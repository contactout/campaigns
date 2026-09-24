<?php

namespace App\Services\Mail;

use App\Models\ContactField;
use App\Models\Recipient;

/**
 * Renders campaign email content by replacing recipient placeholders.
 *
 * Supports the built-in `name`, `email` and `phone` placeholders plus any
 * custom contact field defined for the recipient's team. Matching is
 * case-insensitive and tolerant of optional surrounding whitespace; unknown
 * placeholders resolve to an empty string.
 */
class PlaceholderRenderer
{
    /**
     * Render the given HTML for a single recipient.
     */
    public function render(string $html, Recipient $recipient): string
    {
        $values = $this->valuesFor($recipient);

        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/',
            static fn (array $matches): string => $values[strtolower($matches[1])] ?? '',
            $html,
        );
    }

    /**
     * Build the placeholder value map for the recipient's contact.
     *
     * @return array<string, string>
     */
    private function valuesFor(Recipient $recipient): array
    {
        $recipient->loadMissing([
            'contact.emailIdentity',
            'contact.phoneIdentity',
            'contact.properties',
        ]);

        $contact = $recipient->contact;

        $values = [
            'name' => (string) $contact->name,
            'email' => (string) ($contact->email() ?? ''),
            'phone' => (string) ($contact->phone() ?? ''),
        ];

        $fields = ContactField::query()
            ->where('team_id', $contact->team_id)
            ->get();

        foreach ($fields as $field) {
            $values[strtolower($field->name)] = (string) ($contact->propertyValue($field->id) ?? '');
        }

        return $values;
    }
}
