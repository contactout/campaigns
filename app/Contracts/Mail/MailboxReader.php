<?php

namespace App\Contracts\Mail;

use App\Data\InboundMessage;
use App\Models\MailerConnection;
use Carbon\CarbonInterface;

/**
 * Reads inbound messages from a mailer connection's mailbox.
 *
 * Implementations talk to the provider's mailbox (for example IMAP); callers
 * only supply the connection and the point in time to read from.
 */
interface MailboxReader
{
    /**
     * Fetch inbound messages received after the given time.
     *
     * When no `$since` is supplied, implementations should read unseen
     * messages instead.
     *
     * @return array<int, InboundMessage>
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since): array;
}
