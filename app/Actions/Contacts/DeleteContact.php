<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class DeleteContact
{
    /**
     * Soft delete a contact and detach it from any lists.
     */
    public function handle(Contact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $contact->lists()->detach();

            $contact->delete();
        });
    }
}
