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
     *
     * Connections at their daily sending limit are left out entirely until the
     * limit refreshes, so their backlog cannot crowd out other connections
     * either. The send job moves held-back emails onto the sending window.
     */
    public function handle(): int
    {
        $spaced = MailerConnection::query()
            ->whereNotNull('min_seconds_between_sends')
            ->get();

        $capped = $this->cappedConnectionIds();
        $heldBack = array_values(array_unique([...$spaced->modelKeys(), ...$capped]));

        $emails = $this->dueEmails()
            ->when($heldBack !== [], fn (Builder $query): Builder => $this->withoutConnections($query, $heldBack))
            ->limit(self::BATCH_SIZE)
            ->get();

        $spaced
            ->reject(fn (MailerConnection $connection): bool => in_array($connection->id, $capped, true))
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
     * Get the ids of connections that have reached their daily sending limit.
     *
     * Mirrors the cap `SendEmail` enforces: `sent_count` counts towards
     * `sending_limit` until `sending_limit_refreshed_at`, after which the next
     * send starts a new day.
     *
     * @return array<int, int>
     */
    private function cappedConnectionIds(): array
    {
        return MailerConnection::query()
            ->whereNotNull('sending_limit')
            ->whereColumn('sent_count', '>=', 'sending_limit')
            ->where('sending_limit_refreshed_at', '>', now())
            ->pluck('id')
            ->all();
    }

    /**
     * Build the query for due, scheduled emails of active campaigns, oldest first.
     *
     * @return Builder<CampaignEmail>
     */
    private function dueEmails(): Builder
    {
        return CampaignEmail::query()
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
