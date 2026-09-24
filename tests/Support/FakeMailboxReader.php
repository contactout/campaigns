<?php

namespace Tests\Support;

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
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
     * @var array<int, array{connection: MailerConnection, since: CarbonInterface|null}>
     */
    public array $fetched = [];

    /**
     * @param  array<int, InboundMessage>  $messages
     */
    public function __construct(array $messages = [])
    {
        $this->messages = $messages;
    }

    /**
     * Record the call and return the queued messages.
     *
     * @return array<int, InboundMessage>
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since): array
    {
        $this->fetched[] = ['connection' => $connection, 'since' => $since];

        return $this->messages;
    }
}
