<?php

namespace App\Models;

use Database\Factories\UnsubscribeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $campaign_id
 * @property int|null $recipient_id
 * @property string $email
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Campaign|null $campaign
 * @property-read Recipient|null $recipient
 */
#[Fillable(['team_id', 'campaign_id', 'recipient_id', 'email', 'reason'])]
class Unsubscribe extends Model
{
    /** @use HasFactory<UnsubscribeFactory> */
    use HasFactory;

    /**
     * Get the team that owns the unsubscribe.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the campaign the unsubscribe originated from, if any.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the recipient that unsubscribed, if any.
     *
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Scope a query to only include unsubscribes owned by the given team.
     *
     * @param  Builder<Unsubscribe>  $query
     * @return Builder<Unsubscribe>
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
