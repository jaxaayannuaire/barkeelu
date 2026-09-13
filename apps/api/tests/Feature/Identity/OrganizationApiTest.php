<?php

namespace Tests\Feature\Identity;

use App\Enums\OrganizationMembershipRole;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Organizations\CreateOrganization;
use Database\Seeders\PlatformRbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('barkeelu_test', DB::scalar('select current_database()'));
        $this->seed(PlatformRbacSeeder::class);
    }

    public function test_authenticated_user_creates_an_organization_and_its_initial_owner(): void
    {
        $creator = User::factory()->create();

        $this->as($creator)->postJson('/api/v1/organizations', [
            'name' => 'Association Exemple',
            'type' => 'ASSOCIATION',
            'status' => 'ARCHIVED',
            'public_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Association Exemple')
            ->assertJsonPath('data.slug', 'association-exemple')
            ->assertJsonPath('data.type', 'ASSOCIATION')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonMissingPath('data.id');

        $organization = Organization::query()->sole();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $organization->public_id);
        $this->assertSame($creator->id, $organization->created_by_user_id);
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $creator->id,
            'membership_role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_creation_requires_authentication_and_valid_data(): void
    {
        $this->postJson('/api/v1/organizations', [])->assertUnauthorized();

        $this->as(User::factory()->create())
            ->postJson('/api/v1/organizations', ['name' => '', 'type' => 'INVALID'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_listing_is_limited_to_active_memberships_unless_global_permission_is_granted(): void
    {
        $member = User::factory()->create();
        $otherOwner = User::factory()->create();
        $visible = $this->createOrganizationFor($otherOwner, 'Visible');
        $suspended = $this->createOrganizationFor($otherOwner, 'Suspended');
        $left = $this->createOrganizationFor($otherOwner, 'Left');

        $this->addMembership($visible, $member, OrganizationMembershipRole::MEMBER);
        $this->addMembership($suspended, $member, OrganizationMembershipRole::MEMBER, OrganizationMembershipStatus::SUSPENDED);
        $this->addMembership($left, $member, OrganizationMembershipRole::MEMBER, OrganizationMembershipStatus::LEFT);

        $this->as($member)->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $visible->public_id);

        $member->givePermissionTo('organizations.manage_all');
        $member->refresh();

        $this->as($member)->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_view_policy_allows_active_members_and_global_permission_only(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganizationFor($owner, 'Lecture');
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $globalManager = User::factory()->create();

        $this->addMembership($organization, $admin, OrganizationMembershipRole::ADMIN);
        $this->addMembership($organization, $member, OrganizationMembershipRole::MEMBER);
        $globalManager->givePermissionTo('organizations.manage_all');

        foreach ([$owner, $admin, $member, $globalManager] as $user) {
            $this->as($user)->getJson("/api/v1/organizations/{$organization->public_id}")->assertOk();
        }

        $this->as($outsider)->getJson("/api/v1/organizations/{$organization->public_id}")->assertForbidden();
    }

    public function test_update_policy_preserves_slug_and_isolates_organizations(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganizationFor($owner, 'Nom Stable');
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $globalManager = User::factory()->create();
        $otherOrganization = $this->createOrganizationFor($outsider, 'Autre Organisation');

        $this->addMembership($organization, $admin, OrganizationMembershipRole::ADMIN);
        $this->addMembership($organization, $member, OrganizationMembershipRole::MEMBER);
        $globalManager->givePermissionTo('organizations.manage_all');

        $this->as($owner)->patchJson("/api/v1/organizations/{$organization->public_id}", [
            'name' => 'Nom Modifié',
            'type' => 'FOUNDATION',
            'slug' => 'interdit',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nom Modifié')
            ->assertJsonPath('data.slug', 'nom-stable')
            ->assertJsonPath('data.type', 'FOUNDATION');

        $this->as($admin)->patchJson("/api/v1/organizations/{$organization->public_id}", ['name' => 'Admin'])->assertOk();
        $this->as($member)->patchJson("/api/v1/organizations/{$organization->public_id}", ['name' => 'Membre'])->assertForbidden();
        $this->as($owner)->patchJson("/api/v1/organizations/{$otherOrganization->public_id}", ['name' => 'Interdit'])->assertForbidden();
        $this->as($globalManager)->patchJson("/api/v1/organizations/{$organization->public_id}", ['name' => 'Global'])->assertOk();
    }

    public function test_slug_collisions_are_deterministic_and_membership_failure_rolls_back(): void
    {
        $creator = User::factory()->create();
        $first = $this->createOrganizationFor($creator, 'Association X');
        $second = $this->createOrganizationFor($creator, 'Association X');

        $this->assertSame('association-x', $first->slug);
        $this->assertSame('association-x-2', $second->slug);

        OrganizationMember::creating(static function (): void {
            throw new RuntimeException('Échec de membership simulé.');
        });

        try {
            app(CreateOrganization::class)->create($creator, 'Rollback', OrganizationType::NGO);
            $this->fail('La création devait échouer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Échec de membership simulé.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('organizations', ['slug' => 'rollback']);
    }

    public function test_database_constraints_protect_memberships_public_ids_slugs_and_foreign_keys(): void
    {
        $creator = User::factory()->create();
        $organization = $this->createOrganizationFor($creator, 'Contraintes');

        $this->expectException(QueryException::class);

        OrganizationMember::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $creator->id,
            'membership_role' => OrganizationMembershipRole::MEMBER,
            'status' => OrganizationMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
    }

    public function test_database_constraints_protect_organization_public_ids_slugs_and_foreign_keys(): void
    {
        $creator = User::factory()->create();
        $organization = $this->createOrganizationFor($creator, 'Contraintes Organisation');

        foreach ([
            [
                'public_id' => $organization->public_id,
                'slug' => 'autre-slug',
                'created_by_user_id' => $creator->id,
            ],
            [
                'public_id' => (string) Str::uuid(),
                'slug' => $organization->slug,
                'created_by_user_id' => $creator->id,
            ],
            [
                'public_id' => (string) Str::uuid(),
                'slug' => 'createur-invalide',
                'created_by_user_id' => 999999999,
            ],
        ] as $attributes) {
            try {
                $duplicate = new Organization;
                $duplicate->forceFill([
                    'name' => 'Contrainte invalide',
                    'type' => OrganizationType::NGO,
                    'status' => OrganizationStatus::ACTIVE,
                    ...$attributes,
                ]);
                $duplicate->save();
                $this->fail('La contrainte PostgreSQL devait refuser cette écriture.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->withToken($user->createToken('organizations-test')->plainTextToken);
    }

    private function createOrganizationFor(User $creator, string $name): Organization
    {
        return app(CreateOrganization::class)->create($creator, $name, OrganizationType::ASSOCIATION);
    }

    private function addMembership(
        Organization $organization,
        User $user,
        OrganizationMembershipRole $role,
        OrganizationMembershipStatus $status = OrganizationMembershipStatus::ACTIVE,
    ): void {
        OrganizationMember::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_role' => $role,
            'status' => $status,
            'joined_at' => now(),
            'left_at' => $status === OrganizationMembershipStatus::LEFT ? now() : null,
        ]);
    }
}
