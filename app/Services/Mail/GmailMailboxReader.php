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
     * Gmail search also covers the Sent folder, so the connection's own mail
     * is excluded: a threaded follow-up carries `In-Reply-To` for the previous
     * step and would otherwise be recorded as the recipient's reply.
     *
     * Every result page is read, and API failures are thrown rather than
     * returned as an empty mailbox, so the caller never moves its cursor past
     * messages that were not fetched.
     *
     * @return array<int, InboundMessage>
     *
     * @throws RuntimeException When the token or Gmail API request fails.
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

        $accessToken = $this->tokens->accessToken($connection);
        $query = ($since instanceof CarbonInterface
            ? 'after:'.$since->getTimestamp()
            : 'is:unread').' -from:me';

        $ids = [];
        $pageToken = null;

        do {
            $page = $this->gmail->listMessageIds($accessToken, $query, $pageToken);
            array_push($ids, ...$page['ids']);
            $pageToken = $page['nextPageToken'];
        } while ($pageToken !== null);

        $inbound = [];

        foreach ($ids as $id) {
            $message = $this->gmail->getMessage($accessToken, $id);

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
