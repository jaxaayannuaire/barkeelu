<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEntityType;
use App\Enums\KycReviewEventType;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\KycReviewEvent;
use App\Models\User;
use App\Services\Kyc\CreateKycProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class KycReviewEventFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_profile_human_event_is_inserted_with_metadata_and_relations(): void
    {
        $actor = User::factory()->create();
        $profile = $this->profile($actor);

        $event = KycReviewEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'entity_type' => KycReviewEntityType::PROFILE,
            'kyc_profile_id' => $profile->id,
            'event_type' => KycReviewEventType::PROFILE_CREATED,
            'to_status' => KycStatus::DRAFT->value,
            'risk_level_after' => KycRiskLevel::UNKNOWN,
            'actor_type' => KycReviewActorType::HUMAN,
            'actor_user_id' => $actor->id,
            'metadata' => ['source' => 'test'],
        ]);

        $this->assertTrue($event->profile->is($profile));
        $this->assertTrue($event->actor->is($actor));
        $this->assertNull($event->document);
        $this->assertSame(['source' => 'test'], $event->metadata);
        $this->assertSame(KycReviewEntityType::PROFILE, $event->entity_type);
        $this->assertSame(KycReviewActorType::HUMAN, $event->actor_type);
        $this->assertCount(1, $profile->reviewEvents);
    }

    public function test_document_event_relates_to_document(): void
    {
        $actor = User::factory()->create();
        $profile = $this->profile($actor);
        $document = $this->document($profile, $actor);

        $event = KycReviewEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'entity_type' => KycReviewEntityType::DOCUMENT,
            'kyc_profile_id' => $profile->id,
            'kyc_document_id' => $document->id,
            'event_type' => KycReviewEventType::DOCUMENT_UPLOADED,
            'to_status' => KycDocumentStatus::UPLOADED->value,
            'actor_type' => KycReviewActorType::HUMAN,
            'actor_user_id' => $actor->id,
        ]);

        $this->assertTrue($event->document->is($document));
        $this->assertCount(1, $document->reviewEvents);
    }

    public function test_document_event_cannot_reference_document_from_another_profile(): void
    {
        $actor = User::factory()->create();
        $profileA = $this->profile($actor);
        $profileB = $this->profile(User::factory()->create());
        $documentB = $this->document($profileB, $actor);

        $this->expectException(QueryException::class);
        KycReviewEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'entity_type' => KycReviewEntityType::DOCUMENT,
            'kyc_profile_id' => $profileA->id,
            'kyc_document_id' => $documentB->id,
            'event_type' => KycReviewEventType::DOCUMENT_UPLOADED,
            'to_status' => KycDocumentStatus::UPLOADED->value,
            'actor_type' => KycReviewActorType::HUMAN,
            'actor_user_id' => $actor->id,
        ]);
    }

    public function test_entity_and_actor_constraints_are_enforced_by_postgresql(): void
    {
        $actor = User::factory()->create();
        $profile = $this->profile($actor);
        $document = $this->document($profile, $actor);

        foreach ([
            ['entity_type' => 'PROFILE', 'kyc_document_id' => $document->id, 'actor_type' => 'HUMAN', 'actor_user_id' => $actor->id],
            ['entity_type' => 'DOCUMENT', 'kyc_document_id' => null, 'actor_type' => 'HUMAN', 'actor_user_id' => $actor->id],
            ['entity_type' => 'PROFILE', 'kyc_document_id' => null, 'actor_type' => 'HUMAN', 'actor_user_id' => null],
            ['entity_type' => 'PROFILE', 'kyc_document_id' => null, 'actor_type' => 'SYSTEM', 'actor_user_id' => $actor->id],
        ] as $invalid) {
            try {
                DB::table('kyc_review_events')->insert(array_merge([
                    'public_id' => (string) Str::uuid(),
                    'kyc_profile_id' => $profile->id,
                    'event_type' => KycReviewEventType::PROFILE_CREATED->value,
                    'created_at' => now(),
                ], $invalid));
                $this->fail('La contrainte PostgreSQL aurait dû refuser cet événement KYC ambigu.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_event_public_id_is_unique_and_events_are_immutable(): void
    {
        $actor = User::factory()->create();
        $profile = $this->profile($actor);
        $event = $this->profileEvent($profile, $actor);

        $this->assertMutationRefused(fn () => DB::table('kyc_review_events')->where('id', $event->id)->update(['reason_code' => 'MUTATION']));
        $this->assertMutationRefused(fn () => DB::table('kyc_review_events')->where('id', $event->id)->delete());

        $this->expectException(QueryException::class);
        KycReviewEvent::query()->create([
            'public_id' => $event->public_id,
            'entity_type' => KycReviewEntityType::PROFILE,
            'kyc_profile_id' => $profile->id,
            'event_type' => KycReviewEventType::PROFILE_CREATED,
            'actor_type' => KycReviewActorType::HUMAN,
            'actor_user_id' => $actor->id,
        ]);
    }

    public function test_profile_workflow_columns_are_cast(): void
    {
        $actor = User::factory()->create();
        $profile = $this->profile($actor);
        $profile->update([
            'submitted_by_user_id' => $actor->id,
            'review_started_at' => now(),
            'current_reviewer_user_id' => $actor->id,
            'verified_at' => now(),
            'expires_at' => now()->addYear(),
            'suspended_at' => now(),
            'suspension_reason' => 'Test',
        ]);

        $profile->refresh();
        $this->assertTrue($profile->submitter->is($actor));
        $this->assertTrue($profile->currentReviewer->is($actor));
        $this->assertInstanceOf(Carbon::class, $profile->review_started_at);
        $this->assertInstanceOf(Carbon::class, $profile->verified_at);
        $this->assertInstanceOf(Carbon::class, $profile->expires_at);
        $this->assertInstanceOf(Carbon::class, $profile->suspended_at);
    }

    public function test_create_kyc_profile_records_profile_created_with_human_actor(): void
    {
        $actor = User::factory()->create();

        $profile = app(CreateKycProfile::class)->create($actor, $actor);

        $this->assertDatabaseHas('kyc_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('kyc_review_events', [
            'kyc_profile_id' => $profile->id,
            'entity_type' => KycReviewEntityType::PROFILE->value,
            'event_type' => KycReviewEventType::PROFILE_CREATED->value,
            'to_status' => KycStatus::DRAFT->value,
            'risk_level_after' => KycRiskLevel::UNKNOWN->value,
            'actor_type' => KycReviewActorType::HUMAN->value,
            'actor_user_id' => $actor->id,
        ]);
        $this->assertSame(1, $profile->reviewEvents()->count());
    }

    public function test_profile_and_profile_created_event_roll_back_together_on_event_failure(): void
    {
        $actor = User::factory()->create();

        DB::unprepared(<<<'SQL'
CREATE FUNCTION test_reject_profile_created_event()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.event_type = 'PROFILE_CREATED' THEN
        RAISE EXCEPTION 'test PROFILE_CREATED failure';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER test_reject_profile_created_event
BEFORE INSERT ON kyc_review_events
FOR EACH ROW EXECUTE FUNCTION test_reject_profile_created_event();
SQL);

        try {
            $exception = null;

            try {
                app(CreateKycProfile::class)->create($actor, $actor);
            } catch (QueryException $caught) {
                $exception = $caught;
            }

            $this->assertNotNull($exception);
            $this->assertStringContainsString('test PROFILE_CREATED failure', $exception->getMessage());
            $this->assertDatabaseMissing('kyc_profiles', ['user_id' => $actor->id]);
            $this->assertDatabaseCount('kyc_review_events', 0);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS test_reject_profile_created_event ON kyc_review_events; DROP FUNCTION IF EXISTS test_reject_profile_created_event();');
        }
    }

    private function profile(User $user): KycProfile
    {
        return KycProfile::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'status' => KycStatus::DRAFT,
            'risk_level' => KycRiskLevel::UNKNOWN,
        ]);
    }

    private function document(KycProfile $profile, User $actor): KycDocument
    {
        return KycDocument::query()->create([
            'public_id' => (string) Str::uuid(),
            'kyc_profile_id' => $profile->id,
            'type' => KycDocumentType::IDENTITY_DOCUMENT,
            'status' => KycDocumentStatus::UPLOADED,
            'storage_disk' => 'kyc_private',
            'object_key' => 'tests/'.$profile->public_id.'.pdf',
            'sha256' => str_repeat('a', 64),
            'mime_type' => 'application/pdf',
            'size_bytes' => 1,
            'uploaded_by_user_id' => $actor->id,
        ]);
    }

    private function profileEvent(KycProfile $profile, User $actor): KycReviewEvent
    {
        return KycReviewEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'entity_type' => KycReviewEntityType::PROFILE,
            'kyc_profile_id' => $profile->id,
            'event_type' => KycReviewEventType::PROFILE_CREATED,
            'actor_type' => KycReviewActorType::HUMAN,
            'actor_user_id' => $actor->id,
        ]);
    }

    private function assertMutationRefused(callable $mutation): void
    {
        try {
            DB::transaction($mutation);
            $this->fail('Mutation événement KYC immutable doit être refusée.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('KYC review events are immutable', $exception->getMessage());
        }
    }
}
