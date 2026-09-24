<?php

namespace App\Models;

use Database\Factories\TrackedLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_email_id
 * @property string $url
 * @property string $hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CampaignEmail $campaignEmail
 * @property-read Collection<int, LinkClick> $clicks
 */
#[Fillable(['campaign_email_id', 'url', 'hash'])]
class TrackedLink extends Model
{
    /** @use HasFactory<TrackedLinkFactory> */
    use HasFactory;

    /**
     * Get the route key for the model.
     *
     * Tracked links are addressed publicly by their random hash rather than id.
     */
    public function getRouteKeyName(): string
    {
        return 'hash';
    }

    /**
     * Get the email that owns the tracked link.
     *
     * @return BelongsTo<CampaignEmail, $this>
     */
    public function campaignEmail(): BelongsTo
    {
        return $this->belongsTo(CampaignEmail::class);
    }

    /**
     * Get the clicks recorded for the tracked link.
     *
     * @return HasMany<LinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }
}
