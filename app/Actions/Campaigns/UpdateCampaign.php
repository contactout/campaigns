<?php

namespace App\Actions\Campaigns;

use App\Actions\MailerConnections\ResumeDeferredEmails;
use App\Data\CampaignSettings;
use App\Enums\EmailStatus;
use App\Jobs\Campaigns\SendEmail;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Services\Mail\CampaignStepScheduler;
use App\Services\Mail\SendingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UpdateCampaign
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly CampaignStepScheduler $scheduler,
        private readonly SendingWindow $window,
    ) {}

    /**
     * Update the given campaign.
     *
     * Unsent emails send through the campaign's connection, so moving the
     * campaign to another connection moves them with it (see
     * {@see self::repointUnsentEmails()}). Clearing the connection leaves
     * them where they are: there is nothing to move them to, and failing the
     * whole queue on an edit would lose it.
     *
     * The campaign row is locked like {@see CampaignStepScheduler::scheduleNextStep()}
     * does, so a next step is scheduled either before the switch, and then
     * moved by it, or after it on the new connection.
     *
     * @param  array{name: string, timezone: string, mailer_connection_id?: int|null, settings?: array<string, mixed>}  $data
     */
    public function handle(Campaign $campaign, array $data): Campaign
    {
        DB::transaction(function () use ($campaign, $data): void {
            Campaign::query()->lockForUpdate()->find($campaign->id);

            $campaign->fill([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
            ]);

            if (array_key_exists('settings', $data)) {
                $campaign->settings = CampaignSettings::fromArray($data['settings'])->toArray();
            }

            if (array_key_exists('mailer_connection_id', $data)) {
                $campaign->mailer_connection_id = $data['mailer_connection_id'];
            }

            $campaign->save();

            if ($campaign->wasChanged('mailer_connection_id') && $campaign->mailer_connection_id !== null) {
                $this->repointUnsentEmails($campaign);
            }
        }, CampaignStepScheduler::TRANSACTION_ATTEMPTS);

        return $campaign;
    }

    /**
     * Move the campaign's unsent emails onto its current connection.
     *
     * Emails held back for an inactive connection are no longer waiting on
     * it, so their deferral flag is cleared and they are brought forward to
     * the next moment the sending window allows, as on reactivation (see
     * {@see ResumeDeferredEmails}).
     */
    private function repointUnsentEmails(Campaign $campaign): void
    {
        $unsent = fn () => CampaignEmail::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', [EmailStatus::Pending, EmailStatus::Scheduled]);

        $unsent()->update(['mailer_connection_id' => $campaign->mailer_connection_id]);

        $settings = CampaignSettings::fromArray($campaign->settings);
        $now = CarbonImmutable::now();

        $unsent()
            ->with('recipient')
            ->whereNotNull('data->'.SendEmail::DEFERRED_FOR_CONNECTION)
            ->chunkById(200, function (Collection $emails) use ($campaign, $settings, $now): void {
                foreach ($emails as $email) {
                    /** @var CampaignEmail $email */
                    $data = $email->data ?? [];
                    unset($data[SendEmail::DEFERRED_FOR_CONNECTION]);

                    $email->data = $data;
                    $email->scheduled_at = $this->window->nextAllowedAt(
                        $now,
                        $this->scheduler->timezoneFor($campaign, $email->recipient),
                        $settings,
                    );
                    $email->save();
                }
            });
    }
}
