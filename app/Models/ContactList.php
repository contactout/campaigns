<?php

namespace App\Models;

use Database\Factories\ContactListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $name
 * @property bool $is_default
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read Collection<int, Contact> $contacts
 */
#[Fillable(['team_id', 'user_id', 'name', 'is_default', 'settings'])]
class ContactList extends Model
{
    /** @use HasFactory<ContactListFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     *
     * Enforces a single default list per team in application code because the
     * generated-column approach used by the source is MySQL-only.
     */
    protected static function booted(): void
    {
        static::saving(function (ContactList $list): void {
            if (! $list->is_default) {
                return;
            }

            static::query()
                ->where('team_id', $list->team_id)
                ->where('is_default', true)
                ->when($list->exists, fn (Builder $query) => $query->whereKeyNot($list->getKey()))
                ->update(['is_default' => false]);
        });
    }

    /**
     * Get the team that owns the list.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the list.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contacts that belong to the list.
     *
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_list')->withTimestamps();
    }

    /**
     * Scope a query to only include lists owned by the given team.
     *
     * @param  Builder<ContactList>  $query
     * @return Builder<ContactList>
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
            'settings' => 'array',
        ];
    }
}
