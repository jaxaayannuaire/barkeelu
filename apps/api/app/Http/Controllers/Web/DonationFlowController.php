<?php

namespace App\Http\Controllers\Web;

use App\Enums\CampaignFundraisingStatus;
use App\Enums\CheckoutStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Services\Checkout\CheckoutConfirmationService;
use App\Services\Checkout\CheckoutPaymentService;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\CheckoutSessionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function payCheckout(string $slug, string $checkout, CheckoutPaymentService $checkoutPayments): RedirectResponse
    {
        $session = $this->checkoutSession($slug, $checkout);
        try {
            $account = $this->waveAccount($session->currency);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }
        $paymentKey = 'ssr-payment-'.$session->public_id.'-'.($session->donation?->payments()->count() + 1);
        $waiting = route('donations.waiting.checkout', [$slug, $session->public_id]);

        try {
            $result = $checkoutPayments->initiate($session, $account, [
                'idempotency_key' => $paymentKey,
                'payer_mobile' => $session->donor_snapshot['phone'] ?? null,
                'success_url' => $waiting,
                'error_url' => $waiting,
            ]);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }

        return $this->redirectForPayment($slug, $session, $result->payment, $result->redirectUrl);
    }

    public function waitingCheckout(string $slug, string $checkout): View
    {
        $session = $this->checkoutSession($slug, $checkout);
        $payment = $session->lastPayment()->firstOrFail();

        return view('pages.donations.waiting', ['checkout' => $session, 'payment' => $payment]);
    }

    public function statusCheckout(string $slug, string $checkout)
    {
        $session = $this->checkoutSession($slug, $checkout);
        $payment = $session->lastPayment()->first();

        return response()->json([
            'checkout_status' => $session->status->value,
            'payment_status' => $payment?->status?->value,
            'donation_status' => $payment?->donation?->status?->value,
        ])->header('Cache-Control', 'no-store');
    }

    public function retryCheckout(string $slug, string $checkout, CheckoutPaymentService $checkoutPayments): RedirectResponse
    {
        $session = $this->checkoutSession($slug, $checkout);
        $payment = $session->lastPayment()->firstOrFail();
        if ($payment->status !== PaymentStatus::FAILED) {
            abort(409, 'Seul un paiement définitivement échoué peut être retenté.');
        }

        try {
            $account = $this->waveAccount($session->currency);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }
        $paymentKey = 'ssr-payment-'.$session->public_id.'-'.($session->donation?->payments()->count() + 1);
        $waiting = route('donations.waiting.checkout', [$slug, $session->public_id]);

        try {
            $result = $checkoutPayments->initiate($session, $account, [
                'idempotency_key' => $paymentKey,
                'payer_mobile' => $session->donor_snapshot['phone'] ?? null,
                'success_url' => $waiting,
                'error_url' => $waiting,
            ]);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }

        return $this->redirectForPayment($slug, $session, $result->payment, $result->redirectUrl);
    }

    public function resolveCheckoutUnknown(string $slug, string $checkout, CheckoutPaymentService $checkoutPayments): RedirectResponse
    {
        $session = $this->checkoutSession($slug, $checkout);
        $payment = $session->lastPayment()->firstOrFail();
        if ($payment->status !== PaymentStatus::UNKNOWN) {
            abort(409, 'Seul un paiement UNKNOWN peut être vérifié.');
        }

        try {
            $resolved = $checkoutPayments->resolveUnknown($payment);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }

        return $this->redirectForPayment($slug, $session->refresh(), $resolved, null);
    }

    public function thanksCheckout(string $slug, string $checkout): View
    {
        $session = $this->checkoutSession($slug, $checkout);
        abort_unless($session->status === CheckoutStatus::PAID, 404);

        return view('pages.donations.thanks', ['checkout' => $session, 'donation' => $session->donation]);
    }

    public function failedCheckout(string $slug, string $checkout): View
    {
        $session = $this->checkoutSession($slug, $checkout);
        $payment = $session->lastPayment()->firstOrFail();
        abort_unless($payment->status === PaymentStatus::FAILED, 404);

        return view('pages.donations.failed', ['checkout' => $session, 'payment' => $payment]);
    }

    private function waveAccount(string $currency): ProviderAccount
    {
        $accounts = ProviderAccount::query()->where('provider', 'WAVE')->where('currency', $currency)->where('is_active', true)->get();
        if ($accounts->count() !== 1) {
            throw new DomainException($accounts->isEmpty() ? 'Aucun compte Wave actif compatible.' : 'Plusieurs comptes Wave actifs compatibles nécessitent une règle de sélection.');
        }

        return $accounts->first();
    }

    private function redirectForPayment(string $slug, CheckoutSession $session, Payment $payment, ?string $redirectUrl): RedirectResponse
    {
        if ($payment->status === PaymentStatus::PENDING && $redirectUrl !== null) {
            return redirect()->away($redirectUrl);
        }
        if ($payment->status === PaymentStatus::PAID) {
            return to_route('donations.thanks.checkout', [$slug, $session->public_id]);
        }
        if ($payment->status === PaymentStatus::FAILED) {
            return to_route('donations.failed.checkout', [$slug, $session->public_id]);
        }

        return to_route('donations.waiting.checkout', [$slug, $session->public_id]);
    }

    public function pay(): RedirectResponse
    {
        abort(410, 'Le paiement SSR legacy est désactivé pour ce parcours.');
    }

    public function waiting(string $donation, string $payment): View
    {
        abort(410, 'La route SSR legacy est désactivée pour ce parcours.');
    }

    public function status(string $donation, string $payment)
    {
        abort(410, 'La route SSR legacy est désactivée pour ce parcours.');
    }

    public function thanks(string $donation): View
    {
        abort(410, 'La route SSR legacy est désactivée pour ce parcours.');
    }

    public function failed(string $donation, string $payment): View
    {
        abort(410, 'La route SSR legacy est désactivée pour ce parcours.');
    }

    public function retry(): RedirectResponse
    {
        abort(410, 'Le retry SSR legacy est désactivé pour ce parcours.');
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
}
