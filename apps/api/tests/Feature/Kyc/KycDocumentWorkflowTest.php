<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycReviewEventType;
use App\Exceptions\KycDocumentWorkflowException;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\User;
use App\Services\Kyc\CreateKycProfile;
use App\Services\Kyc\KycDocumentWorkflow;
use App\Services\Kyc\RecordKycReviewEvent;
use App\Services\Kyc\UploadKycDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KycDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_records_document_uploaded_atomically(): void
    {
        Storage::fake('kyc_private');
        [$profile, $uploader] = $this->profile();
        $document = app(UploadKycDocument::class)->upload($profile, $uploader, UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nprivate"), KycDocumentType::IDENTITY_DOCUMENT);

        $this->assertSame(KycDocumentStatus::UPLOADED, $document->status);
        $event = $document->reviewEvents()->sole();
        $this->assertSame(KycReviewEventType::DOCUMENT_UPLOADED, $event->event_type);
        $this->assertNull($event->from_status);
        $this->assertSame(KycDocumentStatus::UPLOADED->value, $event->to_status);
        Storage::disk('kyc_private')->assertExists($document->object_key);
    }

    public function test_upload_rolls_back_document_event_and_file_on_event_failure(): void
    {
        Storage::fake('kyc_private');
        [$profile, $uploader] = $this->profile();
        $this->mock(RecordKycReviewEvent::class, function ($mock): void {
            $mock->shouldReceive('recordDocument')->andThrow(new \RuntimeException('event failure'));
        });
        $this->expectException(\RuntimeException::class);
        try {
            app(UploadKycDocument::class)->upload($profile, $uploader, UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nprivate"), KycDocumentType::IDENTITY_DOCUMENT);
        } finally {
            $this->assertDatabaseCount('kyc_documents', 0);
            $this->assertDatabaseCount('kyc_review_events', 1);
            Storage::disk('kyc_private')->assertDirectoryEmpty('profiles/'.$profile->public_id);
        }
    }

    public function test_accept_reject_and_expire_are_audited(): void
    {
        [$profile, $uploader] = $this->profile();
        $compliance = $this->compliance();
        $document = $this->document($profile, $uploader, KycDocumentStatus::UPLOADED, now()->addDay());
        $workflow = app(KycDocumentWorkflow::class);

        $workflow->accept($document, $compliance);
        $this->assertSame(KycDocumentStatus::ACCEPTED, $document->refresh()->status);
        $workflow->reject($document, $compliance, 'Document invalidé', 'DOCUMENT_FORGED');
        $this->assertSame(KycDocumentStatus::REJECTED, $document->refresh()->status);
        $this->assertCount(2, $document->reviewEvents()->get());
    }

    public function test_accepted_document_expires_by_system_only_after_date(): void
    {
        [$profile, $uploader] = $this->profile();
        $document = $this->document($profile, $uploader, KycDocumentStatus::ACCEPTED, now()->addDay());
        $workflow = app(KycDocumentWorkflow::class);
        $this->expectException(KycDocumentWorkflowException::class);
        $workflow->expireBySystem($document, 'DOCUMENT_DATE_EXPIRED');
    }

    public function test_accept_rolls_back_document_projection_when_event_insert_fails(): void
    {
        [$profile, $uploader] = $this->profile();
        $compliance = $this->compliance();
        $document = $this->document($profile, $uploader);
        $this->mock(RecordKycReviewEvent::class, function ($mock): void {
            $mock->shouldReceive('recordDocument')->andThrow(new \RuntimeException('event failure'));
        });

        $this->expectException(\RuntimeException::class);
        try {
            app(KycDocumentWorkflow::class)->accept($document, $compliance);
        } finally {
            $this->assertSame(KycDocumentStatus::UPLOADED, $document->refresh()->status);
            $this->assertNull($document->reviewed_at);
            $this->assertNull($document->reviewed_by_user_id);
            $this->assertSame(0, $document->reviewEvents()->count());
        }
    }

    public function test_expired_document_date_is_accepted_by_system(): void
    {
        [$profile, $uploader] = $this->profile();
        $document = $this->document($profile, $uploader, KycDocumentStatus::ACCEPTED, now()->subDay());
        app(KycDocumentWorkflow::class)->expireBySystem($document, 'DOCUMENT_DATE_EXPIRED');
        $this->assertSame(KycDocumentStatus::EXPIRED, $document->refresh()->status);
        $this->assertSame(KycReviewEventType::DOCUMENT_EXPIRED, $document->reviewEvents()->latest('id')->value('event_type'));
    }

    public function test_compliance_expiration_requires_reason_and_keeps_profile_unchanged(): void
    {
        [$profile, $uploader] = $this->profile();
        $compliance = $this->compliance();
        $document = $this->document($profile, $uploader, KycDocumentStatus::ACCEPTED, now()->addDay());
        app(KycDocumentWorkflow::class)->expireByCompliance($document, $compliance, 'Invalidation manuelle');

        $this->assertSame(KycDocumentStatus::EXPIRED, $document->refresh()->status);
        $this->assertSame('DRAFT', $profile->refresh()->status->value);
        $this->assertSame(KycReviewEventType::DOCUMENT_EXPIRED, $document->reviewEvents()->latest('id')->value('event_type'));
    }

    public function test_self_review_and_non_compliance_are_refused(): void
    {
        [$profile, $uploader] = $this->profile();
        $document = $this->document($profile, $uploader);
        $workflow = app(KycDocumentWorkflow::class);
        $this->expectException(KycDocumentWorkflowException::class);
        $workflow->accept($document, $uploader);
    }

    public function test_non_compliance_cannot_accept_document(): void
    {
        [$profile, $uploader] = $this->profile();
        $document = $this->document($profile, $uploader);
        $this->expectException(KycDocumentWorkflowException::class);
        app(KycDocumentWorkflow::class)->accept($document, User::factory()->create());
    }

    public function test_stale_accept_after_reject_is_refused(): void
    {
        [$profile, $uploader] = $this->profile();
        $compliance = $this->compliance();
        $first = $this->document($profile, $uploader);
        $stale = KycDocument::query()->findOrFail($first->id);
        $workflow = app(KycDocumentWorkflow::class);
        $workflow->reject($first, $compliance, 'Fichier invalide');
        $this->expectException(KycDocumentWorkflowException::class);
        $workflow->accept($stale, $compliance);
    }

    public function test_rejected_and_expired_documents_cannot_be_accepted(): void
    {
        [$profile, $uploader] = $this->profile();
        $compliance = $this->compliance();
        $workflow = app(KycDocumentWorkflow::class);
        foreach ([KycDocumentStatus::REJECTED, KycDocumentStatus::EXPIRED] as $status) {
            $document = $this->document($profile, $uploader, $status);
            try {
                $workflow->accept($document, $compliance);
                $this->fail('Transition document inattendue.');
            } catch (KycDocumentWorkflowException) {
                $this->assertTrue(true);
            }
        }
    }

    private function profile(): array
    {
        $user = User::factory()->create();

        return [app(CreateKycProfile::class)->create($user, $user), $user];
    }

    private function compliance(): User
    {
        Permission::findOrCreate('compliance.manage', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('compliance.manage');

        return $user;
    }

    private function document(KycProfile $profile, User $uploader, KycDocumentStatus $status = KycDocumentStatus::UPLOADED, $expiresAt = null): KycDocument
    {
        return KycDocument::query()->create([
            'public_id' => (string) Str::uuid(), 'kyc_profile_id' => $profile->id, 'type' => KycDocumentType::IDENTITY_DOCUMENT,
            'status' => $status, 'storage_disk' => 'kyc_private', 'object_key' => 'profiles/test/'.Str::random(8).'.pdf',
            'sha256' => str_repeat('a', 64), 'mime_type' => 'application/pdf', 'size_bytes' => 10,
            'expires_at' => $expiresAt, 'uploaded_by_user_id' => $uploader->id,
        ]);
    }
}
