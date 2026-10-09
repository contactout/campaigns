<?php

namespace App\Actions\Mail;

use App\Data\InboundMessage;
use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Enums\RecipientStatus;
use App\Models\CampaignEmail;
use App\Models\MailerConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Records replies and bounces from inbound mailbox messages.
 *
 * A message is matched either as a bounce report about an email we sent (by
 * Message-ID or failed recipient address) or as a reply to one (via the
 * `In-Reply-To`/`References` headers). Unmatched messages are ignored.
 */
class ProcessInboundMessage
{
    /**
     * Process a single inbound message for the given connection.
     *
     * A bounce report that matches no sent email falls through to reply
     * matching, so a human reply that merely looks like a bounce is kept.
     */
    public function handle(MailerConnection $connection, InboundMessage $message): void
    {
        DB::transaction(function () use ($connection, $message): void {
            // Bounce reports usually carry In-Reply-To/References pointing at
            // the original email, so they must be checked before replies or
            // they would be recorded as one.
            if ($this->looksLikeBounce($message) && $this->markBounced($connection, $message)) {
                return;
            }

            $reply = $this->findReply($connection, $message);

            if ($reply instanceof CampaignEmail) {
                $this->markReplied($reply);
            }
        });
    }

    /**
     * Find the campaign email a reply is answering, if any.
     */
    private function findReply(MailerConnection $connection, InboundMessage $message): ?CampaignEmail
    {
        $inReplyTo = $message->inReplyTo;
        $references = $message->references;

        if (($inReplyTo === null || $inReplyTo === '') && $references === []) {
            return null;
        }

        return CampaignEmail::query()
            ->where('mailer_connection_id', $connection->id)
            ->where(function (Builder $query) use ($inReplyTo, $references): void {
                if ($inReplyTo !== null && $inReplyTo !== '') {
                    $query->where('message_id', $inReplyTo);
                }

                if ($references !== []) {
                    $query->orWhereIn('message_id', $references);
                }
            })
            ->first();
    }

    /**
     * Mark the replied email, recipient and contact as replied.
     */
    private function markReplied(CampaignEmail $email): void
    {
        $now = now();

        $email->status = EmailStatus::Replied;
        $email->replied_at = $now;
        $email->save();

        $recipient = $email->recipient;
        $recipient->status = RecipientStatus::Replied;
        $recipient->last_responded_at = $now;
        $recipient->save();

        $contact = $recipient->contact;
        $contact->status = ContactStatus::Replied;
        $contact->last_responded_at = $now;
        $contact->save();
    }

    /**
     * Determine whether the message reports a delivery failure.
     */
    private function looksLikeBounce(InboundMessage $message): bool
    {
        $from = strtolower($message->fromEmail ?? '');

        if (str_contains($from, 'mailer-daemon') || str_contains($from, 'postmaster')) {
            return true;
        }

        // A person replying to a step whose subject contains these words would
        // otherwise be bounced, since bounces are checked before replies.
        if (preg_match('/^\s*(re|fwd?|aw|sv)\s*:/i', $message->subject) === 1) {
            return false;
        }

        return preg_match(
            '/undeliver|delivery status|returned to sender|failure notice|delivery has failed/i',
            $message->subject,
        ) === 1;
    }

    /**
     * Mark the campaign email, recipient and contact tied to a bounce report.
     *
     * @return bool Whether a bounced email was found.
     */
    private function markBounced(MailerConnection $connection, InboundMessage $message): bool
    {
        $email = $this->findBouncedEmail($connection, $message);

        if (! $email instanceof CampaignEmail) {
            return false;
        }

        $email->status = EmailStatus::Bounced;
        $email->save();

        $recipient = $email->recipient;
        $recipient->status = RecipientStatus::Bounced;
        $recipient->save();

        $contact = $recipient->contact;
        $contact->status = ContactStatus::Bounced;
        $contact->save();

        return true;
    }

    /**
     * Find the sent email a bounce report is about.
     *
     * The original message is matched by Message-ID first, from the report's
     * threading headers or any Message-ID quoted in its text, then by the
     * failed recipient address. Only emails this connection actually sent are
     * considered, latest first, so a contact in several campaigns resolves to
     * the same email every time.
     */
    private function findBouncedEmail(MailerConnection $connection, InboundMessage $message): ?CampaignEmail
    {
        $messageIds = $this->referencedMessageIds($message);

        if ($messageIds !== []) {
            $email = $this->sentEmails($connection)
                ->whereIn('message_id', $messageIds)
                ->first();

            if ($email instanceof CampaignEmail) {
                return $email;
            }
        }

        $failedEmail = $this->extractFailedAddress($connection, $message);

        if ($failedEmail === null) {
            return null;
        }

        return $this->sentEmails($connection)
            ->whereHas('recipient.contact.identities', fn (Builder $query): Builder => $query
                ->where('identity_type', ContactIdentityType::Email->value)
                ->where('normalized_value', $failedEmail))
            ->first();
    }

    /**
     * Query the emails the connection has sent, latest first.
     *
     * @return Builder<CampaignEmail>
     */
    private function sentEmails(MailerConnection $connection): Builder
    {
        return CampaignEmail::query()
            ->where('mailer_connection_id', $connection->id)
            ->whereNotNull('dispatched_at')
            ->orderByDesc('dispatched_at')
            ->orderByDesc('id');
    }

    /**
     * Collect the Message-IDs a bounce report refers to.
     *
     * Ids are kept both with and without angle brackets, since stored ids and
     * headers do not agree on the format across providers.
     *
     * @return array<int, string>
     */
    private function referencedMessageIds(InboundMessage $message): array
    {
        $ids = $message->references;

        if ($message->inReplyTo !== null && $message->inReplyTo !== '') {
            $ids[] = $message->inReplyTo;
        }

        if (preg_match_all('/^\s*Message-ID:\s*(\S+)/im', $message->text ?? '', $matches) > 0) {
            array_push($ids, ...$matches[1]);
        }

        $variants = [];

        foreach ($ids as $id) {
            $bare = trim($id, '<> ');

            if ($bare !== '') {
                $variants[] = $bare;
                $variants[] = '<'.$bare.'>';
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Extract the first bounced address that is not the connection's own
     * `from` address.
     */
    private function extractFailedAddress(MailerConnection $connection, InboundMessage $message): ?string
    {
        $text = $message->text ?? '';

        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches) === 0) {
            return null;
        }

        $ownAddress = strtolower((string) (($connection->smtp_setting['from_email'] ?? '')));

        foreach ($matches[0] as $candidate) {
            $candidate = strtolower($candidate);

            if ($candidate !== $ownAddress) {
                return $candidate;
            }
        }

        return null;
    }
}
