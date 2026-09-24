<?php

namespace App\Console\Commands;

use App\Enums\MailerConnectionStatus;
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
     * Dispatch a mailbox check for every active connection with an IMAP host.
     */
    public function handle(): int
    {
        $connections = MailerConnection::query()
            ->where('status', MailerConnectionStatus::Active)
            ->get()
            ->filter(fn (MailerConnection $connection): bool => $this->imapHost($connection) !== '');

        $connections->each(fn (MailerConnection $connection) => CheckConnectionMailbox::dispatch($connection));

        $this->info(sprintf('Dispatched %d mailbox check(s).', $connections->count()));

        return self::SUCCESS;
    }

    /**
     * Get the configured IMAP host for a connection.
     */
    private function imapHost(MailerConnection $connection): string
    {
        return trim((string) ((($connection->smtp_setting ?? [])['imap_host'] ?? '')));
    }
}
