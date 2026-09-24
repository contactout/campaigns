<?php

namespace App\Actions\Contacts;

use App\Models\ContactList;

class UpdateContactList
{
    /**
     * Update a contact list, optionally changing its default status.
     */
    public function handle(ContactList $list, string $name, ?bool $isDefault = null): ContactList
    {
        $list->fill(['name' => $name]);

        if ($isDefault !== null) {
            $list->is_default = $isDefault;
        }

        $list->save();

        return $list;
    }
}
