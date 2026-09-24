<?php

namespace App\Actions\Contacts;

use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateContact
{
    /**
     * Create a contact with its email identity and attach it to the given lists.
     *
     * @param  array{name: string, email: string, phone?: string|null, timezone?: string|null, status?: string|null, lists?: array<int, int|string>|null, source?: string|null}  $data
     */
    public function handle(Team $team, User $user, array $data): Contact
    {
        return DB::transaction(function () use ($team, $user, $data): Contact {
            $contact = $team->contacts()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'source' => $data['source'] ?? 'manual',
                'timezone' => $data['timezone'] ?? null,
                'status' => isset($data['status']) ? ContactStatus::from($data['status']) : ContactStatus::NotContacted,
            ]);

            $this->createIdentity($contact, ContactIdentityType::Email, $data['email']);

            if (($data['phone'] ?? null) !== null) {
                $this->createIdentity($contact, ContactIdentityType::Phone, $data['phone']);
            }

            if (! empty($data['lists'])) {
                $contact->lists()->sync($data['lists']);
            }

            return $contact;
        });
    }

    /**
     * Create an identity for the contact.
     */
    protected function createIdentity(Contact $contact, ContactIdentityType $type, string $value): void
    {
        $contact->identities()->create([
            'team_id' => $contact->team_id,
            'identity_type' => $type,
            'normalized_value' => $value,
        ]);
    }
}
