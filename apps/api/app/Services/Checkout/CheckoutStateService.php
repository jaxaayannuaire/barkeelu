<?php

namespace App\Services\Checkout;

use App\Enums\CheckoutStatus;
use App\Models\CheckoutSession;
use App\Models\Payment;
use DomainException;
use Illuminate\Support\Facades\DB;

class CheckoutStateService
{
    private const TRANSITIONS = [
        'DRAFT' => ['QUOTED', 'EXPIRED', 'CANCELLED'],
        'QUOTED' => ['QUOTED', 'CONFIRMED', 'EXPIRED', 'CANCELLED'],
        'CONFIRMED' => ['PAYMENT_PENDING', 'EXPIRED', 'CANCELLED'],
        'PAYMENT_PENDING' => ['PAID', 'FAILED', 'UNKNOWN', 'CANCELLED'],
        'UNKNOWN' => ['UNKNOWN', 'PAID', 'FAILED'],
        'FAILED' => ['PAYMENT_PENDING', 'CANCELLED'],
        'PAID' => ['PAID'],
        'EXPIRED' => [],
        'CANCELLED' => [],
    ];

    public function transition(CheckoutSession $session, CheckoutStatus $target, bool $serverAuthority = false, bool $providerEvidence = false): CheckoutSession
    {
        return DB::transaction(function () use ($session, $target, $serverAuthority, $providerEvidence): CheckoutSession {
            $locked = CheckoutSession::query()->lockForUpdate()->findOrFail($session->id);
            $current = $locked->status->value;
            $targetValue = $target->value;

            if (! in_array($targetValue, self::TRANSITIONS[$current] ?? [], true)) {
                throw new DomainException("Transition checkout interdite : {$current} -> {$targetValue}.");
            }

            if ($target === CheckoutStatus::EXPIRED) {
                if (! in_array($locked->status, [CheckoutStatus::DRAFT, CheckoutStatus::QUOTED, CheckoutStatus::CONFIRMED], true)
                    || $locked->checkout_expires_at === null
                    || $locked->checkout_expires_at->isFuture()) {
                    throw new DomainException('Checkout non expiré ou déjà engagé auprès d’un fournisseur.');
                }
            }

            if ($target === CheckoutStatus::UNKNOWN && ! $providerEvidence && ! $this->hasPaymentEvidence($locked)) {
                throw new DomainException('UNKNOWN exige un Payment ou une preuve d’émission fournisseur.');
            }

            if ($target === CheckoutStatus::PAID && ! $serverAuthority) {
                throw new DomainException('PAID exige une autorité server-side.');
            }

            $locked->status = $target;
            if ($target === CheckoutStatus::CONFIRMED && $locked->confirmed_at === null) {
                $locked->confirmed_at = now();
            }
            $locked->save();

            return $locked->refresh();
        });
    }

    private function hasPaymentEvidence(CheckoutSession $session): bool
    {
        return $session->last_payment_id !== null
            && Payment::query()->whereKey($session->last_payment_id)->exists();
    }
}
