<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LinkClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tracked_link_id
 * @property int $campaign_email_id
 * @property int $recipient_id
 * @property CarbonImmutable $clicked_at
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TrackedLink $trackedLink
 * @property-read CampaignEmail $campaignEmail
 * @property-read Recipient $recipient
 */
#[Fillable(['tracked_link_id', 'campaign_email_id', 'recipient_id', 'clicked_at', 'user_agent'])]
class LinkClick extends Model
{
    /** @use HasFactory<LinkClickFactory> */
    use HasFactory;

    /**
     * Get the tracked link that was clicked.
     *
     * @return BelongsTo<TrackedLink, $this>
     */
    public function trackedLink(): BelongsTo
    {
        return $this->belongsTo(TrackedLink::class);
    }

    /**
     * Get the email that was clicked.
     *
     * @return BelongsTo<CampaignEmail, $this>
     */
    public function campaignEmail(): BelongsTo
    {
        return $this->belongsTo(CampaignEmail::class);
    }

    /**
     * Get the recipient that clicked the link.
     *
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }
}
