<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\RecipientStatus;
use App\Jobs\Campaigns\SeedCampaignEmails;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Recipient;
use Illuminate\Support\Facades\DB;

class AddCampaignRecipients
{
    /**
     * Add the given contacts and list contacts as recipients of the campaign.
     *
     * Contacts that are already recipients of the campaign are skipped. The
     * source is recorded as "manual" whenever explicit contacts were provided,
     * otherwise "list".
     *
     * Adding recipients to a running campaign queues their first step; a draft
     * or stopped campaign keeps them queued for its next start.
     *
     * @param  array<int, int|string>  $contactIds
     * @param  array<int, int|string>  $listIds
     * @return int the number of recipients created
     */
    public function handle(Campaign $campaign, array $contactIds, array $listIds): int
    {
        $created = DB::transaction(function () use ($campaign, $contactIds, $listIds): int {
            $contactIds = array_values(array_unique(array_map(intval(...), $contactIds)));
            $listIds = array_values(array_unique(array_map(intval(...), $listIds)));

            $candidateIds = array_values(array_unique([
                ...$contactIds,
                ...$this->contactIdsForLists($listIds),
            ]));

            if ($candidateIds === []) {
                return 0;
            }

            $existingIds = $campaign->recipients()
                ->whereIn('contact_id', $candidateIds)
                ->pluck('contact_id')
                ->map(intval(...))
                ->all();

            $newIds = array_values(array_diff($candidateIds, $existingIds));

            if ($newIds === []) {
                return 0;
            }

            $source = $contactIds !== [] ? 'manual' : 'list';
            $now = now();

            Recipient::query()->insert(array_map(fn (int $contactId): array => [
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'timezone' => $campaign->timezone,
                'status' => RecipientStatus::Active->value,
                'source' => $source,
                'sequence' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], $newIds));

            return count($newIds);
        });

        if ($created > 0 && $campaign->status === CampaignStatus::Active) {
            SeedCampaignEmails::dispatch($campaign)->afterCommit();
        }

        return $created;
    }

    /**
     * Resolve the non-trashed contact ids that belong to the given lists.
     *
     * @param  array<int, int>  $listIds
     * @return array<int, int>
     */
    private function contactIdsForLists(array $listIds): array
    {
        if ($listIds === []) {
            return [];
        }

        return Contact::query()
            ->whereIn('id', function ($query) use ($listIds): void {
                $query->select('contact_id')
                    ->from('contact_list')
                    ->whereIn('contact_list_id', $listIds);
            })
            ->pluck('id')
            ->map(intval(...))
            ->all();
    }
}
