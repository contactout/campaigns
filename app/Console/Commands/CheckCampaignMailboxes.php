<?php

namespace App\Console\Commands;

use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Jobs\Campaigns\CheckConnectionMailbox;
use App\Models\MailerConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campaigns:check-mailboxes')]
#[Description('Dispatch mailbox checks for active mailer connections')]
class CheckCampaignMailboxes extends Command
{
    /**
     * Dispatch a mailbox check for every eligible active connection.
     */
    public function handle(): int
    {
        $connections = MailerConnection::query()
            ->where('status', MailerConnectionStatus::Active)
            ->get()
            ->filter(fn (MailerConnection $connection): bool => $this->isEligible($connection));

        $connections->each(fn (MailerConnection $connection) => CheckConnectionMailbox::dispatch($connection));

        $this->info(sprintf('Dispatched %d mailbox check(s).', $connections->count()));

        return self::SUCCESS;
    }

    /**
     * Determine whether the connection can be polled for replies/bounces.
     */
    private function isEligible(MailerConnection $connection): bool
    {
        if (in_array($connection->mailer_type, [MailerType::Gmail, MailerType::Outlook], true)) {
            return true;
        }

        return $this->imapHost($connection) !== '';
    }

    /**
     * Get the configured IMAP host for a connection.
     */
    private function imapHost(MailerConnection $connection): string
    {
        return trim((string) ((($connection->smtp_setting ?? [])['imap_host'] ?? '')));
    }
}
