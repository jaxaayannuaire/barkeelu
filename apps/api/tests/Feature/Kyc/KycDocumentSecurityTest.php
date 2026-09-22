<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentType;
use App\Models\User;
use App\Services\Kyc\CreateKycProfile;
use App\Services\Kyc\KycFileInspector;
use App\Services\Kyc\UploadKycDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KycDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_size_is_5120_kb_and_override_is_dynamic(): void
    {
        $this->assertSame(5120, config('kyc.upload.max_size_kb'));
        config()->set('kyc.upload.max_size_kb', 1024);
        try {
            app(KycFileInspector::class)->inspect(UploadedFile::fake()->create('large.pdf', 1025, 'application/pdf'));
            $this->fail('Limite taille non appliquée.');
        } catch (\Throwable $exception) {
            $this->assertSame('KYC_FILE_TOO_LARGE', $exception->errorCode);
        }
    }

    public function test_five_megabytes_is_allowed_and_over_limit_is_rejected(): void
    {
        $content = "%PDF-1.4\n".str_repeat('x', (5 * 1024 * 1024) - 9);
        $this->assertSame('pdf', app(KycFileInspector::class)->inspect(UploadedFile::fake()->createWithContent('five.pdf', $content)));
        config()->set('kyc.upload.max_size_kb', 5120);
        try {
            app(KycFileInspector::class)->inspect(UploadedFile::fake()->createWithContent('over.pdf', $content.'x'));
            $this->fail('Fichier supérieur à la limite accepté.');
        } catch (\Throwable $exception) {
            $this->assertSame('KYC_FILE_TOO_LARGE', $exception->errorCode);
        }
    }

    public function test_valid_pdf_is_streamed_and_client_name_never_enters_object_key(): void
    {
        Storage::fake('kyc_private');
        [$profile, $user] = $this->profile();
        $document = app(UploadKycDocument::class)->upload($profile, $user, UploadedFile::fake()->createWithContent('../../secret.pdf', "%PDF-1.4\nvalid"), KycDocumentType::IDENTITY_DOCUMENT);

        $this->assertStringStartsWith('profiles/'.$profile->public_id.'/', $document->object_key);
        $this->assertStringEndsWith('.pdf', $document->object_key);
        $this->assertStringNotContainsString('secret', $document->object_key);
        Storage::disk('kyc_private')->assertExists($document->object_key);
    }

    public function test_disguised_files_are_rejected(): void
    {
        $inspector = app(KycFileInspector::class);
        foreach ([
            ['fake.pdf', 'not a pdf', 'application/pdf'],
            ['fake.jpg', 'not a jpeg', 'image/jpeg'],
            ['fake.png', 'not a png', 'image/png'],
            ['shell.php.pdf', '<?php echo 1;', 'application/pdf'],
        ] as [$name, $content, $mime]) {
            try {
                $inspector->inspect(UploadedFile::fake()->createWithContent($name, $content));
                $this->fail('Signature invalide acceptée : '.$name);
            } catch (\Throwable $exception) {
                $this->assertSame('KYC_FILE_SIGNATURE_INVALID', $exception->errorCode);
            }
        }
    }

    public function test_download_requires_subject_access_and_never_exposes_object_key(): void
    {
        Storage::fake('kyc_private');
        [$profile, $owner] = $this->profile();
        $document = app(UploadKycDocument::class)->upload($profile, $owner, UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nprivate"), KycDocumentType::IDENTITY_DOCUMENT);
        Storage::disk('kyc_private')->put($document->object_key, "%PDF-1.4\nprivate");

        $this->getJson('/api/v1/kyc/documents/'.$document->public_id.'/download')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/kyc/documents/'.$document->public_id.'/download')->assertForbidden();
        $response = $this->actingAs($owner)->get('/api/v1/kyc/documents/'.$document->public_id.'/download');
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition');
        $this->assertStringNotContainsString($document->object_key, (string) $response->headers->get('Content-Disposition'));
        $this->getJson('/api/v1/kyc/documents/'.$document->id.'/download')->assertNotFound();
    }

    public function test_compliance_can_download_and_missing_file_returns_safe_404(): void
    {
        Storage::fake('kyc_private');
        [$profile, $owner] = $this->profile();
        $document = app(UploadKycDocument::class)->upload($profile, $owner, UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nprivate"), KycDocumentType::IDENTITY_DOCUMENT);
        $compliance = $this->compliance();
        Storage::disk('kyc_private')->delete($document->object_key);
        $this->actingAs($compliance)->get('/api/v1/kyc/documents/'.$document->public_id.'/download')->assertNotFound()->assertJsonMissing(['object_key' => $document->object_key]);
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
}
