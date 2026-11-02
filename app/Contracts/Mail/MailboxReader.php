<?php

namespace App\Contracts\Mail;

use App\Data\InboundMessage;
use App\Data\MailboxBatch;
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
     * Fetch the oldest inbound messages received in the given window.
     *
     * The window runs from `$since` (inclusive) to `$before` (exclusive) when
     * given. When no `$since` is supplied, implementations should read unseen
     * messages instead. Messages whose resume id is in `$skip` were already
     * processed and are left out.
     *
     * At most `$limit` messages are returned, oldest first, each with its
     * `receivedAt`; `hasMore` reports that the window held more.
     * Implementations must throw rather than return a partial or empty result
     * when the provider fails, since the caller moves its read cursor past
     * whatever the batch says it covered.
     *
     * @param  array<int, string>  $skip  Resume ids ({@see InboundMessage::resumeId()}) to leave out.
     *
     * @throws \Throwable When the mailbox cannot be read.
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit, ?CarbonInterface $before = null, array $skip = []): MailboxBatch;
}
