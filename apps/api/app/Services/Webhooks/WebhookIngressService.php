<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\ProviderAccount;
use App\Models\WebhookEvent;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookIngressService
{
    public function receive(ProviderAccount $account, string $rawPayload, array $headers, bool $signatureValid): WebhookEvent
    {
        $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        // Wave identifie l'événement par « id », les intégrations historiques par « event_id ».
        $eventId = $payload['event_id'] ?? $payload['id'] ?? null;
        $payloadHash = hash('sha256', $rawPayload);
        $dedupeKey = hash('sha256', implode('|', [$account->id, $eventId ?? $payloadHash, $payloadHash, $signatureValid ? 'valid' : 'invalid']));

        $event = DB::transaction(function () use ($account, $rawPayload, $headers, $signatureValid, $payload, $eventId, $payloadHash, $dedupeKey): WebhookEvent {
            $existing = WebhookEvent::query()->where('dedupe_key', $dedupeKey)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($signatureValid && $eventId !== null) {
                $conflict = WebhookEvent::query()
                    ->where('provider_account_id', $account->id)
                    ->where('provider_event_id', $eventId)
                    ->where('signature_valid', true)
                    ->first();

                if ($conflict !== null && ! hash_equals($conflict->payload_hash, $payloadHash)) {
                    throw new DomainException('Conflit d’événement webhook fournisseur.');
                }
            }

            return WebhookEvent::query()->create([
                'public_id' => (string) Str::uuid(),
                'provider_account_id' => $account->id,
                'provider' => $account->provider,
                'provider_event_id' => $eventId,
                'event_type' => $payload['event_type'] ?? $payload['type'] ?? null,
                'signature_valid' => $signatureValid,
                'headers_redacted' => $this->redactHeaders($headers),
                'raw_payload' => $rawPayload,
                'payload_hash' => $payloadHash,
                'dedupe_key' => $dedupeKey,
                'status' => $signatureValid ? WebhookEventStatus::VERIFIED : WebhookEventStatus::IGNORED,
                'received_at' => now(),
            ]);
        });

        if ($event->signature_valid && $event->status === WebhookEventStatus::VERIFIED) {
            ProcessWebhookEvent::dispatch($event->id)->afterCommit();
        }

        return $event;
    }

    private function redactHeaders(array $headers): array
    {
        $redacted = [];
        foreach ($headers as $name => $value) {
            $redacted[strtolower($name)] = str_contains(strtolower($name), 'authorization') || str_contains(strtolower($name), 'signature')
                ? '[REDACTED]'
                : $value;
        }

        return $redacted;
    }
}
