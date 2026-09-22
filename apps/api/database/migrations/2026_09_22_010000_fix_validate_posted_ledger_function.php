<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION validate_posted_ledger()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    tx bigint;
    deb bigint;
    cred bigint;
    cnt bigint;
    bad bigint;
BEGIN
    IF TG_TABLE_NAME = 'ledger_entries' THEN
        tx := CASE
            WHEN TG_OP = 'DELETE' THEN OLD.ledger_transaction_id
            ELSE NEW.ledger_transaction_id
        END;
    ELSIF TG_TABLE_NAME = 'ledger_transactions' THEN
        tx := CASE
            WHEN TG_OP = 'DELETE' THEN OLD.id
            ELSE NEW.id
        END;
    ELSE
        RAISE EXCEPTION 'Unsupported ledger validation trigger table: %', TG_TABLE_NAME;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM ledger_transactions
        WHERE id = tx
          AND status = 'POSTED'
    ) THEN
        SELECT
            count(*),
            COALESCE(sum(CASE WHEN direction = 'DEBIT' THEN amount ELSE 0 END), 0),
            COALESCE(sum(CASE WHEN direction = 'CREDIT' THEN amount ELSE 0 END), 0),
            count(*) FILTER (WHERE a.currency <> t.currency)
        INTO cnt, deb, cred, bad
        FROM ledger_entries e
        JOIN ledger_accounts a ON a.id = e.ledger_account_id
        JOIN ledger_transactions t ON t.id = e.ledger_transaction_id
        WHERE e.ledger_transaction_id = tx;

        IF cnt < 2 OR deb <> cred OR bad > 0 THEN
            RAISE EXCEPTION 'Invalid posted ledger transaction %', tx;
        END IF;
    END IF;

    RETURN NULL;
END;
$$;
SQL);
    }

    public function down(): void {}
};
