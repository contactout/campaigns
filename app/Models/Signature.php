<?php

namespace App\Models;

use Database\Factories\SignatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $name
 * @property string $body
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 */
#[Fillable(['team_id', 'user_id', 'name', 'body', 'is_default'])]
class Signature extends Model
{
    /** @use HasFactory<SignatureFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     *
     * Enforces a single default signature per team in application code, mirroring
     * the single-default rule used by {@see ContactList}.
     */
    protected static function booted(): void
    {
        static::saving(function (Signature $signature): void {
            if (! $signature->is_default) {
                return;
            }

            static::query()
                ->where('team_id', $signature->team_id)
                ->where('is_default', true)
                ->when($signature->exists, fn (Builder $query) => $query->whereKeyNot($signature->getKey()))
                ->update(['is_default' => false]);
        });
    }

    /**
     * Get the team that owns the signature.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the signature.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include signatures owned by the given team.
     *
     * @param  Builder<Signature>  $query
     * @return Builder<Signature>
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
            'is_default' => 'boolean',
        ];
    }
}
