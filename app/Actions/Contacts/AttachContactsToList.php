<?php

namespace App\Actions\Contacts;

use App\Models\ContactList;
use Illuminate\Support\Facades\DB;

class AttachContactsToList
{
    /**
     * Attach the given contacts to a list, ignoring existing memberships.
     *
     * @param  array<int, int|string>  $contactIds
     */
    public function handle(ContactList $list, array $contactIds): void
    {
        DB::transaction(function () use ($list, $contactIds): void {
            $list->contacts()->syncWithoutDetaching($contactIds);
        });
    }
}
