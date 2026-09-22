<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_posted_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    parent_id bigint;
BEGIN
    IF TG_TABLE_NAME = 'ledger_transactions' THEN
        IF OLD.status = 'POSTED' THEN
            RAISE EXCEPTION 'Posted ledger transaction is immutable';
        END IF;
    ELSIF TG_TABLE_NAME = 'ledger_entries' THEN
        parent_id := CASE
            WHEN TG_OP = 'INSERT' THEN NEW.ledger_transaction_id
            ELSE OLD.ledger_transaction_id
        END;

        IF EXISTS (
            SELECT 1
            FROM ledger_transactions
            WHERE id = parent_id
              AND status = 'POSTED'
        ) THEN
            RAISE EXCEPTION 'Posted ledger entry is immutable';
        END IF;
    ELSE
        RAISE EXCEPTION 'Unsupported ledger immutability trigger table: %', TG_TABLE_NAME;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;
SQL);
    }

    public function down(): void {}
};
