<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('type', 32);
            $t->string('display_name');
            $t->string('status', 32);
            $t->foreignId('linked_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('linked_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT beneficiaries_one_link CHECK (NOT (linked_user_id IS NOT NULL AND linked_organization_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
