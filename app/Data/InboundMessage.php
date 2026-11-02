<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * A normalized inbound email message read from a mailer connection's mailbox.
 *
 * The shape is provider-agnostic so reply and bounce processing never depends
 * on the underlying mailbox implementation.
 */
readonly class InboundMessage
{
    /**
     * Create a new inbound message instance.
     *
     * @param  array<int, string>  $references
     * @param  CarbonImmutable|null  $receivedAt  When the mailbox received it, if the provider says.
     * @param  string|null  $mailboxId  The provider's id for the message, used to skip it when a read resumes.
     */
    public function __construct(
        public string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public ?string $fromEmail,
        public string $subject,
        public ?string $text,
        public ?CarbonImmutable $receivedAt = null,
        public ?string $mailboxId = null,
    ) {}

    /**
     * Get the id a resumed read uses to recognise this message.
     */
    public function resumeId(): string
    {
        return $this->mailboxId ?? $this->messageId;
    }
}
