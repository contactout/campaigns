<?php

namespace Tests\Support;

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Data\MailboxBatch;
use App\Models\MailerConnection;
use Carbon\CarbonInterface;

/**
 * In-memory mailbox reader used to exercise reply/bounce processing without IMAP.
 */
class FakeMailboxReader implements MailboxReader
{
    /**
     * @var array<int, InboundMessage>
     */
    public array $messages = [];

    /**
     * @var array<int, array{connection: MailerConnection, since: CarbonInterface|null, limit: int, before: CarbonInterface|null, skip: array<int, string>}>
     */
    public array $fetched = [];

    /**
     * @param  array<int, InboundMessage>  $messages
     * @param  bool  $hasMore  Whether to report the batch as truncated.
     */
    public function __construct(array $messages = [], public bool $hasMore = false)
    {
        $this->messages = $messages;
    }

    /**
     * Record the call and return the queued messages.
     *
     * @param  array<int, string>  $skip
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit, ?CarbonInterface $before = null, array $skip = []): MailboxBatch
    {
        $this->fetched[] = ['connection' => $connection, 'since' => $since, 'limit' => $limit, 'before' => $before, 'skip' => $skip];

        return new MailboxBatch($this->messages, $this->hasMore);
    }
}
