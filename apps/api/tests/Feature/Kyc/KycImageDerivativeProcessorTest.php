<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentType;
use App\Enums\KycImageProcessingErrorCode;
use App\Exceptions\KycImageProcessingException;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\Kyc\CreateKycProfile;
use App\Services\Kyc\KycImageDerivativeProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KycImageDerivativeProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_jpeg_is_resized_to_webp_without_upscaling_or_master_mutation(): void
    {
        Storage::fake('kyc_private');
        config()->set('kyc.image.max_dimension', 2400);
        [$document, $master] = $this->document($this->imageBytes('jpeg', 2600, 1300), 'image/jpeg');

        $asset = app(KycImageDerivativeProcessor::class)->generate($document, 'kyc_private', 'derivatives/'.$document->public_id.'.webp');

        $output = Storage::disk('kyc_private')->get($asset->objectKey);
        $this->assertSame('image/webp', $asset->mimeType);
        $this->assertSame(2400, $asset->width);
        $this->assertSame(1200, $asset->height);
        $this->assertStringStartsWith('RIFF', $output);
        $this->assertSame('WEBP', substr($output, 8, 4));
        $this->assertSame($master, Storage::disk('kyc_private')->get($document->object_key));
        $this->assertSame(hash('sha256', $output), $asset->sha256);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $asset->sha256);
    }

    public function test_png_is_converted_and_small_images_are_not_upscaled(): void
    {
        Storage::fake('kyc_private');
        config()->set('kyc.image.max_dimension', 2400);
        [$document] = $this->document($this->imageBytes('png', 120, 80), 'image/png');

        $asset = app(KycImageDerivativeProcessor::class)->generate($document, 'kyc_private', 'derivatives/small.webp');

        $this->assertSame([120, 80], [$asset->width, $asset->height]);
        $this->assertSame('image/webp', $asset->mimeType);
    }

    public function test_webp_is_resized_to_webp_without_master_mutation(): void
    {
        Storage::fake('kyc_private');
        config()->set('kyc.image.max_dimension', 2400);
        [$document, $master] = $this->document($this->imageBytes('webp', 2600, 1300), 'image/webp');

        $asset = app(KycImageDerivativeProcessor::class)->generate($document, 'kyc_private', 'derivatives/webp.webp');
        $output = Storage::disk('kyc_private')->get($asset->objectKey);

        $this->assertSame([2400, 1200], [$asset->width, $asset->height]);
        $this->assertSame('image/webp', $asset->mimeType);
        $this->assertSame('RIFF', substr($output, 0, 4));
        $this->assertSame('WEBP', substr($output, 8, 4));
        $this->assertSame($master, Storage::disk('kyc_private')->get($document->object_key));
        $this->assertSame(hash('sha256', $output), $asset->sha256);
    }

    public function test_jpeg_exif_orientation_is_applied(): void
    {
        Storage::fake('kyc_private');
        config()->set('kyc.image.max_dimension', 2400);
        [$document] = $this->document($this->withExifOrientation($this->imageBytes('jpeg', 4, 2), 6), 'image/jpeg');

        $asset = app(KycImageDerivativeProcessor::class)->generate($document, 'kyc_private', 'derivatives/oriented.webp');

        $this->assertSame([2, 4], [$asset->width, $asset->height]);
    }

    public function test_pixel_limit_is_checked_before_decode(): void
    {
        Storage::fake('kyc_private');
        config()->set('kyc.image.max_input_pixels', 100);
        [$document] = $this->document($this->imageBytes('jpeg', 20, 20), 'image/jpeg');

        try {
            app(KycImageDerivativeProcessor::class)->generate($document, 'kyc_private', 'derivatives/too-large.webp');
            $this->fail('Pixel limit was not enforced.');
        } catch (KycImageProcessingException $exception) {
            $this->assertSame(KycImageProcessingErrorCode::PIXEL_LIMIT_EXCEEDED, $exception->errorCode());
        }
    }

    public function test_webp_quality_is_read_from_configuration(): void
    {
        Storage::fake('kyc_private');
        [$document] = $this->document($this->imageBytes('jpeg', 800, 600), 'image/jpeg');
        $processor = app(KycImageDerivativeProcessor::class);

        config()->set('kyc.image.webp_quality', 40);
        $low = $processor->generate($document, 'kyc_private', 'derivatives/low.webp');
        config()->set('kyc.image.webp_quality', 95);
        $high = $processor->generate($document, 'kyc_private', 'derivatives/high.webp');

        $this->assertNotSame($low->sha256, $high->sha256);
    }

    public function test_unavailable_destination_storage_is_controlled(): void
    {
        Storage::fake('kyc_private');
        [$document] = $this->document($this->imageBytes('jpeg', 10, 10), 'image/jpeg');

        try {
            app(KycImageDerivativeProcessor::class)->generate($document, 'missing_kyc_disk', 'derivatives/fail.webp');
            $this->fail('Storage failure was not reported.');
        } catch (KycImageProcessingException $exception) {
            $this->assertSame(KycImageProcessingErrorCode::STORAGE_FAILED, $exception->errorCode());
        }
    }

    public function test_pdf_and_missing_master_are_rejected_without_disclosing_storage_details(): void
    {
        Storage::fake('kyc_private');
        [$pdf] = $this->document('pdf', 'application/pdf');
        try {
            app(KycImageDerivativeProcessor::class)->generate($pdf, 'kyc_private', 'derivatives/pdf.webp');
            $this->fail('PDF was accepted.');
        } catch (KycImageProcessingException $exception) {
            $this->assertSame(KycImageProcessingErrorCode::MASTER_UNSUPPORTED, $exception->errorCode());
        }

        [$missing] = $this->document($this->imageBytes('jpeg', 10, 10), 'image/jpeg');
        Storage::disk('kyc_private')->delete($missing->object_key);
        try {
            app(KycImageDerivativeProcessor::class)->generate($missing, 'kyc_private', 'derivatives/missing.webp');
            $this->fail('Missing master was accepted.');
        } catch (KycImageProcessingException $exception) {
            $this->assertSame(KycImageProcessingErrorCode::MASTER_NOT_FOUND, $exception->errorCode());
            $this->assertStringNotContainsString($missing->object_key, $exception->getMessage());
        }
    }

    public function test_destination_traversal_and_invalid_image_are_rejected(): void
    {
        Storage::fake('kyc_private');
        [$document] = $this->document('not-an-image', 'image/jpeg');

        try {
            app(KycImageDerivativeProcessor::class)->generate($document, 'kyc_private', '../escape.webp');
            $this->fail('Traversal destination was accepted.');
        } catch (KycImageProcessingException $exception) {
            $this->assertSame(KycImageProcessingErrorCode::STORAGE_FAILED, $exception->errorCode());
        }

        try {
            app(KycImageDerivativeProcessor::class)->generate($document, 'kyc_private', 'derivatives/invalid.webp');
            $this->fail('Invalid image was accepted.');
        } catch (KycImageProcessingException $exception) {
            $this->assertSame(KycImageProcessingErrorCode::INVALID, $exception->errorCode());
        }
    }

    public function test_processing_disabled_is_explicit(): void
    {
        Storage::fake('kyc_private');
        config()->set('kyc.image.optimization_enabled', false);
        [$document] = $this->document($this->imageBytes('jpeg', 10, 10), 'image/jpeg');

        try {
            app(KycImageDerivativeProcessor::class)->generate($document, 'kyc_private', 'derivatives/disabled.webp');
            $this->fail('Disabled processing was accepted.');
        } catch (KycImageProcessingException $exception) {
            $this->assertSame(KycImageProcessingErrorCode::PROCESSING_DISABLED, $exception->errorCode());
        }
    }

    private function document(string $bytes, string $mime): array
    {
        $user = User::factory()->create();
        $profile = app(CreateKycProfile::class)->create($user, $user);
        $key = 'profiles/'.$profile->public_id.'/master.'.match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        Storage::disk('kyc_private')->put($key, $bytes);
        $document = KycDocument::query()->create([
            'public_id' => (string) Str::uuid(),
            'kyc_profile_id' => $profile->id,
            'type' => KycDocumentType::IDENTITY_DOCUMENT,
            'status' => 'UPLOADED',
            'storage_disk' => 'kyc_private',
            'object_key' => $key,
            'sha256' => hash('sha256', $bytes),
            'mime_type' => $mime,
            'size_bytes' => strlen($bytes),
            'uploaded_by_user_id' => $user->id,
        ]);

        return [$document, $bytes];
    }

    private function imageBytes(string $format, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
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

    private function withExifOrientation(string $jpeg, int $orientation): string
    {
        $tiff = "II*\0"."\x08\0\0\0"."\x01\0"."\x12\x01"."\x03\0"."\x01\0\0\0".chr($orientation)."\0\0\0"."\0\0\0\0";
        $app1 = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
