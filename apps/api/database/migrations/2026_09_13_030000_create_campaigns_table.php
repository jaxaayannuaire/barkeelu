<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->bigInteger('goal_amount');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('fundraising_status', 32);
            $table->string('payout_status', 32);
            $table->string('visibility', 32);
            $table->boolean('featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('gross_collected_nominal')->default(0);
            $table->bigInteger('refunded_nominal')->default(0);
            $table->bigInteger('net_collected_nominal')->default(0);
            $table->bigInteger('available_for_payout')->default(0);
            $table->bigInteger('reserved_for_payout')->default(0);
            $table->bigInteger('paid_out_amount')->default(0);
            $table->bigInteger('donation_count')->default(0);
            $table->bigInteger('distinct_donor_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_exactly_one_owner CHECK (((owner_user_id IS NOT NULL)::int + (owner_organization_id IS NOT NULL)::int) = 1)');
        DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_goal_positive CHECK (goal_amount > 0)');
        DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_projections_non_negative CHECK (gross_collected_nominal >= 0 AND refunded_nominal >= 0 AND net_collected_nominal >= 0 AND available_for_payout >= 0 AND reserved_for_payout >= 0 AND paid_out_amount >= 0 AND donation_count >= 0 AND distinct_donor_count >= 0)');
        DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_dates_coherent CHECK (end_at IS NULL OR start_at IS NULL OR end_at > start_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
