<?php

namespace App\Models;

use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property int|null $folder_id
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property bool $is_draft
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read TemplateFolder|null $folder
 * @property-read Collection<int, Placeholder> $placeholders
 */
#[Fillable(['team_id', 'user_id', 'folder_id', 'name', 'subject', 'body', 'is_draft'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    /**
     * Get the team that owns the template.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the template.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the folder the template is filed in.
     *
     * @return BelongsTo<TemplateFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(TemplateFolder::class, 'folder_id');
    }

    /**
     * Get the placeholders defined for the template.
     *
     * @return MorphMany<Placeholder, $this>
     */
    public function placeholders(): MorphMany
    {
        return $this->morphMany(Placeholder::class, 'owner');
    }

    /**
     * Scope a query to only include templates owned by the given team.
     *
     * @param  Builder<EmailTemplate>  $query
     * @return Builder<EmailTemplate>
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
            'is_draft' => 'boolean',
        ];
    }
}
