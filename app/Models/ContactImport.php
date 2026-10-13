<?php

namespace App\Models;

use App\Enums\ContactImportDuplicateAction;
use App\Enums\ContactImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property int|null $contact_list_id
 * @property string $original_name
 * @property string $file_path
 * @property array<int, string> $mapping
 * @property ContactImportDuplicateAction $duplicate_action
 * @property ContactImportStatus $status
 * @property int $created_count
 * @property int $updated_count
 * @property int $skipped_count
 * @property int $invalid_count
 * @property array<int, array{row: int, reason: string}>|null $failures
 * @property int $byte_offset
 * @property int $rows_read
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $user
 * @property-read ContactList|null $contactList
 */
#[Fillable([
    'team_id',
    'user_id',
    'contact_list_id',
    'original_name',
    'file_path',
    'mapping',
    'duplicate_action',
    'status',
    'created_count',
    'updated_count',
    'skipped_count',
    'invalid_count',
    'failures',
    'byte_offset',
    'rows_read',
    'completed_at',
])]
class ContactImport extends Model
{
    /**
     * Get the team that owns the import.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that started the import.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the list imported contacts are attached to, if any.
     *
     * @return BelongsTo<ContactList, $this>
     */
    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    /**
     * Scope a query to only include imports owned by the given team.
     *
     * @param  Builder<ContactImport>  $query
     * @return Builder<ContactImport>
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
            'mapping' => 'array',
            'duplicate_action' => ContactImportDuplicateAction::class,
            'status' => ContactImportStatus::class,
            'failures' => 'array',
            'byte_offset' => 'integer',
            'rows_read' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
