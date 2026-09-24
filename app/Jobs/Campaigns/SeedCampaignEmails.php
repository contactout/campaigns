<?php

namespace App\Jobs\Campaigns;

use App\Models\Campaign;
use App\Services\Mail\CampaignStepScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SeedCampaignEmails implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Campaign $campaign) {}

    /**
     * Create the first-step emails for every eligible recipient.
     */
    public function handle(CampaignStepScheduler $scheduler): void
    {
        $scheduler->scheduleFirstSteps($this->campaign);
    }
}
