<?php

namespace App\Data;

/**
 * Outcome of a successful campaign send.
 *
 * Providers that expose a Message-ID and/or conversation thread id populate
 * these fields so reply matching can use them later. SMTP may leave both null.
 */
readonly class SendResult
{
    /**
     * Create a new send result instance.
     */
    public function __construct(
        public ?string $messageId = null,
        public ?string $threadId = null,
    ) {}
}
