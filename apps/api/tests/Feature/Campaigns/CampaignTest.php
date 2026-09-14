<?php

namespace Tests\Feature\Campaigns;

use App\Enums\BeneficiaryRepresentativeStatus;
use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Models\Beneficiary;
use App\Models\BeneficiaryRepresentative;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Campaigns\TransitionCampaign;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_constraints_protect_owner_goal_projections_dates_and_uniques(): void
    {
        $user = User::factory()->create();
        $beneficiary = $this->beneficiary($user);
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        foreach ([['owner_user_id' => null, 'owner_organization_id' => null], ['owner_user_id' => $user->id, 'owner_organization_id' => $this->organization($user)->id]] as $owners) {
            try {
                Campaign::query()->create(array_merge($this->attributes($user, $beneficiary), $owners));
                $this->fail('Le XOR owner aurait dû être rejeté.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(QueryException::class);
        Campaign::query()->create(array_merge($this->attributes($user, $beneficiary), ['goal_amount' => 0]));
    }

    public function test_postgresql_rejects_negative_projection_invalid_dates_and_foreign_key(): void
    {
        $user = User::factory()->create();
        $beneficiary = $this->beneficiary($user);
        foreach ([['gross_collected_nominal' => -1], ['start_at' => now(), 'end_at' => now()->subMinute()], ['beneficiary_id' => 999999]] as $changes) {
            try {
                Campaign::query()->create(array_merge($this->attributes($user, $beneficiary), $changes));
                $this->fail('La contrainte PostgreSQL aurait dû être rejetée.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_authenticated_user_creates_campaign_with_server_defaults_and_stable_slug(): void
    {
        $user = User::factory()->create();
        $beneficiary = $this->beneficiary($user);
        $token = $user->createToken('campaign')->plainTextToken;
        $payload = ['title' => 'Campagne Exemple', 'description' => 'Description', 'beneficiary_public_id' => $beneficiary->public_id, 'goal_amount' => 1000, 'currency' => 'XOF', 'visibility' => 'PUBLIC', 'net_collected_nominal' => 99];
        $this->withToken($token)->postJson('/api/v1/campaigns', $payload)->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.payout_status', 'NOT_ELIGIBLE');
        $campaign = Campaign::query()->firstOrFail();
        $slug = $campaign->slug;
        $this->withToken($token)->patchJson('/api/v1/manage/campaigns/'.$campaign->public_id, ['title' => 'Titre modifié', 'net_collected_nominal' => 99])->assertOk();
        $this->assertSame($slug, $campaign->refresh()->slug);
        $this->assertSame(0, $campaign->net_collected_nominal);
    }

    public function test_currency_and_targeted_visibility_are_rejected(): void
    {
        $user = User::factory()->create();
        $beneficiary = $this->beneficiary($user);
        $token = $user->createToken('campaign')->plainTextToken;
        $base = ['title' => 'T', 'description' => 'D', 'beneficiary_public_id' => $beneficiary->public_id, 'goal_amount' => 1, 'currency' => 'XOF', 'visibility' => 'PUBLIC'];
        $this->withToken($token)->postJson('/api/v1/campaigns', array_merge($base, ['currency' => 'EUR']))->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/campaigns', array_merge($base, ['visibility' => 'TARGETED']))->assertUnprocessable();
    }

    public function test_slug_collisions_are_deterministic(): void
    {
        $user = User::factory()->create();
        $beneficiary = $this->beneficiary($user);
        $token = $user->createToken('campaign')->plainTextToken;
        $payload = ['title' => 'Même titre', 'description' => 'D', 'beneficiary_public_id' => $beneficiary->public_id, 'goal_amount' => 1, 'currency' => 'XOF', 'visibility' => 'PUBLIC'];
        $this->withToken($token)->postJson('/api/v1/campaigns', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/campaigns', $payload)->assertCreated();
        $this->assertSame(['meme-titre', 'meme-titre-2'], Campaign::query()->orderBy('id')->pluck('slug')->all());
    }

    public function test_organization_owner_and_admin_can_create_but_member_cannot(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $beneficiary = $this->beneficiary($owner);
        $organization = $this->organization($owner);
        OrganizationMember::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'membership_role' => 'ADMIN', 'status' => 'ACTIVE', 'joined_at' => now()]);
        OrganizationMember::query()->create(['organization_id' => $organization->id, 'user_id' => $member->id, 'membership_role' => 'MEMBER', 'status' => 'ACTIVE', 'joined_at' => now()]);
        BeneficiaryRepresentative::query()->create(['beneficiary_id' => $beneficiary->id, 'representative_user_id' => $admin->id, 'status' => BeneficiaryRepresentativeStatus::ACTIVE, 'valid_from' => now(), 'created_by_user_id' => $owner->id]);
        $base = ['title' => 'Organisation', 'description' => 'D', 'beneficiary_public_id' => $beneficiary->public_id, 'goal_amount' => 1, 'currency' => 'XOF', 'visibility' => 'PRIVATE', 'owner_organization_public_id' => $organization->public_id];
        $this->withToken($owner->createToken('owner')->plainTextToken)->postJson('/api/v1/campaigns', $base)->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->withToken($admin->createToken('admin')->plainTextToken)->postJson('/api/v1/campaigns', $base)->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->withToken($member->createToken('member')->plainTextToken)->postJson('/api/v1/campaigns', $base)->assertForbidden();
    }

    public function test_public_visibility_does_not_leak_unlisted_private_or_targeted_campaigns(): void
    {
        $user = User::factory()->create();
        $beneficiary = $this->beneficiary($user);
        $public = $this->campaign($user, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC);
        $unlisted = $this->campaign($user, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::UNLISTED);
        $private = $this->campaign($user, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::PRIVATE);
        $targeted = $this->campaign($user, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::TARGETED);
        $this->getJson('/api/v1/campaigns')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.public_id', $public->public_id)->assertJsonPath('meta.current_page', 1);
        $this->getJson('/api/v1/campaigns/by-slug/'.$unlisted->slug)->assertOk();
        $this->getJson('/api/v1/campaigns/by-slug/'.$private->slug)->assertNotFound();
        $this->getJson('/api/v1/campaigns/by-slug/'.$targeted->slug)->assertNotFound();
    }

    public function test_workflow_is_controlled_and_publishing_sets_fundraising_status(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        Permission::findOrCreate('moderation.manage', 'web');
        $moderator->givePermissionTo('moderation.manage');
        $campaign = $this->campaign($owner, $this->beneficiary($owner));
        $service = app(TransitionCampaign::class);
        $this->assertTrue(Gate::forUser($owner)->allows('submit', $campaign));
        $campaign = $service->transition($campaign, CampaignStatus::SUBMITTED);
        $this->assertFalse(Gate::forUser($owner)->allows('review', $campaign));
        $this->assertTrue(Gate::forUser($moderator)->allows('review', $campaign));
        $campaign = $service->transition($campaign, CampaignStatus::UNDER_REVIEW);
        $campaign = $service->transition($campaign, CampaignStatus::PUBLISHED);
        $this->assertSame(CampaignFundraisingStatus::OPEN, $campaign->fundraising_status);
        $this->assertNotNull($campaign->published_at);
        $this->assertFalse($campaign->goal_reached);
        $campaign->update(['net_collected_nominal' => $campaign->goal_amount]);
        $this->assertTrue($campaign->refresh()->goal_reached);
        $this->expectException(\DomainException::class);
        $service->transition($campaign, CampaignStatus::REJECTED);
    }

    private function attributes(User $user, Beneficiary $beneficiary): array
    {
        return ['public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'owner_organization_id' => null, 'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'Campagne '.Str::random(8), 'slug' => 'campaign-'.Str::lower(Str::random(12)), 'description' => 'Description', 'goal_amount' => 100, 'currency' => 'XOF', 'status' => CampaignStatus::DRAFT, 'fundraising_status' => CampaignFundraisingStatus::NOT_STARTED, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC];
    }

    private function beneficiary(User $user): Beneficiary
    {
        return Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'display_name' => 'Bénéficiaire', 'type' => BeneficiaryType::INDIVIDUAL, 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id]);
    }

    private function organization(User $user): Organization
    {
        $organization = new Organization;
        $organization->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Org', 'slug' => 'org-'.Str::lower(Str::random(8)), 'type' => 'ASSOCIATION', 'status' => 'ACTIVE', 'created_by_user_id' => $user->id]);
        $organization->save();
        OrganizationMember::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'membership_role' => 'OWNER', 'status' => 'ACTIVE', 'joined_at' => now()]);

        return $organization;
    }

    private function campaign(User $user, Beneficiary $beneficiary, CampaignStatus $status = CampaignStatus::DRAFT, CampaignVisibility $visibility = CampaignVisibility::PUBLIC): Campaign
    {
        return Campaign::query()->create(array_merge($this->attributes($user, $beneficiary), ['status' => $status, 'visibility' => $visibility, 'published_at' => $status === CampaignStatus::PUBLISHED ? now() : null]));
    }
}
