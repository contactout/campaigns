<?php

namespace App\Services\Mail;

use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\Unsubscribe;

/**
 * Decides which contacts and addresses a team must never email.
 *
 * The team's `unsubscribes` table is the suppression list. It holds exact
 * addresses and whole domains written as `@example.com`, stored lowercased
 * and so matched case-insensitively. A contact is also suppressed when it is flagged
 * do-not-contact or carries a bounced or unsubscribed status, so a contact
 * that bounced in one campaign is never emailed from another.
 */
class SuppressionList
{
    /**
     * Contact statuses that rule out any further email.
     *
     * @var array<int, ContactStatus>
     */
    private const array SUPPRESSED_STATUSES = [
        ContactStatus::Bounced,
        ContactStatus::Unsubscribed,
        ContactStatus::DoNotContact,
    ];

    /**
     * Addresses looked up per query, keeping bindings under driver limits.
     */
    private const int LOOKUP_CHUNK = 500;

    /**
     * Add an address to the team's suppression list.
     *
     * An address already on the list keeps its original entry.
     */
    public function add(int $teamId, string $email, string $reason, ?int $campaignId = null, ?int $recipientId = null): void
    {
        $email = ContactIdentityType::Email->normalize($email);

        if ($email === '') {
            return;
        }

        Unsubscribe::query()->upsert([
            'team_id' => $teamId,
            'campaign_id' => $campaignId,
            'recipient_id' => $recipientId,
            'email' => $email,
            'reason' => $reason,
        ], ['team_id', 'email'], ['updated_at']);
    }

    /**
     * Determine whether the contact must not be emailed at the given address.
     *
     * As at enrollment, the contact is suppressed when any of its email
     * addresses is listed, not only the one being sent to.
     */
    public function isSuppressed(Contact $contact, string $email): bool
    {
        if ($this->isBlocked($contact)) {
            return true;
        }

        $emails = $contact->identities()
            ->where('identity_type', ContactIdentityType::Email->value)
            ->pluck('normalized_value')
            ->all();

        return $this->listedEmails($contact->team_id, [$email, ...$emails]) !== [];
    }

    /**
     * Filter the given contacts down to the ones that must not be emailed.
     *
     * A contact is suppressed when any of its email addresses is listed.
     *
     * @param  array<int, int>  $contactIds
     * @return array<int, int>
     */
    public function suppressedContactIds(int $teamId, array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        $blocked = Contact::query()
            ->whereIntegerInRaw('id', $contactIds)
            ->where(fn ($query) => $query
                ->whereNotNull('do_not_contact_at')
                ->orWhereIn('status', array_map(fn (ContactStatus $status): string => $status->value, self::SUPPRESSED_STATUSES)))
            ->pluck('id')
            ->map(intval(...))
            ->all();

        $identities = ContactIdentity::query()
            ->whereIntegerInRaw('contact_id', array_values(array_diff($contactIds, $blocked)))
            ->where('identity_type', ContactIdentityType::Email->value)
            ->get(['contact_id', 'normalized_value']);

        $listed = array_flip($this->listedEmails($teamId, $identities->pluck('normalized_value')->all()));

        $suppressed = $identities
            ->filter(fn (ContactIdentity $identity): bool => isset($listed[ContactIdentityType::Email->normalize($identity->normalized_value)]))
            ->pluck('contact_id')
            ->map(intval(...))
            ->all();

        return array_values(array_unique([...$blocked, ...$suppressed]));
    }

    /**
     * Determine whether the contact itself rules out any further email.
     */
    private function isBlocked(Contact $contact): bool
    {
        return $contact->do_not_contact_at !== null
            || in_array($contact->status, self::SUPPRESSED_STATUSES, true);
    }

    /**
     * Return the lowercased addresses that the team's list suppresses, either
     * directly or through their domain.
     *
     * @param  array<int, string>  $emails
     * @return array<int, string>
     */
    private function listedEmails(int $teamId, array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(
            fn (string $email): string => ContactIdentityType::Email->normalize($email),
            $emails,
        ))));

        if ($emails === []) {
            return [];
        }

        $candidates = array_values(array_unique([
            ...$emails,
            ...array_filter(array_map($this->domainEntry(...), $emails)),
        ]));

        $entries = [];

        foreach (array_chunk($candidates, self::LOOKUP_CHUNK) as $chunk) {
            $entries = [
                ...$entries,
                ...Unsubscribe::query()
                    ->forTeam($teamId)
                    ->whereIn('email', $chunk)
                    ->pluck('email')
                    ->all(),
            ];
        }

        if ($entries === []) {
            return [];
        }

        $entries = array_flip($entries);

        return array_values(array_filter(
            $emails,
            fn (string $email): bool => isset($entries[$email]) || isset($entries[$this->domainEntry($email)]),
        ));
    }

    /**
     * Get the `@domain` list entry covering the address, if it has a domain.
     */
    private function domainEntry(string $email): string
    {
        $domain = strrchr($email, '@');

        return $domain === false || $domain === '@' ? '' : $domain;
    }
}
