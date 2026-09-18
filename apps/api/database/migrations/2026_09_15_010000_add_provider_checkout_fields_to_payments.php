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
            $table->string('provider_checkout_session_id')->nullable()->after('provider');
            $table->string('provider_client_reference')->nullable()->after('provider_checkout_session_id');
            $table->timestampTz('provider_checkout_expires_at')->nullable()->after('provider_client_reference');
            $table->text('payer_mobile_encrypted')->nullable()->after('provider_checkout_expires_at');
        });

        DB::statement('CREATE UNIQUE INDEX payments_provider_checkout_session_unique ON payments (provider_account_id, provider_checkout_session_id) WHERE provider_checkout_session_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX payments_provider_client_reference_unique ON payments (provider_account_id, provider_client_reference) WHERE provider_client_reference IS NOT NULL');

    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_provider_checkout_session_unique');
        DB::statement('DROP INDEX IF EXISTS payments_provider_client_reference_unique');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['provider_checkout_session_id', 'provider_client_reference', 'provider_checkout_expires_at', 'payer_mobile_encrypted']);
        });
    }
};
