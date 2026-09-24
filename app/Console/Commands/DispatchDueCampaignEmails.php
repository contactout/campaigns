<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Enums\EmailStatus;
use App\Jobs\Campaigns\SendEmail;
use App\Models\CampaignEmail;
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
     */
    public function handle(): int
    {
        $emails = CampaignEmail::query()
            ->with(['campaign', 'step', 'recipient.contact', 'mailerConnection'])
            ->where('status', EmailStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->whereHas('campaign', fn (Builder $query): Builder => $query->where('status', CampaignStatus::Active))
            ->orderBy('scheduled_at')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($emails->isEmpty()) {
            $this->info('No campaign emails are due.');

            return self::SUCCESS;
        }

        $emails->each(fn (CampaignEmail $email) => SendEmail::dispatch($email));

        $this->info(sprintf('Dispatched %d campaign email(s).', $emails->count()));

        return self::SUCCESS;
    }
}
