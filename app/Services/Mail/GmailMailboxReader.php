<?php

namespace App\Services\Mail;

use App\Contracts\Mail\GmailApi;
use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Services\OAuth\OAuthTokenManager;
use Carbon\CarbonInterface;
use RuntimeException;
use Throwable;

/**
 * Reads inbound mail from Gmail via the Gmail API.
 */
class GmailMailboxReader implements MailboxReader
{
    /**
     * Create a new Gmail mailbox reader instance.
     */
    public function __construct(
        private readonly OAuthTokenManager $tokens,
        private readonly GmailApi $gmail,
    ) {}

    /**
     * Fetch inbox messages received after the given time, or unread messages.
     *
     * @return array<int, InboundMessage>
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since): array
    {
        if ($connection->mailer_type !== MailerType::Gmail) {
            return [];
        }

        $settings = $connection->smtp_setting ?? [];

        if (empty($settings['access_token']) && empty($settings['refresh_token'])) {
            return [];
        }

        try {
            $accessToken = $this->tokens->accessToken($connection);
            $query = $since instanceof CarbonInterface
                ? 'after:'.$since->getTimestamp()
                : 'is:unread';

            $ids = $this->gmail->listMessageIds($accessToken, $query, 50);
        } catch (Throwable) {
            return [];
        }

        $inbound = [];

        foreach ($ids as $id) {
            try {
                $message = $this->gmail->getMessage($accessToken, $id);
            } catch (RuntimeException) {
                continue;
            }

            $inbound[] = new InboundMessage(
                messageId: $message['messageId'],
                inReplyTo: $message['inReplyTo'],
                references: $message['references'],
                fromEmail: $message['fromEmail'],
                subject: $message['subject'],
                text: $message['text'],
            );
        }

        return $inbound;
    }
}
