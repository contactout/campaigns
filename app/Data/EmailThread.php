<?php

namespace App\Data;

/**
 * Provider details needed to send an email as a reply in an existing thread.
 *
 * Built from the previous sent email for a recipient when the step has
 * `is_threaded` on. Providers consume what they need: SMTP and Gmail use
 * {@see self::$messageId} for the `In-Reply-To`/`References` headers, Gmail and
 * Outlook use {@see self::$threadId} to attach to a provider conversation, and
 * Outlook uses {@see self::$replyToId} to create the reply on the original
 * message.
 */
readonly class EmailThread
{
    /**
     * Create a new email thread context.
     */
    public function __construct(
        public ?string $messageId = null,
        public ?string $threadId = null,
        public ?string $replyToId = null,
    ) {}

    /**
     * Determine whether any provider detail is available to thread with.
     *
     * With nothing to reply to the email must start a new conversation.
     */
    public function isThreaded(): bool
    {
        return filled($this->messageId) || filled($this->threadId) || filled($this->replyToId);
    }
}
