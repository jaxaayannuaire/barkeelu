<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Console\Command;

class PurgeExpiredPayerMobiles extends Command
{
    protected $signature = 'payments:purge-expired-payer-mobiles';

    protected $description = 'Purge les numéros payeur chiffrés des tentatives expirées après la rétention autorisée.';

    public function handle(): int
    {
        $count = Payment::query()->where('status', PaymentStatus::EXPIRED)
            ->whereNotNull('payer_mobile_encrypted')->where('updated_at', '<=', now()->subDays(30))
            ->update(['payer_mobile_encrypted' => null]);
        $this->info("{$count} numéro(s) purgé(s).");

        return self::SUCCESS;
    }
}
