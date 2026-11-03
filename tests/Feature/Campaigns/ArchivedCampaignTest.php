<?php

use App\Enums\CampaignStatus;
use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\Recipient;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('archived campaigns expose read only permissions while remaining viewable', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $campaign = Campaign::factory()->forTeam($team)->active()->create();

    $this->actingAs($user)
        ->post(route('campaigns.archive', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Archived);

    $this->get(route('campaigns.show', ['current_team' => $team->slug, 'campaign' => $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.status', CampaignStatus::Archived->value)
            ->where('can.update', false)
            ->where('can.delete', true)
            ->where('can.duplicate', true));
});

test('archived campaigns refuse changes to settings recipients and steps', function (string $operation) {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $campaign = Campaign::factory()->forTeam($team)->create(['status' => CampaignStatus::Archived]);
    $step = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);
    $second = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 2]);
    $recipient = Recipient::factory()->for($campaign)->create();
    $contact = Contact::factory()->forTeam($team)->create();
    $campaignBefore = $campaign->getAttributes();
    $stepBefore = $step->getAttributes();
    $recipientBefore = $recipient->getAttributes();

    [$method, $route, $payload] = match ($operation) {
        'settings' => ['patch', 'campaigns.update', ['name' => 'Changed', 'timezone' => 'UTC']],
        'add recipient' => ['post', 'campaigns.recipients.store', ['contacts' => [$contact->id]]],
        'remove recipient' => ['delete', 'campaigns.recipients.destroy', []],
        'bulk remove recipients' => ['post', 'campaigns.recipients.bulk-destroy', ['recipients' => [$recipient->id]]],
        'add step' => ['post', 'campaigns.steps.store', ['subject' => 'New', 'body' => '<p>New</p>', 'day' => 0]],
        'update step' => ['patch', 'campaigns.steps.update', ['subject' => 'Changed', 'body' => '<p>Changed</p>', 'day' => 1]],
        'remove step' => ['delete', 'campaigns.steps.destroy', []],
        'reorder steps' => ['post', 'campaigns.steps.reorder', ['steps' => [$second->id, $step->id]]],
    };

    $parameters = ['current_team' => $team->slug, 'campaign' => $campaign];

    if (in_array($operation, ['update step', 'remove step'], true)) {
        $parameters['step'] = $step;
    }

    if ($operation === 'remove recipient') {
        $parameters['recipient'] = $recipient;
    }

    $this->actingAs($user)->{$method}(route($route, $parameters), $payload)->assertForbidden();

    expect($campaign->fresh()->getAttributes())->toEqual($campaignBefore)
        ->and($step->fresh()->getAttributes())->toEqual($stepBefore)
        ->and($recipient->fresh()->getAttributes())->toEqual($recipientBefore)
        ->and($second->fresh()->sequence)->toBe(2)
        ->and($campaign->recipients()->count())->toBe(1)
        ->and($campaign->steps()->count())->toBe(2);
})->with([
    'settings',
    'add recipient',
    'remove recipient',
    'bulk remove recipients',
    'add step',
    'update step',
    'remove step',
    'reorder steps',
]);
