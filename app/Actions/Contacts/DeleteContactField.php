<?php

namespace App\Actions\Contacts;

use App\Models\ContactField;

class DeleteContactField
{
    /**
     * Delete the given custom contact field.
     *
     * The stored property values are removed by the cascading foreign key.
     */
    public function handle(ContactField $field): void
    {
        $field->delete();
    }
}
