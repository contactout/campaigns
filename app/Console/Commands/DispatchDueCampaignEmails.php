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
     * Connections with a minimum interval between sends stay out of the batch,
     * so their backlog cannot crowd out other connections. Each of them gets
     * its oldest due email instead, once its last send is outside the interval.
     */
    public function handle(): int
    {
        $spaced = MailerConnection::query()
            ->whereNotNull('min_seconds_between_sends')
            ->get();

        $emails = $this->dueEmails()
            ->when($spaced->isNotEmpty(), fn (Builder $query): Builder => $this->withoutConnections($query, $spaced->modelKeys()))
            ->limit(self::BATCH_SIZE)
            ->get();

        $spaced
            ->filter(fn (MailerConnection $connection): bool => $this->mayDispatch($connection))
            ->each(function (MailerConnection $connection) use ($emails): void {
                $email = $this->dueEmails()
                    ->where(fn (Builder $query): Builder => $this->forConnection($query, $connection->id))
                    ->first();

                if ($email instanceof CampaignEmail) {
                    $emails->push($email);
                }
            });

        if ($emails->isEmpty()) {
            $this->info('No campaign emails are due.');

            return self::SUCCESS;
        }

        $emails->each(fn (CampaignEmail $email) => SendEmail::dispatch($email));

        $this->info(sprintf('Dispatched %d campaign email(s).', $emails->count()));

        return self::SUCCESS;
    }

    /**
     * Build the query for due, scheduled emails of active campaigns, oldest first.
     *
     * @return Builder<CampaignEmail>
     */
    private function dueEmails(): Builder
    {
        return CampaignEmail::query()
            ->with(['campaign', 'step', 'recipient.contact', 'mailerConnection'])
            ->where('status', EmailStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->whereHas('campaign', fn (Builder $query): Builder => $query->where('status', CampaignStatus::Active))
            ->orderBy('scheduled_at');
    }

    /**
     * Leave out emails sent through any of the given connections.
     *
     * An email without its own connection sends through its campaign's.
     *
     * @param  Builder<CampaignEmail>  $query
     * @param  array<int, int>  $connectionIds
     * @return Builder<CampaignEmail>
     */
    private function withoutConnections(Builder $query, array $connectionIds): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereNotIn('mailer_connection_id', $connectionIds)
            ->orWhere(fn (Builder $query): Builder => $query
                ->whereNull('mailer_connection_id')
                ->whereHas('campaign', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                    ->whereNull('mailer_connection_id')
                    ->orWhereNotIn('mailer_connection_id', $connectionIds)))));
    }

    /**
     * Limit the query to emails sent through the given connection.
     *
     * @param  Builder<CampaignEmail>  $query
     * @return Builder<CampaignEmail>
     */
    private function forConnection(Builder $query, int $connectionId): Builder
    {
        return $query
            ->where('mailer_connection_id', $connectionId)
            ->orWhere(fn (Builder $query): Builder => $query
                ->whereNull('mailer_connection_id')
                ->whereHas('campaign', fn (Builder $query): Builder => $query->where('mailer_connection_id', $connectionId)));
    }

    /**
     * Determine whether the connection's last send is outside its minimum interval.
     */
    private function mayDispatch(MailerConnection $connection): bool
    {
        return $connection->last_sent_at === null
            || ! $connection->last_sent_at->addSeconds((int) $connection->min_seconds_between_sends)->isFuture();
    }
}
