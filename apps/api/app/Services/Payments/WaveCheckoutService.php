<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\AmbiguousProviderInitiationException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WaveCheckoutService
{
    private const MAX_VALIDATION_DETAILS = 10;

    private const MAX_VALIDATION_LOCATION_SEGMENTS = 10;

    private const MAX_VALIDATION_LOCATION_SEGMENT_LENGTH = 100;

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
        $response = $this->client()->withToken(config('services.wave.api_key'))->withHeaders(['Wave-Signature' => "t={$timestamp},v1={$signature}", 'Content-Type' => 'application/json'])->timeout(10)->withBody($body, 'application/json')->post(rtrim(config('services.wave.base_url'), '/').'/v1/checkout/sessions');
        if (! $response->successful()) {
            $this->logCheckoutInitiationRejected($response->status(), $response->json(), $payment);

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

    private function logCheckoutInitiationRejected(int $httpStatus, mixed $payload, Payment $payment): void
    {
        $context = [
            'provider' => 'WAVE',
            'operation' => 'checkout_initiate',
            'http_status' => $httpStatus,
            'payment_public_id' => $payment->public_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ];
        $providerError = [];
        if (is_array($payload)) {
            foreach (['code', 'error_code', 'message', 'detail', 'type'] as $field) {
                $value = $payload[$field] ?? null;
                if (is_string($value) && $this->isSafeProviderErrorValue($value)) {
                    $providerError[$field] = $value;
                }
            }

            $details = $this->sanitizeValidationDetails($payload['details'] ?? null);
            if ($details !== []) {
                $providerError['details'] = $details;
            }
        }
        if ($providerError !== []) {
            $context['provider_error'] = $providerError;
        }

        Log::warning('Wave checkout initiation rejected.', $context);
    }

    private function isSafeProviderErrorValue(string $value): bool
    {
        return strlen($value) <= 500
            && preg_match('/(?:authorization|api[_-]?key|token|secret|signature|bearer|\+?\d[\d\s().-]{7,}|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/i', $value) !== 1;
    }

    private function sanitizeValidationDetails(mixed $details): array
    {
        if (! is_array($details)) {
            return [];
        }

        $sanitizedDetails = [];
        foreach (array_slice($details, 0, self::MAX_VALIDATION_DETAILS) as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $sanitizedDetail = [];
            $loc = $this->sanitizeValidationLocation($detail['loc'] ?? null);
            if ($loc !== null) {
                $sanitizedDetail['loc'] = $loc;
            }
            foreach (['msg', 'type'] as $field) {
                $value = $detail[$field] ?? null;
                if (is_string($value) && $this->isSafeProviderErrorValue($value)) {
                    $sanitizedDetail[$field] = $value;
                }
            }
            if ($sanitizedDetail !== []) {
                $sanitizedDetails[] = $sanitizedDetail;
            }
        }

        return $sanitizedDetails;
    }

    private function sanitizeValidationLocation(mixed $location): ?array
    {
        if (! is_array($location) || ! array_is_list($location) || count($location) > self::MAX_VALIDATION_LOCATION_SEGMENTS) {
            return null;
        }

        foreach ($location as $segment) {
            if (is_int($segment)) {
                continue;
            }
            if (! is_string($segment) || strlen($segment) > self::MAX_VALIDATION_LOCATION_SEGMENT_LENGTH || preg_match('/^[A-Za-z0-9_.:-]+$/', $segment) !== 1) {
                return null;
            }
        }

        return $location;
    }

    private function client(): PendingRequest
    {
        $proxy = config('services.wave.proxy');

        if (! filled($proxy)) {
            return Http::acceptJson();
        }

        return Http::withOptions(['proxy' => $proxy])->acceptJson();
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
        $response = $this->client()->withToken(config('services.wave.api_key'))
            ->withHeaders(['Wave-Signature' => "t={$timestamp},v1={$signature}"])->timeout(10)
            ->get(rtrim(config('services.wave.base_url'), '/').'/v1/checkout/sessions/'.$checkoutSessionId);
        if (! $response->successful()) {
            throw new RuntimeException('Vérification Wave indisponible.');
        }

        return $response->json();
    }

    public function searchByClientReference(string $clientReference): array
    {
        if (! $this->available()) {
            throw new RuntimeException('Wave n’est pas configuré.');
        }
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp, config('services.wave.request_signing_secret'));
        $response = $this->client()->withToken(config('services.wave.api_key'))
            ->withHeaders(['Wave-Signature' => "t={$timestamp},v1={$signature}"])->timeout(10)
            ->get(rtrim(config('services.wave.base_url'), '/').'/v1/checkout/sessions/search', ['client_reference' => $clientReference]);
        if (! $response->successful()) {
            throw new RuntimeException('Recherche Wave indisponible.');
        }

        return $response->json();
    }
}
