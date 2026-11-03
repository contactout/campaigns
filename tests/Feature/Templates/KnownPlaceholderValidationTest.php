<?php

use App\Enums\TeamRole;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\ContactField;
use App\Models\EmailTemplate;
use App\Models\Placeholder;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user, ['role' => TeamRole::Member->value]);
    $this->template = EmailTemplate::factory()->forTeam($this->team)->create();
    $this->campaign = Campaign::factory()->forTeam($this->team)->create();
    $this->step = CampaignStep::factory()->forCampaign($this->campaign)->create();
    $this->actingAs($this->user);
});

function placeholderSaveTarget(string $target, Team $team, EmailTemplate $template, Campaign $campaign, CampaignStep $step): array
{
    $params = ['current_team' => $team->slug];

    return match ($target) {
        'create template' => ['post', route('templates.store', $params)],
        'update template' => ['patch', route('templates.update', [...$params, 'template' => $template])],
        'create step' => ['post', route('campaigns.steps.store', [...$params, 'campaign' => $campaign])],
        'update step' => ['patch', route('campaigns.steps.update', [...$params, 'campaign' => $campaign, 'step' => $step])],
    };
}

test('saves reject and name unknown placeholders without changing content', function (string $target) {
    [$method, $url] = placeholderSaveTarget($target, $this->team, $this->template, $this->campaign, $this->step);
    $templateCount = EmailTemplate::count();
    $stepCount = CampaignStep::count();
    $oldTemplate = $this->template->body;
    $oldStep = $this->step->body;

    // Neither another team's contact field nor a template definition is renderable here.
    ContactField::factory()->create(['name' => 'foreign_field']);
    Placeholder::factory()->forTemplate($this->template)->create(['name' => 'first_name']);

    $this->{$method}($url, [
        'name' => 'Intro',
        'subject' => 'Quick intro, {{first_name}}',
        'body' => '<p>Hi {{foreign_field}}, {{typo}} {{typo}}</p>',
        'day' => 0,
    ])->assertSessionHasErrors([
        'subject' => 'Unknown placeholders: {{first_name}}. Use the available contact fields.',
        'body' => 'Unknown placeholders: {{foreign_field}}, {{typo}}. Use the available contact fields.',
    ]);

    expect(EmailTemplate::count())->toBe($templateCount)
        ->and(CampaignStep::count())->toBe($stepCount)
        ->and($this->template->fresh()->body)->toBe($oldTemplate)
        ->and($this->step->fresh()->body)->toBe($oldStep);
})->with(['create template', 'update template', 'create step', 'update step']);

test('saves accept built in and current team fields with case and whitespace', function (string $target) {
    ContactField::factory()->forTeam($this->team)->create(['name' => 'Company']);
    ContactField::factory()->forTeam($this->team)->create(['name' => 'custom.key-1']);
    [$method, $url] = placeholderSaveTarget($target, $this->team, $this->template, $this->campaign, $this->step);

    $this->{$method}($url, [
        'name' => 'Intro',
        'subject' => 'Hello {{ NAME }} at {{company}}',
        'body' => '<p>{{email}} {{ phone }} {{custom.key-1}} {{ Signature }}</p>',
        'day' => 0,
    ])->assertRedirect()->assertSessionHasNoErrors();
})->with(['create template', 'update template', 'create step', 'update step']);

test('signature is not accepted in a subject', function (string $target) {
    [$method, $url] = placeholderSaveTarget($target, $this->team, $this->template, $this->campaign, $this->step);

    $this->{$method}($url, [
        'name' => 'Intro',
        'subject' => '{{signature}}',
        'body' => '<p>Hi {{name}}</p>',
        'day' => 0,
    ])->assertSessionHasErrors('subject');
})->with(['create template', 'update template', 'create step', 'update step']);

test('both editors expose the same team scoped placeholder catalogue', function () {
    ContactField::factory()->forTeam($this->team)->create(['name' => 'company']);
    ContactField::factory()->forTeam($this->team)->create(['name' => 'title']);
    ContactField::factory()->create(['name' => 'hidden']);
    $expected = [
        ['name' => 'name', 'label' => 'Full name'],
        ['name' => 'email', 'label' => 'Email'],
        ['name' => 'phone', 'label' => 'Phone'],
        ['name' => 'company', 'label' => 'company'],
        ['name' => 'title', 'label' => 'title'],
    ];

    $this->get(route('templates.show', ['current_team' => $this->team->slug, 'template' => $this->template]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('availablePlaceholders', $expected));

    $this->get(route('campaigns.show', ['current_team' => $this->team->slug, 'campaign' => $this->campaign]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('placeholders', $expected));
});

test('saves reject tags the renderer cannot match and the catalogue omits unusable field names', function (string $target) {
    ContactField::factory()->forTeam($this->team)->create(['name' => 'Job Title']);
    [$method, $url] = placeholderSaveTarget($target, $this->team, $this->template, $this->campaign, $this->step);

    $this->{$method}($url, [
        'name' => 'Intro',
        'subject' => 'Hi {{first name}}',
        'body' => '<p>{{Job Title}} {{name!}}</p>',
        'day' => 0,
    ])->assertSessionHasErrors([
        'subject' => 'Unknown placeholders: {{first name}}. Use the available contact fields.',
        'body' => 'Unknown placeholders: {{Job Title}}, {{name!}}. Use the available contact fields.',
    ]);

    $this->get(route('templates.show', ['current_team' => $this->team->slug, 'template' => $this->template]))
        ->assertInertia(fn (Assert $page) => $page->where('availablePlaceholders', fn ($list) => collect($list)->pluck('name')->doesntContain('Job Title')));
})->with(['create template', 'update template', 'create step', 'update step']);
