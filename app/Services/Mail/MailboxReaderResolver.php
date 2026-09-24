<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use Carbon\CarbonInterface;

/**
 * Routes mailbox polls to the reader implementation for the connection type.
 */
class MailboxReaderResolver implements MailboxReader
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(
        private readonly ImapMailboxReader $imap,
        private readonly GmailMailboxReader $gmail,
        private readonly OutlookMailboxReader $outlook,
    ) {}

    /**
     * Fetch inbound messages through the reader matching the connection's type.
     *
     * @return array<int, InboundMessage>
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since): array
    {
        return match ($connection->mailer_type) {
            MailerType::Gmail => $this->gmail->fetch($connection, $since),
            MailerType::Outlook => $this->outlook->fetch($connection, $since),
            MailerType::Smtp => $this->imap->fetch($connection, $since),
        };
    }
}
