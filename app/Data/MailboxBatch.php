<?php

namespace App\Data;

/**
 * One bounded read of a mailbox: the oldest unread messages in the window.
 *
 * When `hasMore` is set the window held more messages than were returned, so
 * the caller must resume after the newest returned message instead of
 * skipping to the end of the window.
 */
readonly class MailboxBatch
{
    /**
     * Create a new mailbox batch instance.
     *
     * @param  array<int, InboundMessage>  $messages  Oldest first.
     */
    public function __construct(
        public array $messages,
        public bool $hasMore = false,
    ) {}
}
