<?php

namespace App\Models;

use App\Enums\PlaceholderType;
use Database\Factories\PlaceholderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $name
 * @property string|null $fallback
 * @property PlaceholderType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Model|null $owner
 */
#[Fillable(['team_id', 'owner_type', 'owner_id', 'name', 'fallback', 'type'])]
class Placeholder extends Model
{
    /** @use HasFactory<PlaceholderFactory> */
    use HasFactory;

    /**
     * Get the team that owns the placeholder.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the owning model (for example an email template).
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include placeholders owned by the given team.
     *
     * @param  Builder<Placeholder>  $query
     * @return Builder<Placeholder>
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
            'type' => PlaceholderType::class,
        ];
    }
}
