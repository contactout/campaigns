<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Models\MailerConnection;
use Carbon\CarbonInterface;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Message;

/**
 * Reads inbound mail over IMAP using webklex/php-imap.
 *
 * The connection's encrypted SMTP settings also carry the optional IMAP
 * credentials. Connections without an `imap_host` are skipped so reply/bounce
 * polling stays a no-op until the mailbox is configured.
 */
class ImapMailboxReader implements MailboxReader
{
    /**
     * Fetch inbox messages received after the given time, or unseen messages.
     *
     * @return array<int, InboundMessage>
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since): array
    {
        $settings = $connection->smtp_setting ?? [];
        $host = trim((string) ($settings['imap_host'] ?? ''));

        if ($host === '') {
            return [];
        }

        $client = new Client(Config::make([
            'default' => 'default',
            'accounts' => [
                'default' => [
                    'host' => $host,
                    'port' => (int) ($settings['imap_port'] ?? 993),
                    'protocol' => 'imap',
                    'encryption' => $this->encryption($settings['imap_encryption'] ?? null),
                    'validate_cert' => true,
                    'username' => (string) ($settings['imap_username'] ?? ''),
                    'password' => (string) ($settings['imap_password'] ?? ''),
                ],
            ],
        ]));

        $folder = $client->connect()->getFolder('INBOX');

        if ($folder === null) {
            return [];
        }

        $query = $folder->query();

        if ($since instanceof CarbonInterface) {
            $query->whereSince($since);
        } else {
            $query->whereUnseen();
        }

        $messages = $query->leaveUnread()->get();

        $inbound = [];

        foreach ($messages as $message) {
            $inbound[] = $this->toInboundMessage($message);
        }

        return $inbound;
    }

    /**
     * Map a raw IMAP message onto the normalized inbound message shape.
     */
    private function toInboundMessage(Message $message): InboundMessage
    {
        $inReplyTo = (string) $message->getInReplyTo();
        $from = $message->getFrom()->first();

        return new InboundMessage(
            messageId: (string) $message->getMessageId(),
            inReplyTo: $inReplyTo !== '' ? $inReplyTo : null,
            references: $this->references($message),
            fromEmail: $from instanceof Address ? $from->mail : null,
            subject: (string) $message->getSubject(),
            text: $message->hasTextBody() ? $message->getTextBody() : null,
        );
    }

    /**
     * Extract the referenced message ids from the message headers.
     *
     * @return array<int, string>
     */
    private function references(Message $message): array
    {
        $references = [];

        foreach ($message->getReferences()->toArray() as $reference) {
            $reference = trim((string) $reference);

            if ($reference !== '') {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * Normalize the stored encryption value for webklex/php-imap.
     *
     * `none` maps onto the library's disabled encryption sentinel.
     */
    private function encryption(mixed $encryption): string
    {
        $encryption = strtolower((string) $encryption);

        return in_array($encryption, ['ssl', 'tls', 'starttls'], true) ? $encryption : 'none';
    }
}
