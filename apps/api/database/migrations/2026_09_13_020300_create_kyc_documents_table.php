<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('kyc_profile_id')->constrained()->restrictOnDelete();
            $t->string('type', 32);
            $t->string('status', 32);
            $t->string('storage_disk');
            $t->text('object_key');
            $t->char('sha256', 64);
            $t->string('mime_type');
            $t->unsignedBigInteger('size_bytes');
            $t->date('issued_at')->nullable();
            $t->date('expires_at')->nullable();
            $t->jsonb('metadata')->nullable();
            $t->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
