<?php

namespace App\Models;

use App\Enums\PlaceholderType;
use Database\Factories\ContactFieldFactory;
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
 * @property PlaceholderType $type
 * @property string|null $fallback
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read Collection<int, ContactProperty> $properties
 */
#[Fillable(['team_id', 'user_id', 'name', 'type', 'fallback', 'position'])]
class ContactField extends Model
{
    /** @use HasFactory<ContactFieldFactory> */
    use HasFactory;

    /**
     * Get the team that owns the contact field.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the contact field.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the property values stored for the field.
     *
     * @return HasMany<ContactProperty, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(ContactProperty::class);
    }

    /**
     * Scope a query to only include contact fields owned by the given team.
     *
     * @param  Builder<ContactField>  $query
     * @return Builder<ContactField>
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
