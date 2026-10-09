<?php

namespace App\Models;

use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use Carbon\CarbonImmutable;
use Database\Factories\MailerConnectionFactory;
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
 * @property int|null $signature_id
 * @property string $name
 * @property MailerType $mailer_type
 * @property array<string, mixed>|null $smtp_setting
 * @property MailerConnectionStatus $status
 * @property string|null $exception_type
 * @property array<string, mixed>|null $exception_data
 * @property CarbonImmutable|null $threw_at
 * @property CarbonImmutable|null $rate_limit_expired_at
 * @property int|null $sending_limit
 * @property int $sent_count
 * @property CarbonImmutable|null $sending_limit_refreshed_at
 * @property CarbonImmutable|null $last_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read Signature|null $signature
 * @property-read Collection<int, Campaign> $campaigns
 */
#[Fillable(['team_id', 'user_id', 'signature_id', 'name', 'mailer_type', 'smtp_setting', 'status', 'exception_type', 'exception_data', 'threw_at', 'rate_limit_expired_at', 'sending_limit', 'sent_count', 'sending_limit_refreshed_at', 'last_checked_at'])]
class MailerConnection extends Model
{
    /** @use HasFactory<MailerConnectionFactory> */
    use HasFactory;

    /**
     * Get the team that owns the mailer connection.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the mailer connection.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the signature appended to emails sent through the connection.
     *
     * @return BelongsTo<Signature, $this>
     */
    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    /**
     * Resolve the signature for emails sent through the connection.
     *
     * Falls back to the team's default signature when none is assigned.
     */
    public function resolveSignature(): ?Signature
    {
        return $this->signature
            ?? Signature::query()
                ->forTeam($this->team_id)
                ->where('is_default', true)
                ->first();
    }

    /**
     * Get the campaigns using the mailer connection.
     *
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Scope a query to only include mailer connections owned by the given team.
     *
     * @param  Builder<MailerConnection>  $query
     * @return Builder<MailerConnection>
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mailer_type' => MailerType::class,
            // SMTP credentials are encrypted at rest; the column is a text type.
            'smtp_setting' => 'encrypted:array',
            'status' => MailerConnectionStatus::class,
            'exception_data' => 'array',
            'threw_at' => 'datetime',
            'rate_limit_expired_at' => 'datetime',
            'sending_limit_refreshed_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
