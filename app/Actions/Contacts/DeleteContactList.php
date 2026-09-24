<?php

namespace App\Actions\Contacts;

use App\Models\ContactList;
use Illuminate\Support\Facades\DB;

class DeleteContactList
{
    /**
     * Delete a contact list and detach its contacts.
     */
    public function handle(ContactList $list): void
    {
        DB::transaction(function () use ($list): void {
            $list->contacts()->detach();

            $list->delete();
        });
    }
}
