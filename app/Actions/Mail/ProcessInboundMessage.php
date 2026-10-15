<?php

namespace App\Actions\Mail;

use App\Data\InboundMessage;
use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Enums\RecipientStatus;
use App\Models\CampaignEmail;
use App\Models\MailerConnection;
use App\Services\Mail\SendFailureClassifier;
use App\Services\Mail\SuppressionList;
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
     * Create a new action instance.
     */
    public function __construct(
        private readonly SuppressionList $suppressionList,
        private readonly SendFailureClassifier $classifier,
    ) {}

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
     * Mark the campaign email and recipient tied to a bounce report.
     *
     * Only a permanent failure also bounces the contact and adds its address
     * to the team's suppression list; a delay or other soft failure stops this
     * campaign but must not block the contact everywhere.
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

        if (! $this->isPermanentFailure($message)) {
            return true;
        }

        $contact = $recipient->contact;
        $contact->status = ContactStatus::Bounced;
        $contact->save();

        $this->suppressionList->add(
            $email->campaign->team_id,
            (string) ($contact->email() ?? ''),
            'bounce',
            $email->campaign_id,
            $recipient->id,
        );

        return true;
    }

    /**
     * Determine whether the bounce report carries a permanent failure.
     *
     * The code must sit where DSNs put it: a `Status: 5.x.x` line, a 55x reply
     * followed by its 5.x.x code, or a bare 55x reply opening a line or a
     * `smtp;` diagnostic. Only the report itself is read, not the quoted copy
     * of our message, so numbers in quoted text such as phone numbers,
     * version strings or a signature's street address do not count.
     *
     * A delay report never counts: a `Status: 4.x.x` or `Action: delayed`
     * line rules out the whole report.
     *
     * Refusals aimed at the sender and full mailboxes, which usually clear, do
     * not condemn the address: 5.7.x, 5.2.2 and bare 552 replies never count.
     * A reply carrying a qualifying enhanced code, such as 5.1.1, is judged by
     * that code alone and wins over any other line. Otherwise the bare replies
     * decide, and one that {@see SendFailureClassifier} reads as a sender
     * policy refusal, such as a relay denial, or that blames spam, a blocklist,
     * SPF or DMARC rules the report out.
     */
    private function isPermanentFailure(InboundMessage $message): bool
    {
        $report = $this->reportText($message);

        if (preg_match('/^\s*(?:Status:\s*4\.\d{1,3}\.\d{1,3}\b|Action:\s*delayed\b)/im', $report) === 1) {
            return false;
        }

        $code = '5\.(?!7\.|2\.2(?!\d))\d{1,3}\.\d{1,3}\b';
        $bare = '^\s*(?:(?:Diagnostic-Code:\s*)?smtp;\s*)?(?!552)55\d[ -](?!\d)';

        preg_match_all(
            '/^\s*Status:\s*'.$code.'.*$|\b55\d[ -]'.$code.'.*$|'.$bare.'.*$/im',
            $report,
            $matches,
        );

        if ($matches[0] === []) {
            return false;
        }

        foreach ($matches[0] as $reply) {
            if (preg_match('/(?<![\d.])'.$code.'/', $reply) === 1) {
                return true;
            }
        }

        foreach ($matches[0] as $reply) {
            if ($this->classifier->isSmtpPolicyRejection($reply)
                || preg_match('/spam|\b(?:block|black|deny|ban)\s?-?list|\bspf\b|\bdmarc\b/i', $reply) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the bounce report's own text, cut before the quoted copy of the
     * original message.
     */
    private function reportText(InboundMessage $message): string
    {
        $parts = preg_split(
            '/^\s*(?:-{2,}.*\b(?:original|copy of the|undelivered|returned)\b.*message|Original message headers:|Content-Type:\s*message\/rfc822)/im',
            $message->text ?? '',
            2,
        );

        return $parts === false ? '' : $parts[0];
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
