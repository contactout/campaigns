<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Enums\EmailStatus;
use App\Jobs\Campaigns\SendEmail;
use App\Models\CampaignEmail;
use App\Models\MailerConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('campaigns:dispatch-due')]
#[Description('Dispatch scheduled campaign emails that are due')]
class DispatchDueCampaignEmails extends Command
{
    /**
     * The maximum number of emails to dispatch per run.
     */
    private const int BATCH_SIZE = 200;

    /**
     * Dispatch every due scheduled email belonging to an active campaign.
     *
     * A connection with a minimum interval between sends gets at most one email
     * per run, and none while its last send is still within the interval.
     */
    public function handle(): int
    {
        $spaced = $this->connectionsWithinInterval();

        $emails = CampaignEmail::query()
            ->with(['campaign.mailerConnection', 'step', 'recipient.contact', 'mailerConnection'])
            ->where('status', EmailStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->whereHas('campaign', fn (Builder $query): Builder => $query->where('status', CampaignStatus::Active))
            // Keep held-back emails out of the batch so they cannot crowd out other connections.
            ->when($spaced !== [], fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->whereNull('mailer_connection_id')
                    ->orWhereNotIn('mailer_connection_id', array_keys($spaced)),
            ))
            ->orderBy('scheduled_at')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($emails->isEmpty()) {
            $this->info('No campaign emails are due.');

            return self::SUCCESS;
        }

        $dispatched = $emails->filter(function (CampaignEmail $email) use (&$spaced): bool {
            $connection = $email->mailerConnection ?? $email->campaign->mailerConnection;

            if ($connection?->min_seconds_between_sends === null) {
                return true;
            }

            if (isset($spaced[$connection->id])) {
                return false;
            }

            $spaced[$connection->id] = true;

            return true;
        });

        $dispatched->each(fn (CampaignEmail $email) => SendEmail::dispatch($email));

        $this->info(sprintf('Dispatched %d campaign email(s).', $dispatched->count()));

        return self::SUCCESS;
    }

    /**
     * Get the ids of connections whose last send is still within their minimum interval.
     *
     * @return array<int, true>
     */
    private function connectionsWithinInterval(): array
    {
        return MailerConnection::query()
            ->whereNotNull('min_seconds_between_sends')
            ->whereNotNull('last_sent_at')
            ->get()
            ->filter(fn (MailerConnection $connection): bool => $connection->last_sent_at
                ->addSeconds((int) $connection->min_seconds_between_sends)
                ->isFuture())
            ->mapWithKeys(fn (MailerConnection $connection): array => [$connection->id => true])
            ->all();
    }
}
