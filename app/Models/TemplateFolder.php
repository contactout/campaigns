<?php

namespace App\Models;

use Database\Factories\TemplateFolderFactory;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read Collection<int, EmailTemplate> $templates
 */
#[Fillable(['team_id', 'user_id', 'name'])]
class TemplateFolder extends Model
{
    /** @use HasFactory<TemplateFolderFactory> */
    use HasFactory;

    /**
     * Get the team that owns the folder.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the folder.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the templates filed in this folder.
     *
     * @return HasMany<EmailTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class, 'folder_id');
    }

    /**
     * Scope a query to only include folders owned by the given team.
     *
     * @param  Builder<TemplateFolder>  $query
     * @return Builder<TemplateFolder>
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
