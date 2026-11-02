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
     * @var array<int, array{connection: MailerConnection, since: CarbonInterface|null, limit: int}>
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
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit): MailboxBatch
    {
        $this->fetched[] = ['connection' => $connection, 'since' => $since, 'limit' => $limit];

        return new MailboxBatch($this->messages, $this->hasMore);
    }
}
