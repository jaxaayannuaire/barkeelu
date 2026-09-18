<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('donor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('donation_id')->nullable()->unique()->constrained('donations')->nullOnDelete();
            $table->string('status', 32);
            $table->char('currency', 3);
            $table->bigInteger('nominal_amount');
            $table->bigInteger('total_payable_amount')->nullable();
            $table->jsonb('fee_snapshot')->nullable();
            $table->jsonb('donor_snapshot')->nullable();
            $table->timestampTz('quote_expires_at')->nullable();
            $table->timestampTz('checkout_expires_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->string('idempotency_key')->unique();
            $table->char('content_hash', 64);
            $table->foreignId('last_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE checkout_sessions
    ADD CONSTRAINT checkout_sessions_status_valid CHECK (status IN ('DRAFT', 'QUOTED', 'CONFIRMED', 'PAYMENT_PENDING', 'PAID', 'FAILED', 'UNKNOWN', 'EXPIRED', 'CANCELLED'));
ALTER TABLE checkout_sessions
    ADD CONSTRAINT checkout_sessions_currency_present CHECK (char_length(currency) = 3);
ALTER TABLE checkout_sessions
    ADD CONSTRAINT checkout_sessions_nominal_positive CHECK (nominal_amount > 0);
ALTER TABLE checkout_sessions
    ADD CONSTRAINT checkout_sessions_total_coherent CHECK (total_payable_amount IS NULL OR total_payable_amount >= nominal_amount);
ALTER TABLE checkout_sessions
    ADD CONSTRAINT checkout_sessions_expiry_after_creation CHECK (checkout_expires_at > created_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
