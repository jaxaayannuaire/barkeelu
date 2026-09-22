<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentAssetRole;
use App\Enums\KycDocumentAssetStatus;
use App\Enums\KycDocumentType;
use App\Models\KycDocument;
use App\Models\KycDocumentAsset;
use App\Models\User;
use App\Services\Kyc\CreateKycProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class KycDocumentAssetFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_asset_and_relations_are_cast(): void
    {
        [$document] = $this->document();
        $asset = KycDocumentAsset::query()->create($this->attributes($document));
        $this->assertSame(KycDocumentAssetRole::PREVIEW, $asset->role);
        $this->assertSame(KycDocumentAssetStatus::PENDING, $asset->status);
        $this->assertTrue($document->refresh()->assets->contains($asset));
        $this->assertTrue($asset->document->is($document));
    }

    public function test_logical_duplicate_is_rejected_but_processor_or_version_change_is_allowed(): void
    {
        [$document] = $this->document();
        KycDocumentAsset::query()->create($this->attributes($document));
        $this->assertRejected(fn () => KycDocumentAsset::query()->create($this->attributes($document)));
        KycDocumentAsset::query()->create($this->attributes($document, ['processor_version' => 'v2']));
        KycDocumentAsset::query()->create($this->attributes($document, ['processor' => 'other']));
        $this->assertDatabaseCount('kyc_document_assets', 3);
    }

    public function test_ready_requires_complete_storage_metadata(): void
    {
        [$document] = $this->document();
        foreach (['storage_disk', 'object_key', 'mime_type', 'size_bytes', 'sha256'] as $field) {
            $attributes = $this->attributes($document, ['status' => 'READY']);
            $attributes[$field] = null;
            $this->assertRejected(fn () => KycDocumentAsset::query()->create($attributes));
        }
        $asset = KycDocumentAsset::query()->create($this->attributes($document, [
            'status' => 'READY', 'storage_disk' => 'kyc_private', 'object_key' => 'derivatives/test.webp',
            'mime_type' => 'image/webp', 'size_bytes' => 20, 'sha256' => str_repeat('a', 64),
        ]));
        $this->assertSame(KycDocumentAssetStatus::READY, $asset->status);
    }

    public function test_failed_without_storage_is_allowed(): void
    {
        [$document] = $this->document();
        $asset = KycDocumentAsset::query()->create($this->attributes($document, ['status' => 'FAILED']));
        $this->assertSame(KycDocumentAssetStatus::FAILED, $asset->status);
    }

    public function test_postgresql_checks_reject_invalid_role_status_hash_size_and_dimensions(): void
    {
        [$document] = $this->document();
        foreach ([
            ['role' => 'MASTER'], ['status' => 'BROKEN'], ['sha256' => 'bad'],
            ['size_bytes' => 0], ['size_bytes' => -1], ['width' => 0], ['height' => -1],
        ] as $override) {
            $this->assertRejected(fn () => DB::table('kyc_document_assets')->insert(array_merge($this->attributes($document), $override)));
        }
    }

    public function test_document_delete_is_restricted_by_asset_foreign_key(): void
    {
        [$document] = $this->document();
        KycDocumentAsset::query()->create($this->attributes($document));
        $this->expectException(QueryException::class);
        $document->delete();
    }

    public function test_migration_roundtrip_recreates_asset_table(): void
    {
        $migration = require database_path('migrations/2026_09_22_030000_create_kyc_document_assets_table.php');
        $migration->down();
        $this->assertFalse(\Schema::hasTable('kyc_document_assets'));
        $migration->up();
        $this->assertTrue(\Schema::hasTable('kyc_document_assets'));
    }

    private function document(): array
    {
        $user = User::factory()->create();
        $profile = app(CreateKycProfile::class)->create($user, $user);
        $document = KycDocument::query()->create([
            'public_id' => (string) Str::uuid(), 'kyc_profile_id' => $profile->id,
            'type' => KycDocumentType::IDENTITY_DOCUMENT, 'status' => 'UPLOADED',
            'storage_disk' => 'kyc_private', 'object_key' => 'profiles/test/master.pdf',
            'sha256' => str_repeat('b', 64), 'mime_type' => 'application/pdf', 'size_bytes' => 10,
            'uploaded_by_user_id' => $user->id,
        ]);

        return [$document, $profile];
    }

    private function attributes(KycDocument $document, array $overrides = []): array
    {
        return array_merge([
            'public_id' => (string) Str::uuid(), 'kyc_document_id' => $document->id,
            'role' => 'PREVIEW', 'status' => 'PENDING', 'processor' => 'image_webp', 'processor_version' => 'v1',
            'storage_disk' => null, 'object_key' => null, 'mime_type' => null, 'size_bytes' => null,
            'sha256' => null, 'width' => null, 'height' => null,
        ], $overrides);
    }

    private function assertRejected(callable $operation): void
    {
        DB::beginTransaction();
        try {
            $operation();
            $this->fail('Contrainte PostgreSQL non appliquée.');
        } catch (QueryException) {
            $this->assertTrue(true);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
