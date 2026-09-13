<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_representatives', function (Blueprint $t) {
            $t->id();
            $t->foreignId('beneficiary_id')->constrained()->restrictOnDelete();
            $t->foreignId('representative_user_id')->constrained('users')->restrictOnDelete();
            $t->string('status', 32);
            $t->timestamp('valid_from');
            $t->timestamp('valid_until')->nullable();
            $t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('ended_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        DB::statement('ALTER TABLE beneficiary_representatives ADD CONSTRAINT beneficiary_representatives_valid_interval CHECK (valid_until IS NULL OR valid_until > valid_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_representatives');
    }
};
