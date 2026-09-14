<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->bigInteger('reserved_refund_amount')->default(0);
            $table->bigInteger('executed_refund_amount')->default(0);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_account_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('idempotency_key')->unique();
            $table->char('content_hash', 64);
            $table->string('provider_refund_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_account_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('idempotency_key')->unique();
            $table->char('content_hash', 64);
            $table->jsonb('destination_snapshot');
            $table->jsonb('approved_destination_snapshot')->nullable();
            $table->unsignedInteger('reservation_version')->default(1);
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('provider_payout_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('provider_account_id')->constrained()->restrictOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('source');
            $table->string('status', 32);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('reconciliation_run_id')->constrained()->restrictOnDelete();
            $table->string('item_type');
            $table->string('internal_reference')->nullable();
            $table->string('provider_reference')->nullable();
            $table->bigInteger('internal_amount')->nullable();
            $table->bigInteger('provider_amount')->nullable();
            $table->char('currency', 3);
            $table->string('internal_status')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('result', 32);
            $table->string('resolution_status', 32)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE payments ADD CONSTRAINT payments_refund_amounts_non_negative CHECK (reserved_refund_amount >= 0 AND executed_refund_amount >= 0 AND reserved_refund_amount + executed_refund_amount <= amount);
ALTER TABLE refunds ADD CONSTRAINT refunds_amount_positive CHECK (amount > 0 AND currency = 'XOF');
ALTER TABLE payouts ADD CONSTRAINT payouts_amount_positive CHECK (amount > 0 AND currency = 'XOF');
ALTER TABLE reconciliation_runs ADD CONSTRAINT reconciliation_runs_period_valid CHECK (period_end > period_start);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('refunds');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['reserved_refund_amount', 'executed_refund_amount']);
        });
    }
};
