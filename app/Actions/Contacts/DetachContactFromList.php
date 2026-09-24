<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\ContactList;

class DetachContactFromList
{
    /**
     * Detach a contact from a list.
     */
    public function handle(ContactList $list, Contact $contact): void
    {
        $list->contacts()->detach($contact->id);
    }
}
