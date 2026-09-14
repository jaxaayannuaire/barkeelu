<?php

namespace App\Models;

use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = ['goal_reached'];

    protected function casts(): array
    {
        return ['status' => CampaignStatus::class, 'fundraising_status' => CampaignFundraisingStatus::class, 'payout_status' => CampaignPayoutStatus::class, 'visibility' => CampaignVisibility::class, 'featured' => 'boolean', 'published_at' => 'datetime', 'start_at' => 'datetime', 'end_at' => 'datetime', 'closed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function getGoalReachedAttribute(): bool
    {
        return $this->net_collected_nominal >= $this->goal_amount;
    }

    public function scopePubliclyListed(Builder $query): void
    {
        $query
            ->where('status', CampaignStatus::PUBLISHED->value)
            ->where('visibility', CampaignVisibility::PUBLIC->value);
    }

    public function scopePubliclyViewable(Builder $query): void
    {
        $query
            ->where('status', CampaignStatus::PUBLISHED->value)
            ->whereIn('visibility', [
                CampaignVisibility::PUBLIC->value,
                CampaignVisibility::UNLISTED->value,
            ]);
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function ownerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'owner_organization_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }
}
