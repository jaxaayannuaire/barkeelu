<?php

namespace App\Jobs;

use App\Enums\WebhookEventStatus;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentService;
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

    public function handle(PaymentService $paymentService): void
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
            $payment = Payment::query()
                ->where('provider_account_id', $event->provider_account_id)
                ->where('internal_reference', $payload['internal_reference'] ?? null)
                ->firstOrFail();

            $paymentService->applyProviderState($payment, [
                'provider_account_id' => $event->provider_account_id,
                'internal_reference' => $payload['internal_reference'],
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
                'provider_status' => $payload['provider_status'],
                'provider_payment_id' => $payload['provider_payment_id'] ?? null,
                'provider_reference' => $payload['provider_reference'] ?? null,
                'payload' => $payload,
            ]);

            $event->update(['status' => WebhookEventStatus::PROCESSED, 'processed_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            $event->update(['status' => WebhookEventStatus::FAILED, 'last_error' => 'Traitement webhook échoué.']);

            throw $exception;
        }
    }
}
