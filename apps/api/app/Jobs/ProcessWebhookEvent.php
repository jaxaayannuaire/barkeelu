<?php

namespace App\Jobs;

use App\Enums\WebhookEventStatus;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Models\WebhookEvent;
use App\Services\Checkout\CheckoutPaymentService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\ProviderGatewayResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $webhookEventId) {}

    public function handle(PaymentService $paymentService, ?CheckoutPaymentService $checkoutPayments = null, ?ProviderGatewayResolver $gateways = null): void
    {
        $event = DB::transaction(function (): ?WebhookEvent {
            $event = WebhookEvent::query()->lockForUpdate()->findOrFail($this->webhookEventId);

            if (! $event->signature_valid || in_array($event->status, [WebhookEventStatus::PROCESSED, WebhookEventStatus::IGNORED], true)) {
                return null;
            }

            $event->update(['status' => WebhookEventStatus::PROCESSING, 'attempts' => $event->attempts + 1]);

            return $event->refresh();
        });

        if ($event === null) {
            return;
        }

        try {
            $payload = json_decode($event->raw_payload, true, 512, JSON_THROW_ON_ERROR);
            $mapped = null;
            if ($event->provider === 'WAVE') {
                $account = ProviderAccount::query()->findOrFail($event->provider_account_id);
                $mapped = ($gateways ?? app(ProviderGatewayResolver::class))->for($account)->mapWebhook($payload, $account);
            }
            if (($mapped['ignore'] ?? false) === true) {
                $event->update(['status' => WebhookEventStatus::PROCESSED, 'processed_at' => now(), 'last_error' => null]);

                return;
            }
            $payment = $mapped['payment'] ?? Payment::query()->where('provider_account_id', $event->provider_account_id)->where('internal_reference', $payload['internal_reference'] ?? null)->firstOrFail();
            $payment = $paymentService->applyProviderState($payment, $mapped['event'] ?? [
                'provider_account_id' => $event->provider_account_id,
                'internal_reference' => $payload['internal_reference'],
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
                'provider_status' => $payload['provider_status'],
                'provider_payment_id' => $payload['provider_payment_id'] ?? null,
                'provider_reference' => $payload['provider_reference'] ?? null,
                'payload' => $payload,
            ]);
            $checkoutPayments?->syncFromPayment($payment);

            $event->update(['status' => WebhookEventStatus::PROCESSED, 'processed_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            $event->update(['status' => WebhookEventStatus::FAILED, 'last_error' => 'Traitement webhook échoué.']);

            throw $exception;
        }
    }
}
