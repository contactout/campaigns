<?php

use App\Actions\Campaigns\ReorderCampaignSteps;
use App\Enums\CampaignStatus;
use App\Enums\EmailStatus;
use App\Enums\RecipientStatus;
use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\User;
use App\Services\Mail\CampaignStepScheduler;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->withoutVite();
});

test('reordering a started campaign is rejected and its recipient can finish the sequence', function (CampaignStatus $status) {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $campaign = Campaign::factory()->forTeam($team)->active()->create(['status' => $status]);
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1, 'day' => 0]);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2, 'day' => 3]);
    $third = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 3, 'day' => 10]);
    $recipient = Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => RecipientStatus::Active,
    ]);
    $sent = CampaignEmail::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $first->id,
        'recipient_id' => $recipient->id,
    ]);
    $scheduler = app(CampaignStepScheduler::class);
    $scheduler->scheduleNextStep($sent);
    $waiting = $recipient->emails()->where('campaign_step_id', $second->id)->sole();

    $this->actingAs($user)
        ->post(route('campaigns.steps.reorder', [
            'current_team' => $team->slug,
            'campaign' => $campaign,
        ]), ['steps' => [$second->id, $first->id, $third->id]])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'steps' => 'Steps cannot be reordered after the campaign has started.',
        ]);

    expect($campaign->steps()->pluck('id')->all())->toBe([$first->id, $second->id, $third->id])
        ->and($waiting->refresh()->status)->toBe(EmailStatus::Scheduled);

    $waiting->update(['status' => EmailStatus::Sent]);
    $scheduler->scheduleNextStep($waiting);
    $last = $recipient->emails()->where('campaign_step_id', $third->id)->sole();

    expect($last->status)->toBe(EmailStatus::Scheduled)
        ->and($recipient->refresh()->status)->toBe(RecipientStatus::Active);

    $last->update(['status' => EmailStatus::Sent]);
    $scheduler->scheduleNextStep($last);

    expect($recipient->refresh()->status)->toBe(RecipientStatus::Completed)
        ->and($recipient->emails()->count())->toBe(3);
})->with([CampaignStatus::Active, CampaignStatus::Stopped]);

test('reordering uses the persisted start time even before the first email is seeded', function () {
    $campaign = Campaign::factory()->create();
    $first = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2]);

    // The reorder request loaded the campaign before another request started it.
    Campaign::query()->whereKey($campaign->id)->update([
        'status' => CampaignStatus::Active,
        'started_at' => now(),
    ]);

    expect(fn () => app(ReorderCampaignSteps::class)->handle($campaign, [$second->id, $first->id]))
        ->toThrow(ValidationException::class, 'Steps cannot be reordered after the campaign has started.');

    expect($campaign->steps()->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($campaign->emails()->exists())->toBeFalse();
});
