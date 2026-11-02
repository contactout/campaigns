<?php

namespace Tests\Support;

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Data\MailboxBatch;
use App\Models\MailerConnection;
use Carbon\CarbonInterface;

/**
 * In-memory mailbox that applies the reader contract's window and limit.
 *
 * Unlike {@see FakeMailboxReader}, each poll is answered from the same stored
 * mailbox by received time, the way a provider would, so tests can prove that
 * a cursor drains every message across several polls.
 */
class SimulatedMailboxReader implements MailboxReader
{
    /**
     * @param  array<int, InboundMessage>  $mailbox  Every message, each with a received time.
     */
    public function __construct(public array $mailbox) {}

    /**
     * Return the oldest messages in the window, keeping ties in mailbox order.
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since, ?int $limit, ?CarbonInterface $before = null): MailboxBatch
    {
        $window = array_values(array_filter(
            $this->mailbox,
            fn (InboundMessage $message): bool => $message->receivedAt !== null
                && ($since === null || $message->receivedAt->greaterThanOrEqualTo($since))
                && ($before === null || $message->receivedAt->lessThan($before)),
        ));

        usort($window, fn (InboundMessage $a, InboundMessage $b): int => $a->receivedAt <=> $b->receivedAt);

        if ($limit === null || count($window) <= $limit) {
            return new MailboxBatch($window);
        }

        return new MailboxBatch(array_slice($window, 0, $limit), hasMore: true);
    }
}
