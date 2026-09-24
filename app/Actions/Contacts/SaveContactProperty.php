<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\ContactField;

class SaveContactProperty
{
    /**
     * Store or clear the given contact field value on the contact.
     *
     * An empty value clears the cell by deleting the stored property row.
     */
    public function handle(Contact $contact, ContactField $field, ?string $value): void
    {
        if ($value === null || $value === '') {
            $contact->properties()->where('contact_field_id', $field->id)->delete();

            return;
        }

        $contact->properties()->updateOrCreate(
            ['contact_field_id' => $field->id],
            ['value' => $value],
        );
    }
}
