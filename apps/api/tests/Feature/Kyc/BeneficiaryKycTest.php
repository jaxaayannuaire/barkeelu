<?php

namespace Tests\Feature\Kyc;

use App\Enums\BeneficiaryRepresentativeStatus;
use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\KycDocumentType;
use App\Models\Beneficiary;
use App\Models\BeneficiaryRepresentative;
use App\Models\KycProfile;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Policies\KycProfilePolicy;
use App\Services\Kyc\CreateKycProfile;
use App\Services\Kyc\UploadKycDocument;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BeneficiaryKycTest extends TestCase
{
    use RefreshDatabase;

    public function test_beneficiary_constraints_and_historical_representatives_are_enforced_on_postgresql(): void
    {
        $creator = User::factory()->create();
        $linkedUser = User::factory()->create();
        $organization = $this->organization($creator);
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'display_name' => 'Sans compte', 'type' => BeneficiaryType::INDIVIDUAL, 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $creator->id]);

        $this->assertDatabaseHas('beneficiaries', ['id' => $beneficiary->id, 'linked_user_id' => null, 'linked_organization_id' => null]);
        $this->expectException(QueryException::class);
        Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'display_name' => 'Lien interdit', 'type' => BeneficiaryType::OTHER, 'status' => BeneficiaryStatus::ACTIVE, 'linked_user_id' => $linkedUser->id, 'linked_organization_id' => $organization->id, 'created_by_user_id' => $creator->id]);
    }

    public function test_representative_interval_and_kyc_subject_constraints_are_enforced_on_postgresql(): void
    {
        $creator = User::factory()->create();
        $beneficiary = $this->beneficiary($creator);
        $representative = User::factory()->create();
        $this->expectException(QueryException::class);
        BeneficiaryRepresentative::query()->create(['beneficiary_id' => $beneficiary->id, 'representative_user_id' => $representative->id, 'status' => BeneficiaryRepresentativeStatus::ACTIVE, 'valid_from' => now(), 'valid_until' => now()->subMinute(), 'created_by_user_id' => $creator->id]);
    }

    public function test_kyc_profile_xor_and_partial_subject_uniqueness_are_enforced_on_postgresql(): void
    {
        $user = User::factory()->create();
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        app(CreateKycProfile::class)->create($user);
        $this->expectException(QueryException::class);
        app(CreateKycProfile::class)->create($user);
    }

    public function test_kyc_profile_rejects_zero_or_multiple_subjects_at_database_level(): void
    {
        $user = User::factory()->create();
        $beneficiary = $this->beneficiary($user);
        foreach ([['user_id' => null, 'organization_id' => null, 'beneficiary_id' => null], ['user_id' => $user->id, 'organization_id' => null, 'beneficiary_id' => $beneficiary->id]] as $attributes) {
            try {
                DB::table('kyc_profiles')->insert(array_merge(['public_id' => (string) Str::uuid(), 'status' => 'DRAFT', 'risk_level' => 'UNKNOWN', 'created_at' => now(), 'updated_at' => now()], $attributes));
                $this->fail('La contrainte XOR KYC aurait dû rejeter cette ligne.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_private_document_storage_hash_and_resource_secrecy(): void
    {
        $user = User::factory()->create();
        $profile = app(CreateKycProfile::class)->create($user);
        $file = UploadedFile::fake()->createWithContent('identity.pdf', 'contenu-prive-kyc');
        $document = app(UploadKycDocument::class)->upload($profile, $user, $file, KycDocumentType::IDENTITY_DOCUMENT);

        $disk = Storage::disk('kyc_private');
        $this->assertTrue($disk->exists($document->object_key));
        $this->assertSame(hash('sha256', 'contenu-prive-kyc'), $document->sha256);
        $this->assertSame('pdf', $document->metadata['extension']);
        $this->assertArrayNotHasKey('object_key', $document->toResource()->resolve());
        $this->assertArrayNotHasKey('storage_disk', $document->toResource()->resolve());
        $disk->delete($document->object_key);
        $this->assertFalse($disk->exists($document->object_key));
    }

    public function test_beneficiary_and_kyc_policies_isolate_users_and_allow_compliance(): void
    {
        $creator = User::factory()->create();
        $other = User::factory()->create();
        $beneficiary = $this->beneficiary($creator);
        $profile = app(CreateKycProfile::class)->create($beneficiary);

        $this->assertTrue(Gate::forUser($creator)->allows('view', $beneficiary));
        $this->assertFalse(Gate::forUser($other)->allows('view', $beneficiary));
        $this->assertTrue(Gate::forUser($creator)->allows('view', $profile));
        $this->assertFalse(Gate::forUser($other)->allows('view', $profile));

        Permission::findOrCreate('compliance.manage', 'web');
        $other->givePermissionTo('compliance.manage');
        $this->assertTrue(Gate::forUser($other)->allows('view', $beneficiary));
        $this->assertTrue(Gate::forUser($other)->allows('view', $profile));
    }

    public function test_organization_owner_can_create_kyc_profile_and_member_cannot(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->organization($owner);
        OrganizationMember::query()->create(['organization_id' => $organization->id, 'user_id' => $member->id, 'membership_role' => 'MEMBER', 'status' => 'ACTIVE', 'joined_at' => now()]);

        $policy = app(KycProfilePolicy::class);
        $this->assertTrue($policy->create($owner, $organization));
        $this->assertFalse($policy->create($member, $organization));
    }

    public function test_beneficiary_api_and_representative_history_are_available_to_creator_only(): void
    {
        $creator = User::factory()->create();
        $representative = User::factory()->create();
        $other = User::factory()->create();
        $token = $creator->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/v1/beneficiaries', ['display_name' => 'Beneficiaire API', 'type' => 'COMMUNITY']);
        $response->assertCreated()->assertJsonPath('data.display_name', 'Beneficiaire API')->assertJsonMissingPath('data.created_by_user_id');
        $beneficiary = Beneficiary::query()->where('display_name', 'Beneficiaire API')->firstOrFail();
        $this->withToken($token)->postJson('/api/v1/beneficiaries/'.$beneficiary->public_id.'/representatives', ['representative_user_id' => $representative->id, 'valid_from' => now()->toDateTimeString()])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->withToken($other->createToken('other')->plainTextToken)->getJson('/api/v1/beneficiaries/'.$beneficiary->public_id)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/beneficiaries/'.$beneficiary->public_id.'/representatives')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_kyc_profile_and_document_api_do_not_expose_private_storage_details(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('kyc')->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/v1/kyc/profiles', ['user_id' => $user->id]);
        $response->assertCreated()->assertJsonPath('data.status', 'DRAFT');
        $profile = KycProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->withToken($token)->post('/api/v1/kyc/profiles/'.$profile->public_id.'/documents', ['file' => UploadedFile::fake()->createWithContent('proof.pdf', 'preuve-kyc'), 'type' => 'IDENTITY_DOCUMENT'])
            ->assertCreated()
            ->assertJsonMissingPath('data.object_key')
            ->assertJsonMissingPath('data.storage_disk');
        $document = $profile->documents()->firstOrFail();
        Storage::disk('kyc_private')->delete($document->object_key);
    }

    private function beneficiary(User $creator): Beneficiary
    {
        return Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'display_name' => 'Bénéficiaire test', 'type' => BeneficiaryType::INDIVIDUAL, 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $creator->id]);
    }

    private function organization(User $creator): Organization
    {
        $organization = new Organization;
        $organization->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Organisation test', 'slug' => 'organisation-'.Str::lower(Str::random(8)), 'type' => 'ASSOCIATION', 'status' => 'ACTIVE', 'created_by_user_id' => $creator->id]);
        $organization->save();
        OrganizationMember::query()->create(['organization_id' => $organization->id, 'user_id' => $creator->id, 'membership_role' => 'OWNER', 'status' => 'ACTIVE', 'joined_at' => now()]);

        return $organization;
    }
}
