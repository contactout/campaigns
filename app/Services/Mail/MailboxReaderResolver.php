<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailboxReader;
use App\Data\MailboxBatch;
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
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit): MailboxBatch
    {
        return match ($connection->mailer_type) {
            MailerType::Gmail => $this->gmail->fetch($connection, $since, $limit),
            MailerType::Outlook => $this->outlook->fetch($connection, $since, $limit),
            MailerType::Smtp => $this->imap->fetch($connection, $since, $limit),
        };
    }
}
