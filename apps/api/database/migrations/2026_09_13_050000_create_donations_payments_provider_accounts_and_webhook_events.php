<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('provider', 64);
            $table->string('name');
            $table->string('environment', 16);
            $table->string('merchant_reference')->nullable();
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->string('secure_configuration_reference')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'environment', 'merchant_reference']);
        });

        Schema::create('donations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('donor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('donor_name')->nullable();
            $table->string('donor_email')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->char('currency', 3);
            $table->bigInteger('nominal_amount');
            $table->bigInteger('platform_fee_amount')->default(0);
            $table->bigInteger('payout_provision_amount')->default(0);
            $table->bigInteger('total_payable_amount');
            $table->string('status', 32);
            $table->string('idempotency_key')->unique();
            $table->char('content_hash', 64);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('donation_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_account_id')->constrained()->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('internal_reference')->unique();
            $table->char('currency', 3);
            $table->bigInteger('amount');
            $table->string('status', 32);
            $table->string('idempotency_key')->unique();
            $table->char('content_hash', 64);
            $table->string('provider_status')->nullable();
            $table->jsonb('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('provider_account_id')->constrained()->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('provider_event_id')->nullable();
            $table->string('event_type')->nullable();
            $table->boolean('signature_valid');
            $table->jsonb('headers_redacted')->nullable();
            $table->text('raw_payload');
            $table->char('payload_hash', 64);
            $table->string('dedupe_key')->unique();
            $table->string('status', 32);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE provider_accounts ADD CONSTRAINT provider_accounts_currency_xof CHECK (currency = 'XOF');
ALTER TABLE provider_accounts ADD CONSTRAINT provider_accounts_environment_valid CHECK (environment IN ('TEST', 'PRODUCTION'));
ALTER TABLE donations ADD CONSTRAINT donations_currency_xof CHECK (currency = 'XOF');
ALTER TABLE donations ADD CONSTRAINT donations_nominal_positive CHECK (nominal_amount > 0);
ALTER TABLE donations ADD CONSTRAINT donations_fees_nonnegative CHECK (platform_fee_amount >= 0 AND payout_provision_amount >= 0);
ALTER TABLE donations ADD CONSTRAINT donations_total_coherent CHECK (total_payable_amount = nominal_amount + platform_fee_amount + payout_provision_amount);
ALTER TABLE payments ADD CONSTRAINT payments_currency_xof CHECK (currency = 'XOF');
ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0);
CREATE UNIQUE INDEX payments_provider_payment_unique ON payments (provider_account_id, provider_payment_id) WHERE provider_payment_id IS NOT NULL;
CREATE UNIQUE INDEX webhook_events_valid_provider_event_unique ON webhook_events (provider_account_id, provider_event_id) WHERE signature_valid AND provider_event_id IS NOT NULL;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('provider_accounts');
    }
};
