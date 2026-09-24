<?php

namespace App\Actions\Contacts;

use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UpdateContactCell
{
    /**
     * Update a single field on the given contact.
     *
     * @throws ValidationException when the value is invalid or conflicts with another contact.
     */
    public function handle(Contact $contact, string $field, ?string $value): Contact
    {
        return DB::transaction(function () use ($contact, $field, $value): Contact {
            match ($field) {
                'name' => $this->updateName($contact, $value),
                'timezone' => $this->updateTimezone($contact, $value),
                'status' => $this->updateStatus($contact, $value),
                'email' => $this->updateEmail($contact, $value),
                'phone' => $this->updatePhone($contact, $value),
                default => throw new InvalidArgumentException("Unsupported contact field: {$field}"),
            };

            return $contact;
        });
    }

    /**
     * Update the contact's name.
     */
    protected function updateName(Contact $contact, ?string $value): void
    {
        if (trim((string) $value) === '') {
            throw ValidationException::withMessages([
                'value' => __('Name cannot be empty.'),
            ]);
        }

        $contact->name = trim((string) $value);
        $contact->save();
    }

    /**
     * Update the contact's timezone.
     */
    protected function updateTimezone(Contact $contact, ?string $value): void
    {
        $contact->timezone = $value === '' ? null : $value;
        $contact->save();
    }

    /**
     * Update the contact's status.
     */
    protected function updateStatus(Contact $contact, ?string $value): void
    {
        $contact->status = ContactStatus::from((string) $value);
        $contact->save();
    }

    /**
     * Update or create the contact's email identity.
     */
    protected function updateEmail(Contact $contact, ?string $value): void
    {
        $email = ContactIdentityType::Email->normalize((string) $value);

        if ($email === '') {
            throw ValidationException::withMessages([
                'value' => __('Email is required.'),
            ]);
        }

        $this->ensureIdentityIsUnique($contact, ContactIdentityType::Email, $email);

        $this->syncIdentity($contact, ContactIdentityType::Email, $email);
    }

    /**
     * Update, create or clear the contact's phone identity.
     */
    protected function updatePhone(Contact $contact, ?string $value): void
    {
        $phone = ContactIdentityType::Phone->normalize((string) $value);

        if ($phone === '') {
            $contact->identities()->forType(ContactIdentityType::Phone)->delete();

            return;
        }

        $this->ensureIdentityIsUnique($contact, ContactIdentityType::Phone, $phone);

        $this->syncIdentity($contact, ContactIdentityType::Phone, $phone);
    }

    /**
     * Ensure no other contact in the team already owns the given identity value.
     */
    protected function ensureIdentityIsUnique(Contact $contact, ContactIdentityType $type, string $value): void
    {
        $ownIdentityId = $contact->identities()->forType($type)->value('id');

        $duplicate = ContactIdentity::query()
            ->where('team_id', $contact->team_id)
            ->where('identity_type', $type->value)
            ->where('normalized_value', $value)
            ->when($ownIdentityId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'value' => __('This :type is already used by another contact.', ['type' => $type->label()]),
            ]);
        }
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
