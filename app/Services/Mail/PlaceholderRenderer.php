<?php

namespace App\Services\Mail;

use App\Models\ContactField;
use App\Models\MailerConnection;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renders campaign email content by replacing recipient placeholders.
 *
 * Supports the built-in `name`, `email` and `phone` placeholders plus any
 * custom contact field defined for the recipient's team. Matching is
 * case-insensitive and tolerant of optional surrounding whitespace; unknown
 * placeholders resolve to an empty string.
 *
 * Contact values are untrusted text, often from a CSV import, so they are
 * HTML-escaped when rendered into a body and substituted as-is only into
 * plain text such as the subject line.
 *
 * When rendered for a connection, `signature` resolves to that connection's
 * signature HTML (or the team default), which is trusted markup and inserted
 * unescaped. A paragraph holding only the tag is replaced whole, so the
 * signature's own paragraphs are not nested in it.
 */
class PlaceholderRenderer
{
    private const string SIGNATURE_PARAGRAPH = '<p\b[^>]*>\s*\{\{\s*signature\s*\}\}\s*<\/p>';

    /**
     * The contact fields already read, keyed by team id.
     *
     * A send renders the subject and the body for the same recipient, so the
     * team's fields are read once per renderer rather than once per render.
     * The renderer is resolved per job, so a field added later is still seen
     * by the next send.
     *
     * @var array<int, Collection<int, ContactField>>
     */
    private array $fields = [];

    /**
     * Render the given HTML for a single recipient.
     */
    public function render(string $html, Recipient $recipient, ?MailerConnection $connection = null): string
    {
        $values = array_map(e(...), $this->valuesFor($recipient));

        if ($connection === null) {
            return $this->replace($html, $values);
        }

        $values['signature'] = (string) $connection->resolveSignature()?->body;

        return (string) preg_replace_callback(
            '/'.self::SIGNATURE_PARAGRAPH.'|'.MergeFields::PATTERN.'/i',
            static fn (array $matches): string => isset($matches[1])
                ? $values[strtolower($matches[1])] ?? ''
                : $values['signature'],
            $html,
        );
    }

    /**
     * Render plain text, such as a subject line, for a single recipient.
     */
    public function renderText(string $text, Recipient $recipient): string
    {
        return $this->replace($text, $this->valuesFor($recipient));
    }

    /**
     * Replace every placeholder in the content with its value.
     *
     * @param  array<string, string>  $values
     */
    private function replace(string $content, array $values): string
    {
        return (string) preg_replace_callback(
            '/'.MergeFields::PATTERN.'/',
            static fn (array $matches): string => $values[strtolower($matches[1])] ?? '',
            $content,
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

        foreach ($this->fieldsFor($contact->team_id) as $field) {
            $values[strtolower($field->name)] = (string) ($contact->propertyValue($field->id) ?? '');
        }

        return $values;
    }

    /**
     * Get the contact fields defined for the team.
     *
     * @return Collection<int, ContactField>
     */
    private function fieldsFor(int $teamId): Collection
    {
        return $this->fields[$teamId] ??= ContactField::query()
            ->where('team_id', $teamId)
            ->get();
    }
}
