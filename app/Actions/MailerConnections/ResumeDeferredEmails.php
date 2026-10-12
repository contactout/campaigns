<?php

namespace App\Actions\MailerConnections;

use App\Data\CampaignSettings;
use App\Enums\EmailStatus;
use App\Jobs\Campaigns\SendEmail;
use App\Models\CampaignEmail;
use App\Models\MailerConnection;
use App\Services\Mail\CampaignStepScheduler;
use App\Services\Mail\SendingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class ResumeDeferredEmails
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly CampaignStepScheduler $scheduler,
        private readonly SendingWindow $window,
    ) {}

    /**
     * Bring forward the emails held back while the connection was inactive.
     *
     * Each email is rescheduled for the next moment its campaign's sending
     * window allows from now, and its deferral flag is cleared.
     *
     * @return int The number of emails resumed.
     */
    public function handle(MailerConnection $connection): int
    {
        $resumed = 0;

        CampaignEmail::query()
            ->with(['campaign', 'recipient'])
            ->where('status', EmailStatus::Scheduled)
            ->where('data->'.SendEmail::DEFERRED_FOR_CONNECTION, $connection->id)
            ->chunkById(200, function (Collection $emails) use (&$resumed): void {
                $now = CarbonImmutable::now();

                foreach ($emails as $email) {
                    /** @var CampaignEmail $email */
                    $data = $email->data ?? [];
                    unset($data[SendEmail::DEFERRED_FOR_CONNECTION]);

                    $email->data = $data;
                    $email->scheduled_at = $this->window->nextAllowedAt(
                        $now,
                        $this->scheduler->timezoneFor($email->campaign, $email->recipient),
                        CampaignSettings::fromArray($email->campaign->settings),
                    );
                    $email->save();

                    $resumed++;
                }
            });

        return $resumed;
    }
}
