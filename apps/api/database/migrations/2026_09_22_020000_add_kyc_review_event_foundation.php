<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('current_reviewer_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
        });

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->unique(['id', 'kyc_profile_id'], 'kyc_documents_id_profile_unique');
        });

        Schema::create('kyc_review_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('entity_type', 16);
            $table->foreignId('kyc_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('kyc_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event_type', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('risk_level_before', 32)->nullable();
            $table->string('risk_level_after', 32)->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->text('reason_text')->nullable();
            $table->string('actor_type', 16);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['kyc_profile_id', 'created_at']);
            $table->index(['kyc_document_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE kyc_review_events
    ADD CONSTRAINT kyc_review_events_entity_type_valid
        CHECK (entity_type IN ('PROFILE', 'DOCUMENT')),
    ADD CONSTRAINT kyc_review_events_entity_document_consistent
        CHECK (
            (entity_type = 'PROFILE' AND kyc_document_id IS NULL)
            OR (entity_type = 'DOCUMENT' AND kyc_document_id IS NOT NULL)
        ),
    ADD CONSTRAINT kyc_review_events_actor_type_valid
        CHECK (actor_type IN ('HUMAN', 'SYSTEM')),
    ADD CONSTRAINT kyc_review_events_actor_consistent
        CHECK (
            (actor_type = 'HUMAN' AND actor_user_id IS NOT NULL)
            OR (actor_type = 'SYSTEM' AND actor_user_id IS NULL)
        );

CREATE OR REPLACE FUNCTION prevent_kyc_review_event_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'KYC review events are immutable';
END;
$$;

ALTER TABLE kyc_review_events
    ADD CONSTRAINT kyc_review_events_document_profile_fk
    FOREIGN KEY (kyc_document_id, kyc_profile_id)
    REFERENCES kyc_documents (id, kyc_profile_id)
    ON DELETE RESTRICT;

CREATE TRIGGER kyc_review_events_immutable_update
BEFORE UPDATE ON kyc_review_events
FOR EACH ROW EXECUTE FUNCTION prevent_kyc_review_event_mutation();

CREATE TRIGGER kyc_review_events_immutable_delete
BEFORE DELETE ON kyc_review_events
FOR EACH ROW EXECUTE FUNCTION prevent_kyc_review_event_mutation();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS kyc_review_events_immutable_update ON kyc_review_events;
DROP TRIGGER IF EXISTS kyc_review_events_immutable_delete ON kyc_review_events;
DROP FUNCTION IF EXISTS prevent_kyc_review_event_mutation();
SQL);

        Schema::dropIfExists('kyc_review_events');

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->dropUnique('kyc_documents_id_profile_unique');
        });

        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropForeign(['submitted_by_user_id']);
            $table->dropForeign(['current_reviewer_user_id']);
            $table->dropColumn([
                'submitted_by_user_id',
                'review_started_at',
                'current_reviewer_user_id',
                'verified_at',
                'expires_at',
                'suspended_at',
                'suspension_reason',
            ]);
        });
    }
};
