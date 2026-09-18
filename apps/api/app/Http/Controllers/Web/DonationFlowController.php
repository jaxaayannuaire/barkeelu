<?php

namespace App\Http\Controllers\Web;

use App\Enums\CampaignFundraisingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Services\Checkout\CheckoutConfirmationService;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Donations\DonationService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WaveCheckoutService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DonationFlowController extends Controller
{
    public function amount(string $slug): View
    {
        $campaign = $this->campaign($slug);
        $checkout = $this->sessionCheckout($slug);

        return view('pages.donations.amount', ['campaign' => $campaign, 'checkout' => $checkout]);
    }

    public function storeAmount(Request $request, string $slug, CheckoutSessionService $checkouts): RedirectResponse
    {
        $campaign = $this->campaign($slug);
        $input = $request->validate(['nominal_amount' => ['required', 'integer', 'min:1']]);
        $existing = $this->sessionCheckout($slug);
        $idempotencyKey = $existing?->idempotency_key
            ?? 'ssr-checkout-'.hash('sha256', $request->session()->getId().'|'.$campaign->id);

        try {
            $checkout = $checkouts->create($campaign, $request->user(), [
                'nominal_amount' => (int) $input['nominal_amount'],
                'currency' => $campaign->currency,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }
        session(['checkout_public_id' => $checkout->public_id]);

        return to_route('donations.details', [$campaign->slug, $checkout->public_id]);
    }

    public function details(string $slug, string $checkout): View
    {
        return view('pages.donations.details', ['campaign' => $this->campaign($slug), 'checkout' => $this->checkoutSession($slug, $checkout)]);
    }

    public function storeDetails(Request $request, string $slug, string $checkout, CheckoutQuoteService $quotes): RedirectResponse
    {
        $input = $request->validate([
            'donor_name' => ['required', 'string', 'max:255'],
            'donor_email' => ['nullable', 'email', 'max:255'],
            'is_anonymous' => ['nullable', 'boolean'],
            'show_name' => ['nullable', 'boolean'],
            'show_amount' => ['nullable', 'boolean'],
            'payer_mobile' => ['required', 'regex:/^\\+221[0-9]{9}$/'],
        ]);
        $session = $this->checkoutSession($slug, $checkout);

        try {
            $quotes->quote($session, [
                'name' => $input['donor_name'],
                'email' => $input['donor_email'] ?? null,
                'phone' => $input['payer_mobile'],
                'is_anonymous' => (bool) ($input['is_anonymous'] ?? false),
                'show_name' => (bool) ($input['show_name'] ?? false),
                'show_amount' => (bool) ($input['show_amount'] ?? false),
            ]);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }

        return to_route('donations.checkout', [$slug, $checkout]);
    }

    public function checkout(string $slug, string $checkout): View
    {
        return view('pages.donations.checkout', ['campaign' => $this->campaign($slug), 'checkout' => $this->checkoutSession($slug, $checkout)]);
    }

    public function confirm(string $slug, string $checkout, CheckoutConfirmationService $confirmations): RedirectResponse
    {
        $session = $this->checkoutSession($slug, $checkout);

        try {
            $confirmations->confirm($session, [
                'idempotency_key' => 'ssr-confirmation-'.$session->public_id,
            ]);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }

        return to_route('donations.checkout', [$slug, $checkout]);
    }

    public function pay(Request $request, string $slug, DonationService $donations, PaymentService $payments, WaveCheckoutService $wave): RedirectResponse
    {
        abort(410, 'Le paiement SSR legacy est désactivé pour ce parcours.');

        $campaign = $this->campaign($slug);
        $flow = $this->flow($slug);
        abort_unless($wave->available(), 503);
        $donation = $donations->create($campaign, null, ['nominal_amount' => $flow['nominal_amount'], 'currency' => 'XOF', 'donor_name' => $flow['donor_name'], 'is_anonymous' => $flow['is_anonymous'], 'idempotency_key' => 'guest-donation-'.$flow['token']]);
        $account = ProviderAccount::query()->where('provider', 'WAVE')->where('currency', 'XOF')->where('is_active', true)->firstOrFail();
        $payment = $payments->create($donation, $account, ['amount' => $donation->total_payable_amount, 'currency' => 'XOF', 'idempotency_key' => 'guest-payment-'.$flow['token']]);
        $payment->update(['payer_mobile_encrypted' => $flow['payer_mobile'], 'provider_client_reference' => $payment->internal_reference]);
        $waiting = route('donations.waiting', [$donation->public_id, $payment->public_id]);
        $checkout = $wave->initiate($payment->refresh(), $flow['payer_mobile'], $waiting, $waiting);
        $payment->update(['provider_checkout_session_id' => $checkout['id'], 'provider_checkout_expires_at' => $checkout['when_expires'], 'status' => PaymentStatus::PENDING]);
        session(['donation_payment.'.$payment->public_id => $flow['token']]);

        return redirect()->away($checkout['wave_launch_url']);
    }

    public function waiting(string $donation, string $payment): View
    {
        return view('pages.donations.waiting', ['payment' => $this->privatePayment($donation, $payment)]);
    }

    public function status(string $donation, string $payment)
    {
        $p = $this->privatePayment($donation, $payment);

        return response()->json(['status' => $p->status->value])->header('Cache-Control', 'no-store');
    }

    public function thanks(string $donation): View
    {
        $d = Donation::query()->where('public_id', $donation)->where('status', 'PAID')->firstOrFail();

        return view('pages.donations.thanks', ['donation' => $d]);
    }

    public function failed(string $donation, string $payment): View
    {
        $p = $this->privatePayment($donation, $payment);
        abort_unless(in_array($p->status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED], true), 404);

        return view('pages.donations.failed', ['payment' => $p]);
    }

    public function retry(string $donation, string $payment, PaymentService $payments, WaveCheckoutService $wave): RedirectResponse
    {
        abort(410, 'Le retry SSR legacy est désactivé pour ce parcours.');

        $current = $this->privatePayment($donation, $payment);
        abort_unless($current->provider_checkout_session_id !== null, 422);
        $checkout = $wave->retrieve($current->provider_checkout_session_id);
        if (($checkout['checkout_status'] ?? null) === 'open') {
            abort_unless(is_string($checkout['wave_launch_url'] ?? null), 502);

            return redirect()->away($checkout['wave_launch_url']);
        }
        abort_unless(($checkout['checkout_status'] ?? null) === 'expired', 409);
        $next = DB::transaction(function () use ($current, $payments): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($current->id);
            abort_unless($locked->status === PaymentStatus::EXPIRED, 409);
            $retryKey = 'retry-'.$locked->public_id;
            $existing = Payment::query()->where('idempotency_key', $retryKey)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            return $payments->create($locked->donation, $locked->providerAccount, [
                'amount' => $locked->amount, 'currency' => $locked->currency, 'idempotency_key' => $retryKey,
            ]);
        });
        if ($next->provider_checkout_session_id !== null) {
            $resumed = $wave->retrieve($next->provider_checkout_session_id);
            abort_unless(($resumed['checkout_status'] ?? null) === 'open' && is_string($resumed['wave_launch_url'] ?? null), 409);
            session(['donation_payment.'.$next->public_id => session('donation_payment.'.$current->public_id)]);

            return redirect()->away($resumed['wave_launch_url']);
        }
        $next->update(['payer_mobile_encrypted' => $current->payer_mobile_encrypted, 'provider_client_reference' => $next->internal_reference]);
        $waiting = route('donations.waiting', [$donation, $next->public_id]);
        $created = $wave->initiate($next->refresh(), $next->payer_mobile_encrypted, $waiting, $waiting);
        $next->update(['provider_checkout_session_id' => $created['id'], 'provider_checkout_expires_at' => $created['when_expires'], 'status' => PaymentStatus::PENDING]);
        session(['donation_payment.'.$next->public_id => session('donation_payment.'.$current->public_id)]);

        return redirect()->away($created['wave_launch_url']);
    }

    private function campaign(string $slug): Campaign
    {
        return Campaign::query()->publiclyViewable()->where('fundraising_status', CampaignFundraisingStatus::OPEN)->where('slug', $slug)->firstOrFail();
    }

    private function sessionCheckout(string $slug): ?CheckoutSession
    {
        $publicId = session('checkout_public_id');
        if (! is_string($publicId)) {
            return null;
        }

        return CheckoutSession::query()
            ->where('public_id', $publicId)
            ->whereHas('campaign', fn ($query) => $query->where('slug', $slug))
            ->first();
    }

    private function checkoutSession(string $slug, string $publicId): CheckoutSession
    {
        $checkout = CheckoutSession::query()
            ->where('public_id', $publicId)
            ->whereHas('campaign', fn ($query) => $query->where('slug', $slug))
            ->firstOrFail();

        abort_unless((string) session('checkout_public_id') === $checkout->public_id, 404);

        return $checkout;
    }

    private function flow(string $slug): array
    {
        $flow = session('donation_flow.'.$slug);
        abort_unless(is_array($flow) && ($flow['expires_at'] ?? 0) > now()->timestamp, 419);

        return $flow;
    }

    private function privatePayment(string $donation, string $payment): Payment
    {
        $p = Payment::query()->where('public_id', $payment)->whereHas('donation', fn ($q) => $q->where('public_id', $donation))->firstOrFail();
        abort_unless(hash_equals((string) session('donation_payment.'.$payment), (string) session('donation_flow.'.$p->donation->campaign->slug.'.token')), 404);

        return $p;
    }
}
