<?php

namespace App\Services\Mail;

use App\Contracts\Mail\GmailApi;
use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Data\MailboxBatch;
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
     * Gmail search only has second precision and may treat `after:` as
     * exclusive, so the window starts a second early and `before:` rounds up:
     * a little overlap is returned, but nothing inside the window is missed.
     *
     * Gmail lists newest first, so every page of ids is listed (ids are
     * cheap) and only the oldest `$limit` messages are fetched, letting a
     * backlog drain in order across polls. A message deleted after it was
     * listed is skipped; any other API failure is thrown rather than returned
     * as an empty mailbox, so the caller never moves its cursor past messages
     * that were not fetched.
     *
     * @throws RuntimeException When the token or Gmail API request fails.
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since, ?int $limit, ?CarbonInterface $before = null): MailboxBatch
    {
        if ($connection->mailer_type !== MailerType::Gmail) {
            return new MailboxBatch([]);
        }

        $settings = $connection->smtp_setting ?? [];

        if (empty($settings['access_token']) && empty($settings['refresh_token'])) {
            return new MailboxBatch([]);
        }

        $accessToken = $this->tokens->accessToken($connection);
        $query = ($since instanceof CarbonInterface
            ? 'after:'.($since->getTimestamp() - 1)
            : 'is:unread').' -from:me';

        if ($before instanceof CarbonInterface) {
            $query .= ' before:'.($before->getTimestamp() + ($before->micro > 0 ? 1 : 0));
        }

        $ids = [];
        $pageToken = null;

        do {
            $page = $this->gmail->listMessageIds($accessToken, $query, $pageToken, 500);
            array_push($ids, ...$page['ids']);
            $pageToken = $page['nextPageToken'];
        } while ($pageToken !== null);

        $hasMore = $limit !== null && count($ids) > $limit;
        $oldest = array_reverse($hasMore ? array_slice($ids, -$limit) : $ids);
        $inbound = [];

        foreach ($oldest as $id) {
            $message = $this->gmail->getMessage($accessToken, $id);

            if ($message === null) {
                continue;
            }

            $inbound[] = new InboundMessage(
                messageId: $message['messageId'],
                inReplyTo: $message['inReplyTo'],
                references: $message['references'],
                fromEmail: $message['fromEmail'],
                subject: $message['subject'],
                text: $message['text'],
                receivedAt: $message['receivedAt'],
            );
        }

        return new MailboxBatch($inbound, $hasMore);
    }
}
