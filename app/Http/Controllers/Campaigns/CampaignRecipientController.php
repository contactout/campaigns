<?php

namespace App\Http\Controllers\Campaigns;

use App\Actions\Campaigns\AddCampaignRecipients;
use App\Actions\Campaigns\RemoveCampaignRecipient;
use App\Actions\Campaigns\RemoveCampaignRecipients;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\BulkDestroyCampaignRecipientsRequest;
use App\Http\Requests\Campaigns\StoreCampaignRecipientsRequest;
use App\Models\Campaign;
use App\Models\Recipient;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CampaignRecipientController extends Controller
{
    /**
     * Add contacts and list contacts as recipients of the given campaign.
     */
    public function store(StoreCampaignRecipientsRequest $request, Team $currentTeam, Campaign $campaign, AddCampaignRecipients $addCampaignRecipients): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('update', $campaign);

        /** @var array<int, int|string> $contactIds */
        $contactIds = $request->validated('contacts') ?? [];
        /** @var array<int, int|string> $listIds */
        $listIds = $request->validated('lists') ?? [];

        $addCampaignRecipients->handle(
            $campaign,
            array_map(intval(...), $contactIds),
            array_map(intval(...), $listIds),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recipients added.')]);

        return back();
    }

    /**
     * Remove the given recipient from the campaign.
     */
    public function destroy(Team $currentTeam, Campaign $campaign, Recipient $recipient, RemoveCampaignRecipient $removeCampaignRecipient): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);
        $recipient = $campaign->recipients()->findOrFail($recipient->id);

        Gate::authorize('update', $campaign);

        $removeCampaignRecipient->handle($recipient);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recipient removed.')]);

        return back();
    }

    /**
     * Remove multiple recipients from the given campaign.
     */
    public function bulkDestroy(BulkDestroyCampaignRecipientsRequest $request, Team $currentTeam, Campaign $campaign, RemoveCampaignRecipients $removeCampaignRecipients): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('update', $campaign);

        /** @var array<int, int|string> $recipientIds */
        $recipientIds = $request->validated('recipients');

        $removeCampaignRecipients->handle($campaign, array_map(intval(...), $recipientIds));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recipients removed.')]);

        return back();
    }
}
