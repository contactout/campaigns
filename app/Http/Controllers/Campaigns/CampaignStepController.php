<?php

namespace App\Http\Controllers\Campaigns;

use App\Actions\Campaigns\DeleteCampaignStep;
use App\Actions\Campaigns\ReorderCampaignSteps;
use App\Actions\Campaigns\SaveCampaignStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\ReorderCampaignStepsRequest;
use App\Http\Requests\Campaigns\StoreCampaignStepRequest;
use App\Http\Requests\Campaigns\UpdateCampaignStepRequest;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CampaignStepController extends Controller
{
    /**
     * Append a new step to the given campaign.
     */
    public function store(StoreCampaignStepRequest $request, Team $currentTeam, Campaign $campaign, SaveCampaignStep $saveCampaignStep): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('update', $campaign);

        /** @var array{subject: string, body: string, day: int|string, time?: string|null, is_threaded?: bool} $data */
        $data = $request->validated();
        $data['day'] = (int) $data['day'];

        $saveCampaignStep->handle($campaign, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step created.')]);

        return back();
    }

    /**
     * Update the given campaign step.
     */
    public function update(UpdateCampaignStepRequest $request, Team $currentTeam, Campaign $campaign, CampaignStep $step, SaveCampaignStep $saveCampaignStep): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);
        $step = $campaign->steps()->findOrFail($step->id);

        Gate::authorize('update', $campaign);

        /** @var array{subject: string, body: string, day: int|string, time?: string|null, is_threaded?: bool} $data */
        $data = $request->validated();
        $data['day'] = (int) $data['day'];

        $saveCampaignStep->handle($campaign, $data, $step);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step updated.')]);

        return back();
    }

    /**
     * Delete the given campaign step.
     */
    public function destroy(Team $currentTeam, Campaign $campaign, CampaignStep $step, DeleteCampaignStep $deleteCampaignStep): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);
        $step = $campaign->steps()->findOrFail($step->id);

        Gate::authorize('update', $campaign);

        $deleteCampaignStep->handle($step);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step deleted.')]);

        return back();
    }

    /**
     * Reorder the steps of the given campaign.
     */
    public function reorder(ReorderCampaignStepsRequest $request, Team $currentTeam, Campaign $campaign, ReorderCampaignSteps $reorderCampaignSteps): RedirectResponse
    {
        $campaign = Campaign::forTeam($currentTeam->id)->findOrFail($campaign->id);

        Gate::authorize('update', $campaign);

        /** @var array<int, int|string> $orderedIds */
        $orderedIds = $request->validated('steps');

        $reorderCampaignSteps->handle($campaign, $orderedIds);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Steps reordered.')]);

        return back();
    }
}
