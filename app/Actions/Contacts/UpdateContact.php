<?php

namespace App\Actions\Contacts;

use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class UpdateContact
{
    /**
     * Update a contact and keep its email, phone and list memberships in sync.
     *
     * @param  array{name: string, email: string, phone?: string|null, timezone?: string|null, status?: string|null, lists?: array<int, int|string>|null, source?: string|null}  $data
     */
    public function handle(Contact $contact, array $data): Contact
    {
        return DB::transaction(function () use ($contact, $data): Contact {
            $contact->fill([
                'name' => $data['name'],
                'timezone' => $data['timezone'] ?? null,
                'source' => $data['source'] ?? $contact->source,
            ]);

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $contact->status = ContactStatus::from($data['status']);
            }

            $contact->save();

            $this->syncIdentity($contact, ContactIdentityType::Email, $data['email']);

            if (array_key_exists('phone', $data)) {
                if ($data['phone'] === null) {
                    $contact->identities()->forType(ContactIdentityType::Phone)->delete();
                } else {
                    $this->syncIdentity($contact, ContactIdentityType::Phone, $data['phone']);
                }
            }

            if (array_key_exists('lists', $data)) {
                $contact->lists()->sync($data['lists'] ?? []);
            }

            return $contact;
        });
    }

    /**
     * Update or create the identity of the given type for the contact.
     */
    protected function syncIdentity(Contact $contact, ContactIdentityType $type, string $value): void
    {
        $contact->identities()->updateOrCreate(
            ['identity_type' => $type->value],
            ['team_id' => $contact->team_id, 'normalized_value' => $value],
        );
    }
}
