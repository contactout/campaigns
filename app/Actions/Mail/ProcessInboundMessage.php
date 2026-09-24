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
 * A message is matched either as a reply to an email we sent (via the
 * `In-Reply-To`/`References` headers) or as a bounce report naming a failed
 * recipient address. Unmatched messages are ignored.
 */
class ProcessInboundMessage
{
    /**
     * Process a single inbound message for the given connection.
     */
    public function handle(MailerConnection $connection, InboundMessage $message): void
    {
        DB::transaction(function () use ($connection, $message): void {
            $reply = $this->findReply($connection, $message);

            if ($reply instanceof CampaignEmail) {
                $this->markReplied($reply);

                return;
            }

            if ($this->looksLikeBounce($message)) {
                $this->markBounced($connection, $message);
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

        return preg_match(
            '/undeliver|delivery status|returned to sender|failure notice|delivery has failed/i',
            $message->subject,
        ) === 1;
    }

    /**
     * Mark the campaign email, recipient and contact tied to a bounced address.
     */
    private function markBounced(MailerConnection $connection, InboundMessage $message): void
    {
        $failedEmail = $this->extractFailedAddress($connection, $message);

        if ($failedEmail === null) {
            return;
        }

        $email = CampaignEmail::query()
            ->where('mailer_connection_id', $connection->id)
            ->whereHas('recipient.contact.identities', fn (Builder $query): Builder => $query
                ->where('identity_type', ContactIdentityType::Email->value)
                ->where('normalized_value', $failedEmail))
            ->first();

        if (! $email instanceof CampaignEmail) {
            return;
        }

        $email->status = EmailStatus::Bounced;
        $email->save();

        $recipient = $email->recipient;
        $recipient->status = RecipientStatus::Bounced;
        $recipient->save();

        $contact = $recipient->contact;
        $contact->status = ContactStatus::Bounced;
        $contact->save();
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
