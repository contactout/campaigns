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
     * exclusive, so the search starts a second early and `before:` rounds up.
     * The overlap is dropped once each message's received time is known, so
     * it never takes a slot in the batch.
     *
     * Gmail lists newest first, so every page of ids is listed (ids are
     * cheap) and messages are fetched oldest first, skipping `$skip` ids
     * before fetching, until `$limit` in-window messages are in hand. A
     * message deleted after it was listed is skipped; any other API failure is
     * thrown rather than returned as an empty mailbox, so the caller never
     * moves its cursor past messages that were not fetched.
     *
     * @param  array<int, string>  $skip  Gmail message ids already processed.
     *
     * @throws RuntimeException When the token or Gmail API request fails.
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit, ?CarbonInterface $before = null, array $skip = []): MailboxBatch
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

        $skip = array_flip($skip);
        $inbound = [];
        $hasMore = false;

        foreach (array_reverse($ids) as $id) {
            if (isset($skip[$id])) {
                continue;
            }

            if (count($inbound) >= $limit) {
                $hasMore = true;

                break;
            }

            $message = $this->gmail->getMessage($accessToken, $id);

            if ($message === null || ! $this->inWindow($message['receivedAt'], $since, $before)) {
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
                mailboxId: $id,
            );
        }

        return new MailboxBatch($inbound, $hasMore);
    }

    /**
     * Determine whether a received time falls inside the requested window.
     *
     * A message without a received time is kept, since it cannot be placed.
     */
    private function inWindow(?CarbonInterface $receivedAt, ?CarbonInterface $since, ?CarbonInterface $before): bool
    {
        if ($receivedAt === null) {
            return true;
        }

        return ($since === null || $receivedAt->greaterThanOrEqualTo($since))
            && ($before === null || $receivedAt->lessThan($before));
    }
}
