<?php

namespace App\Concerns;

use App\Data\EmailThread;
use Symfony\Component\Mime\Email;

/**
 * Adds the RFC 5322 headers that keep a message in a previous conversation.
 *
 * Used by the mailers that build a MIME message themselves; the Outlook API
 * threads by creating a reply instead of by headers.
 */
trait AppliesThreadHeaders
{
    /**
     * Point the message at the previous message in the conversation.
     */
    private function applyThreadHeaders(Email $email, ?EmailThread $thread): void
    {
        if (! $thread instanceof EmailThread) {
            return;
        }

        $messageId = $thread->messageId;

        if ($messageId === null || $messageId === '') {
            return;
        }

        $messageId = '<'.trim($messageId, '<>').'>';

        $email->getHeaders()->addTextHeader('In-Reply-To', $messageId);
        $email->getHeaders()->addTextHeader('References', $messageId);
    }
}
