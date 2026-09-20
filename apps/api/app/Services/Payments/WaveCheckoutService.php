<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\AmbiguousProviderInitiationException;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WaveCheckoutService
{
    public function available(): bool
    {
        return filled(config('services.wave.api_key')) && filled(config('services.wave.request_signing_secret'));
    }

    public function initiate(Payment $payment, string $payerMobile, string $successUrl, string $errorUrl): array
    {
        if (! $this->available()) {
            throw new RuntimeException('Wave n’est pas configuré.');
        }
        $body = json_encode(['amount' => (string) $payment->amount, 'currency' => $payment->currency, 'client_reference' => $payment->internal_reference, 'restrict_payer_mobile' => $payerMobile, 'success_url' => $successUrl, 'error_url' => $errorUrl], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.$body, config('services.wave.request_signing_secret'));
        $response = Http::acceptJson()->withToken(config('services.wave.api_key'))->withHeaders(['Wave-Signature' => "t={$timestamp},v1={$signature}", 'Content-Type' => 'application/json'])->timeout(10)->withBody($body, 'application/json')->post(rtrim(config('services.wave.base_url'), '/').'/v1/checkout/sessions');
        if (! $response->successful()) {
            if (! in_array($response->status(), [400, 401, 403, 422], true)) {
                throw new AmbiguousProviderInitiationException('Réponse Wave ambiguë.');
            }

            throw new RuntimeException('Initiation Wave indisponible.');
        }
        $data = $response->json();
        if (! isset($data['id'], $data['wave_launch_url'], $data['when_expires'])) {
            throw new AmbiguousProviderInitiationException('Réponse Wave incomplète.');
        }

        return $data;
    }

    public function signatureIsValid(string $rawBody, ?string $header): bool
    {
        $secret = config('services.wave.webhook_signing_secret');
        if (! filled($secret) || ! is_string($header)) {
            return false;
        }
        preg_match('/(?:^|,)t=(\d+)/', $header, $time);
        preg_match_all('/(?:^|,)v1=([a-f0-9]{64})/i', $header, $matches);
        if (! isset($time[1]) || abs(now()->timestamp - (int) $time[1]) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $time[1].$rawBody, $secret);
        foreach ($matches[1] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function retrieve(string $checkoutSessionId): array
    {
        if (! $this->available()) {
            throw new RuntimeException('Wave n’est pas configuré.');
        }
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp, config('services.wave.request_signing_secret'));
        $response = Http::acceptJson()->withToken(config('services.wave.api_key'))
            ->withHeaders(['Wave-Signature' => "t={$timestamp},v1={$signature}"])->timeout(10)
            ->get(rtrim(config('services.wave.base_url'), '/').'/v1/checkout/sessions/'.$checkoutSessionId);
        if (! $response->successful()) {
            throw new RuntimeException('Vérification Wave indisponible.');
        }

        return $response->json();
    }
}
