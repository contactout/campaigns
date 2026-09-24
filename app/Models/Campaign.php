<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CampaignFactory;
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
 * @property int $team_id
 * @property int|null $user_id
 * @property string $name
 * @property CampaignStatus $status
 * @property string $timezone
 * @property int|null $mailer_connection_id
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable|null $started_at
 * @property string|null $interrupted_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read MailerConnection|null $mailerConnection
 * @property-read Collection<int, CampaignStep> $steps
 * @property-read Collection<int, Recipient> $recipients
 * @property-read Collection<int, CampaignEmail> $emails
 */
#[Fillable(['team_id', 'user_id', 'name', 'status', 'timezone', 'mailer_connection_id', 'settings', 'started_at', 'interrupted_reason'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * Get the team that owns the campaign.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the campaign.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the mailer connection used by the campaign.
     *
     * @return BelongsTo<MailerConnection, $this>
     */
    public function mailerConnection(): BelongsTo
    {
        return $this->belongsTo(MailerConnection::class);
    }

    /**
     * Get the steps for the campaign, ordered by sequence.
     *
     * @return HasMany<CampaignStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(CampaignStep::class)->orderBy('sequence');
    }

    /**
     * Get the recipients for the campaign.
     *
     * @return HasMany<Recipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    /**
     * Get the emails for the campaign.
     *
     * @return HasMany<CampaignEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(CampaignEmail::class);
    }

    /**
     * Scope a query to only include campaigns owned by the given team.
     *
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to only include active campaigns.
     *
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Active);
    }

    /**
     * Determine whether the campaign can be started.
     *
     * A campaign needs at least one step and must be in the draft or stopped state.
     */
    public function canBeStarted(): bool
    {
        if (! in_array($this->status, [CampaignStatus::Draft, CampaignStatus::Stopped], true)) {
            return false;
        }

        return $this->steps()->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'settings' => 'array',
            'started_at' => 'immutable_datetime',
        ];
    }
}
