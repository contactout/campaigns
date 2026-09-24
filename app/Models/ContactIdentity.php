<?php

namespace App\Models;

use App\Enums\ContactIdentityType;
use Database\Factories\ContactIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $contact_id
 * @property ContactIdentityType $identity_type
 * @property string $normalized_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact $contact
 */
#[Fillable(['team_id', 'contact_id', 'identity_type', 'normalized_value'])]
class ContactIdentity extends Model
{
    /** @use HasFactory<ContactIdentityFactory> */
    use HasFactory;

    /**
     * Get the contact that owns the identity.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Scope a query to only include identities of the given type.
     *
     * @param  Builder<ContactIdentity>  $query
     * @return Builder<ContactIdentity>
     */
    public function scopeForType(Builder $query, ContactIdentityType $type): Builder
    {
        return $query->where('identity_type', $type->value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'identity_type' => ContactIdentityType::class,
        ];
    }
}
