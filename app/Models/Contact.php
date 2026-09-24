<?php

namespace App\Models;

use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $name
 * @property string $source
 * @property string|null $avatar_url
 * @property ContactStatus $status
 * @property string|null $timezone
 * @property Carbon|null $last_contacted_at
 * @property Carbon|null $last_responded_at
 * @property Carbon|null $do_not_contact_at
 * @property int|null $do_not_contact_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read User|null $doNotContactBy
 * @property-read Collection<int, ContactIdentity> $identities
 * @property-read Collection<int, ContactList> $lists
 * @property-read Collection<int, Recipient> $recipients
 * @property-read ContactIdentity|null $emailIdentity
 */
#[Fillable(['team_id', 'user_id', 'name', 'source', 'avatar_url', 'status', 'timezone', 'last_contacted_at', 'last_responded_at', 'do_not_contact_at', 'do_not_contact_by'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the team that owns the contact.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the contact.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that marked the contact as do-not-contact.
     *
     * @return BelongsTo<User, $this>
     */
    public function doNotContactBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'do_not_contact_by');
    }

    /**
     * Get the identities for the contact.
     *
     * @return HasMany<ContactIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(ContactIdentity::class);
    }

    /**
     * Get the primary email identity for the contact.
     *
     * @return HasOne<ContactIdentity, $this>
     */
    public function emailIdentity(): HasOne
    {
        return $this->hasOne(ContactIdentity::class)
            ->where('identity_type', ContactIdentityType::Email->value);
    }

    /**
     * Get the lists the contact belongs to.
     *
     * @return BelongsToMany<ContactList, $this>
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list')->withTimestamps();
    }

    /**
     * Get the campaign recipients for the contact.
     *
     * @return HasMany<Recipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    /**
     * Get the contact's normalized email address, if any.
     */
    public function email(): ?string
    {
        return $this->emailIdentity?->normalized_value;
    }

    /**
     * Scope a query to only include contacts owned by the given team.
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to exclude contacts flagged as do-not-contact.
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeNotDoNotContact(Builder $query): Builder
    {
        return $query->where('status', '!=', ContactStatus::DoNotContact->value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'last_contacted_at' => 'datetime',
            'last_responded_at' => 'datetime',
            'do_not_contact_at' => 'datetime',
        ];
    }
}
