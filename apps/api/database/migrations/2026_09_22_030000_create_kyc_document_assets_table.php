<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_document_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('kyc_document_id')->constrained('kyc_documents')->restrictOnDelete();
            $table->string('role', 32);
            $table->string('status', 16);
            $table->string('processor', 64);
            $table->string('processor_version', 64);
            $table->string('storage_disk', 64)->nullable();
            $table->text('object_key')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['kyc_document_id', 'role', 'processor', 'processor_version'], 'kyc_document_assets_logical_unique');
            $table->index(['kyc_document_id', 'role']);
            $table->index(['status', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE kyc_document_assets
    ADD CONSTRAINT kyc_document_assets_role_valid CHECK (role IN ('PREVIEW', 'OPTIMIZED')),
    ADD CONSTRAINT kyc_document_assets_status_valid CHECK (status IN ('PENDING', 'READY', 'FAILED')),
    ADD CONSTRAINT kyc_document_assets_sha256_valid CHECK (sha256 IS NULL OR sha256 ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT kyc_document_assets_size_positive CHECK (size_bytes IS NULL OR size_bytes > 0),
    ADD CONSTRAINT kyc_document_assets_width_positive CHECK (width IS NULL OR width > 0),
    ADD CONSTRAINT kyc_document_assets_height_positive CHECK (height IS NULL OR height > 0),
    ADD CONSTRAINT kyc_document_assets_ready_complete CHECK (
        status <> 'READY' OR (
            storage_disk IS NOT NULL AND object_key IS NOT NULL AND mime_type IS NOT NULL
            AND size_bytes IS NOT NULL AND sha256 IS NOT NULL
        )
    );
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_document_assets');
    }
};
