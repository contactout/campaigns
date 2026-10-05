<?php

namespace App\Data;

/**
 * Outcome of a successful campaign send.
 *
 * Providers that expose a Message-ID and/or conversation thread id populate
 * these fields so later steps can reply in the same conversation. SMTP returns
 * the Message-ID it generated and no thread id, because it has no provider
 * thread to attach to.
 */
readonly class SendResult
{
    /**
     * Create a new send result instance.
     */
    public function __construct(
        public ?string $messageId = null,
        public ?string $threadId = null,
        public ?string $replyToId = null,
    ) {}
}
