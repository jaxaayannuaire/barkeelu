<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_profiles', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $t->foreignId('beneficiary_id')->nullable()->constrained('beneficiaries')->restrictOnDelete();
            $t->string('status', 32);
            $t->string('risk_level', 32);
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
        });
        DB::statement('ALTER TABLE kyc_profiles ADD CONSTRAINT kyc_profiles_exactly_one_subject CHECK (((user_id IS NOT NULL)::int + (organization_id IS NOT NULL)::int + (beneficiary_id IS NOT NULL)::int) = 1)');
        DB::statement('CREATE UNIQUE INDEX kyc_profiles_user_current_unique ON kyc_profiles (user_id) WHERE user_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX kyc_profiles_organization_current_unique ON kyc_profiles (organization_id) WHERE organization_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX kyc_profiles_beneficiary_current_unique ON kyc_profiles (beneficiary_id) WHERE beneficiary_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_profiles');
    }
};
