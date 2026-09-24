<?php

namespace App\Models;

use App\Enums\RecipientStatus;
use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $email
 * @property string|null $timezone
 * @property RecipientStatus $status
 * @property string|null $source
 * @property array<string, mixed>|null $placeholders
 * @property int $sequence
 * @property Carbon|null $next_scheduled_at
 * @property Carbon|null $last_responded_at
 * @property Carbon|null $last_delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Collection<int, CampaignEmail> $emails
 */
#[Fillable(['campaign_id', 'email', 'timezone', 'status', 'source', 'placeholders', 'sequence', 'next_scheduled_at', 'last_responded_at', 'last_delivered_at'])]
class Recipient extends Model
{
    /** @use HasFactory<RecipientFactory> */
    use HasFactory;

    /**
     * Get the campaign that owns the recipient.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the emails for the recipient.
     *
     * @return HasMany<CampaignEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(CampaignEmail::class);
    }

    /**
     * Scope a query to only include recipients for the given campaign.
     *
     * @param  Builder<Recipient>  $query
     * @return Builder<Recipient>
     */
    public function scopeForCampaign(Builder $query, int $campaignId): Builder
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecipientStatus::class,
            'placeholders' => 'array',
            'next_scheduled_at' => 'datetime',
            'last_responded_at' => 'datetime',
            'last_delivered_at' => 'datetime',
        ];
    }
}
