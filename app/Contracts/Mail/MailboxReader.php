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
     * messages instead. Implementations must return every matching message,
     * paging through the provider as needed, and must throw rather than
     * return a partial or empty result when the provider fails: the caller
     * advances its read cursor past whatever window this call covered.
     *
     * @return array<int, InboundMessage>
     *
     * @throws \Throwable When the mailbox cannot be read.
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since): array;
}
