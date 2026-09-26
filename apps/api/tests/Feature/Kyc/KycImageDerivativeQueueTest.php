<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentAssetRole;
use App\Enums\KycDocumentAssetStatus;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Jobs\Kyc\GenerateKycDocumentDerivativeJob;
use App\Models\KycDocument;
use App\Models\KycDocumentAsset;
use App\Models\User;
use App\Services\Kyc\CreateKycProfile;
use App\Services\Kyc\KycImageDerivativeProcessor;
use App\Services\Kyc\KycImageDerivativeScheduler;
use App\Services\Kyc\UploadKycDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KycImageDerivativeQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_jpeg_schedules_one_optimized_pending_asset_on_dedicated_queue(): void
    {
        Queue::fake();
        [$document] = $this->imageDocument();

        $asset = app(KycImageDerivativeScheduler::class)->schedule($document);

        $this->assertNotNull($asset);
        $this->assertSame(KycDocumentAssetRole::OPTIMIZED, $asset->role);
        $this->assertSame(KycDocumentAssetStatus::PENDING, $asset->status);
        $this->assertSame(KycImageDerivativeProcessor::PROCESSOR, $asset->processor);
        $this->assertSame(KycImageDerivativeProcessor::PROCESSOR_VERSION, $asset->processor_version);
        $this->assertDatabaseCount('kyc_document_assets', 1);
        Queue::assertPushedOn('kyc-media', GenerateKycDocumentDerivativeJob::class);
    }

    public function test_png_is_eligible_but_pdf_is_not(): void
    {
        Queue::fake();
        [$png] = $this->imageDocument('image/png', $this->imageBytes('png'));
        [$pdf] = $this->document('application/pdf', 'profiles/test/master.pdf', '%PDF-1.4\nprivate');

        $this->assertNotNull(app(KycImageDerivativeScheduler::class)->schedule($png));
        $this->assertNull(app(KycImageDerivativeScheduler::class)->schedule($pdf));
        $this->assertDatabaseCount('kyc_document_assets', 1);
        Queue::assertPushed(GenerateKycDocumentDerivativeJob::class, 1);
    }

    public function test_disabled_optimization_does_not_create_asset_or_job(): void
    {
        Queue::fake();
        config()->set('kyc.image.optimization_enabled', false);
        [$document] = $this->imageDocument();

        $this->assertNull(app(KycImageDerivativeScheduler::class)->schedule($document));
        $this->assertDatabaseCount('kyc_document_assets', 0);
        Queue::assertNothingPushed();
    }

    public function test_repeated_schedule_reuses_asset_and_ready_asset_is_not_dispatched(): void
    {
        Queue::fake();
        [$document] = $this->imageDocument();
        $scheduler = app(KycImageDerivativeScheduler::class);

        $first = $scheduler->schedule($document);
        $second = $scheduler->schedule($document);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->public_id, $second->public_id);
        $this->assertDatabaseCount('kyc_document_assets', 1);
        Queue::assertPushed(GenerateKycDocumentDerivativeJob::class, 1);

        $first->update([
            'status' => KycDocumentAssetStatus::READY,
            'storage_disk' => 'kyc_private',
            'object_key' => 'ready.webp',
            'mime_type' => 'image/webp',
            'size_bytes' => 10,
            'sha256' => str_repeat('a', 64),
            'width' => 10,
            'height' => 10,
        ]);
        $scheduler->schedule($document);
        Queue::assertPushed(GenerateKycDocumentDerivativeJob::class, 1);
    }

    public function test_failed_asset_is_rearmed_without_new_logical_asset(): void
    {
        Queue::fake();
        [$document] = $this->imageDocument();
        $asset = KycDocumentAsset::query()->create([
            'public_id' => (string) Str::uuid(),
            'kyc_document_id' => $document->id,
            'role' => KycDocumentAssetRole::OPTIMIZED,
            'status' => KycDocumentAssetStatus::FAILED,
            'processor' => KycImageDerivativeProcessor::PROCESSOR,
            'processor_version' => KycImageDerivativeProcessor::PROCESSOR_VERSION,
            'storage_disk' => 'kyc_private',
            'object_key' => 'stale.webp',
            'mime_type' => 'image/webp',
            'size_bytes' => 10,
            'sha256' => str_repeat('a', 64),
            'width' => 10,
            'height' => 10,
            'processed_at' => now(),
        ]);

        $rearmed = app(KycImageDerivativeScheduler::class)->schedule($document);

        $this->assertSame($asset->id, $rearmed->id);
        $this->assertSame($asset->public_id, $rearmed->public_id);
        $this->assertSame(KycDocumentAssetStatus::PENDING, $rearmed->refresh()->status);
        $this->assertNull($rearmed->storage_disk);
        $this->assertNull($rearmed->object_key);
        $this->assertNull($rearmed->processed_at);
        $this->assertDatabaseCount('kyc_document_assets', 1);
        Queue::assertPushed(GenerateKycDocumentDerivativeJob::class, 1);
    }

    public function test_upload_survives_post_commit_scheduler_failure(): void
    {
        Storage::fake('kyc_private');
        $this->mock(KycImageDerivativeScheduler::class, function ($mock): void {
            $mock->shouldReceive('schedule')->andThrow(new \RuntimeException('queue unavailable'));
        });
        [$profile, $user] = $this->profile();
        $file = UploadedFile::fake()->createWithContent('id.jpg', $this->imageBytes('jpeg'));

        $document = app(UploadKycDocument::class)->upload($profile, $user, $file, KycDocumentType::IDENTITY_DOCUMENT);

        $this->assertDatabaseHas('kyc_documents', ['id' => $document->id, 'status' => KycDocumentStatus::UPLOADED->value]);
        $this->assertDatabaseHas('kyc_review_events', ['kyc_document_id' => $document->id, 'event_type' => 'DOCUMENT_UPLOADED']);
        Storage::disk('kyc_private')->assertExists($document->object_key);
    }

    public function test_webp_upload_preserves_master_bytes_and_schedules_optimized_asset(): void
    {
        Queue::fake();
        Storage::fake('kyc_private');
        [$profile, $user] = $this->profile();
        $bytes = $this->imageBytes('webp');

        $file = $this->uploadedFile('arbitrary.client-extension', $bytes);
        $document = app(UploadKycDocument::class)->upload($profile, $user, $file, KycDocumentType::IDENTITY_DOCUMENT);

        $this->assertSame('image/webp', $document->mime_type);
        $this->assertSame('webp', $document->metadata['extension']);
        $this->assertStringEndsWith('.webp', $document->object_key);
        $this->assertSame(hash('sha256', $bytes), $document->sha256);
        $this->assertSame(strlen($bytes), $document->size_bytes);
        $this->assertSame($bytes, Storage::disk('kyc_private')->get($document->object_key));
        $this->assertDatabaseHas('kyc_document_assets', [
            'kyc_document_id' => $document->id,
            'role' => KycDocumentAssetRole::OPTIMIZED->value,
            'status' => KycDocumentAssetStatus::PENDING->value,
        ]);
        Queue::assertPushedOn('kyc-media', GenerateKycDocumentDerivativeJob::class);
    }

    public function test_job_generates_ready_asset_and_preserves_master(): void
    {
        Storage::fake('kyc_private');
        [$document, $master] = $this->imageDocument();
        $asset = $this->pendingAsset($document);

        (new GenerateKycDocumentDerivativeJob($asset->id))->handle(app(KycImageDerivativeProcessor::class));

        $asset = $asset->refresh();
        $this->assertSame(KycDocumentAssetStatus::READY, $asset->status);
        $this->assertSame('image/webp', $asset->mime_type);
        $this->assertNotNull($asset->processed_at);
        $this->assertMatchesRegularExpression('/^profiles\/[^\/]+\/derivatives\/[^\/]+\/[^\/]+\.webp$/', $asset->object_key);
        $this->assertGreaterThan(0, $asset->size_bytes);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $asset->sha256);
        Storage::disk('kyc_private')->assertExists($asset->object_key);
        $this->assertSame($master, Storage::disk('kyc_private')->get($document->object_key));
    }

    public function test_ready_job_is_idempotent_and_unique_id_is_asset_stable(): void
    {
        Storage::fake('kyc_private');
        [$document] = $this->imageDocument();
        $asset = $this->pendingAsset($document);
        $job = new GenerateKycDocumentDerivativeJob($asset->id);
        $job->handle(app(KycImageDerivativeProcessor::class));
        $ready = $asset->refresh();
        $job->handle(app(KycImageDerivativeProcessor::class));

        $this->assertSame('kyc-image-derivative:'.$asset->id, $job->uniqueId());
        $this->assertSame(KycDocumentAssetStatus::READY, $ready->status);
        $this->assertSame($ready->object_key, $asset->refresh()->object_key);
        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120, 300], $job->backoff);
        $this->assertSame(180, $job->timeout);
        $this->assertSame(3600, $job->uniqueFor);
    }

    public function test_failed_job_marks_asset_failed_without_removing_master(): void
    {
        Storage::fake('kyc_private');
        [$document, $master] = $this->imageDocument('image/jpeg', 'not-an-image');
        $asset = $this->pendingAsset($document);

        try {
            (new GenerateKycDocumentDerivativeJob($asset->id))->handle(app(KycImageDerivativeProcessor::class));
            $this->fail('Job accepted missing master.');
        } catch (\Throwable $exception) {
            (new GenerateKycDocumentDerivativeJob($asset->id))->failed($exception);
        }

        $this->assertSame(KycDocumentAssetStatus::FAILED, $asset->refresh()->status);
        $this->assertNull($asset->storage_disk);
        $this->assertNull($asset->object_key);
        $this->assertSame($master, Storage::disk('kyc_private')->get($document->object_key));
    }

    private function imageDocument(string $mime = 'image/jpeg', ?string $bytes = null): array
    {
        return $this->document($mime, 'profiles/test/master.'.match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        }, $bytes ?? $this->imageBytes(match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpeg',
        }));
    }

    private function document(string $mime, string $key, string $bytes): array
    {
        Storage::disk('kyc_private')->put($key, $bytes);
        $user = User::factory()->create();
        $profile = app(CreateKycProfile::class)->create($user, $user);
        $document = KycDocument::query()->create([
            'public_id' => (string) Str::uuid(),
            'kyc_profile_id' => $profile->id,
            'type' => KycDocumentType::IDENTITY_DOCUMENT,
            'status' => KycDocumentStatus::UPLOADED,
            'storage_disk' => 'kyc_private',
            'object_key' => $key,
            'sha256' => hash('sha256', $bytes),
            'mime_type' => $mime,
            'size_bytes' => strlen($bytes),
            'uploaded_by_user_id' => $user->id,
        ]);

        return [$document, $bytes];
    }

    private function pendingAsset(KycDocument $document): KycDocumentAsset
    {
        return KycDocumentAsset::query()->create([
            'public_id' => (string) Str::uuid(),
            'kyc_document_id' => $document->id,
            'role' => KycDocumentAssetRole::OPTIMIZED,
            'status' => KycDocumentAssetStatus::PENDING,
            'processor' => KycImageDerivativeProcessor::PROCESSOR,
            'processor_version' => KycImageDerivativeProcessor::PROCESSOR_VERSION,
        ]);
    }

    private function profile(): array
    {
        $user = User::factory()->create();

        return [app(CreateKycProfile::class)->create($user, $user), $user];
    }

    private function imageBytes(string $format): string
    {
        $image = imagecreatetruecolor(40, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 80, 140));
        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 88),
            default => imagejpeg($image, null, 92),
        };
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function uploadedFile(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'barkeelu-upload-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, UPLOAD_ERR_OK, true);
    }
}
