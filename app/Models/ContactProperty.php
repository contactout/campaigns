<?php

namespace App\Models;

use Database\Factories\ContactPropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_id
 * @property int $contact_field_id
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact $contact
 * @property-read ContactField $field
 */
#[Fillable(['contact_id', 'contact_field_id', 'value'])]
class ContactProperty extends Model
{
    /** @use HasFactory<ContactPropertyFactory> */
    use HasFactory;

    /**
     * Get the contact that owns the property.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the contact field the property belongs to.
     *
     * @return BelongsTo<ContactField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ContactField::class, 'contact_field_id');
    }
}
