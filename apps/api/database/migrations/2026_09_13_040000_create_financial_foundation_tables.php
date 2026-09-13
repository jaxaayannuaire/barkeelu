<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('account_type', 32);
            $t->char('currency', 3);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('ledger_transactions', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('business_key')->unique();
            $t->char('content_hash', 64);
            $t->char('currency', 3);
            $t->string('status', 16);
            $t->string('source_type')->nullable();
            $t->string('source_reference')->nullable();
            $t->foreignId('reversal_of_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $t->string('description')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('ledger_entries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('ledger_transaction_id')->constrained()->restrictOnDelete();
            $t->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('direction', 8);
            $t->bigInteger('amount');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('fee_policies', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('code')->unique();
            $t->string('fee_type', 32);
            $t->integer('rate_bps')->nullable();
            $t->bigInteger('fixed_amount')->nullable();
            $t->char('currency', 3)->nullable();
            $t->timestamp('effective_from');
            $t->timestamp('effective_to')->nullable();
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
        Schema::create('applied_fees', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('fee_policy_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('source_type');
            $t->string('source_reference');
            $t->string('fee_type', 32);
            $t->integer('rate_bps')->nullable();
            $t->bigInteger('fixed_amount')->nullable();
            $t->bigInteger('basis_amount');
            $t->bigInteger('amount');
            $t->char('currency', 3);
            $t->string('payer');
            $t->string('beneficiary');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('outbox_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('event_type');
            $t->string('aggregate_type');
            $t->string('aggregate_reference');
            $t->string('dedupe_key')->unique();
            $t->jsonb('payload');
            $t->timestamp('occurred_at');
            $t->timestamp('available_at');
            $t->timestamp('published_at')->nullable();
            $t->integer('attempts')->default(0);
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
        DB::unprepared(<<<'SQL'
ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_positive CHECK (amount > 0);
ALTER TABLE fee_policies ADD CONSTRAINT fee_rate_nonnegative CHECK (rate_bps IS NULL OR rate_bps >= 0);
ALTER TABLE fee_policies ADD CONSTRAINT fee_fixed_nonnegative CHECK (fixed_amount IS NULL OR fixed_amount >= 0);
ALTER TABLE fee_policies ADD CONSTRAINT fee_value_present CHECK (rate_bps IS NOT NULL OR fixed_amount IS NOT NULL);
ALTER TABLE fee_policies ADD CONSTRAINT fee_dates_valid CHECK (effective_to IS NULL OR effective_to > effective_from);
CREATE UNIQUE INDEX ledger_transactions_single_reversal ON ledger_transactions (reversal_of_transaction_id) WHERE reversal_of_transaction_id IS NOT NULL;
CREATE OR REPLACE FUNCTION validate_posted_ledger() RETURNS trigger LANGUAGE plpgsql AS $$ DECLARE tx bigint; deb bigint; cred bigint; cnt bigint; bad bigint; BEGIN IF TG_TABLE_NAME = 'ledger_entries' THEN tx := COALESCE(NEW.ledger_transaction_id, OLD.ledger_transaction_id); ELSE tx := COALESCE(NEW.id, OLD.id); END IF; IF EXISTS (SELECT 1 FROM ledger_transactions WHERE id = tx AND status = 'POSTED') THEN SELECT count(*), COALESCE(sum(CASE WHEN direction = 'DEBIT' THEN amount ELSE 0 END),0), COALESCE(sum(CASE WHEN direction = 'CREDIT' THEN amount ELSE 0 END),0), count(*) FILTER (WHERE a.currency <> t.currency) INTO cnt,deb,cred,bad FROM ledger_entries e JOIN ledger_accounts a ON a.id=e.ledger_account_id JOIN ledger_transactions t ON t.id=e.ledger_transaction_id WHERE e.ledger_transaction_id=tx; IF cnt < 2 OR deb <> cred OR bad > 0 THEN RAISE EXCEPTION 'Invalid posted ledger transaction %', tx; END IF; END IF; RETURN NULL; END $$;
CREATE OR REPLACE FUNCTION prevent_posted_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ DECLARE parent_id bigint; BEGIN IF TG_TABLE_NAME = 'ledger_transactions' THEN IF OLD.status = 'POSTED' THEN RAISE EXCEPTION 'Posted ledger transaction is immutable'; END IF; ELSIF TG_TABLE_NAME = 'ledger_entries' THEN parent_id := CASE WHEN TG_OP = 'INSERT' THEN NEW.ledger_transaction_id ELSE OLD.ledger_transaction_id END; IF EXISTS (SELECT 1 FROM ledger_transactions WHERE id = parent_id AND status = 'POSTED') THEN RAISE EXCEPTION 'Posted ledger entry is immutable'; END IF; ELSE RAISE EXCEPTION 'Unsupported ledger immutability trigger table: %', TG_TABLE_NAME; END IF; RETURN COALESCE(NEW, OLD); END $$;
CREATE OR REPLACE FUNCTION prevent_applied_fee_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Applied fee is immutable'; END $$;
CREATE CONSTRAINT TRIGGER ledger_validate_entries AFTER INSERT OR UPDATE OR DELETE ON ledger_entries DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION validate_posted_ledger();
CREATE CONSTRAINT TRIGGER ledger_validate_transactions AFTER INSERT OR UPDATE ON ledger_transactions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION validate_posted_ledger();
CREATE TRIGGER ledger_transactions_immutable BEFORE UPDATE OR DELETE ON ledger_transactions FOR EACH ROW EXECUTE FUNCTION prevent_posted_mutation();
CREATE TRIGGER ledger_entries_immutable BEFORE INSERT OR UPDATE OR DELETE ON ledger_entries FOR EACH ROW EXECUTE FUNCTION prevent_posted_mutation();
CREATE TRIGGER applied_fees_immutable BEFORE UPDATE OR DELETE ON applied_fees FOR EACH ROW EXECUTE FUNCTION prevent_applied_fee_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('applied_fees');
        Schema::dropIfExists('fee_policies');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
        DB::unprepared('DROP FUNCTION IF EXISTS validate_posted_ledger(); DROP FUNCTION IF EXISTS prevent_posted_mutation(); DROP FUNCTION IF EXISTS prevent_applied_fee_mutation();');
    }
};
