<?php

use App\Models\CampaignEmail;
use App\Models\EmailOpen;
use App\Models\LinkClick;
use App\Models\TrackedLink;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

/**
 * Run the migration that adds the recipient/step unique index.
 */
function mmosRunUniqueIndexMigration(): void
{
    $migration = require database_path('migrations/2026_10_02_000000_add_unique_index_to_campaign_emails_recipient_step.php');

    $migration->up();
}

test('a recipient and step pair cannot hold two emails', function () {
    $email = CampaignEmail::factory()->create();

    expect(fn () => CampaignEmail::factory()->create([
        'recipient_id' => $email->recipient_id,
        'campaign_step_id' => $email->campaign_step_id,
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(CampaignEmail::query()->count())->toBe(1);
});

test('the migration merges duplicates and keeps their tracking rows', function () {
    // Reproduce the state the migration has to cope with: duplicates that were
    // inserted before the index existed.
    Schema::table('campaign_emails', function (Blueprint $table) {
        $table->dropUnique(['recipient_id', 'campaign_step_id']);
    });

    $kept = CampaignEmail::factory()->create();

    $duplicate = CampaignEmail::factory()->create([
        'recipient_id' => $kept->recipient_id,
        'campaign_step_id' => $kept->campaign_step_id,
    ]);

    $open = EmailOpen::factory()->create([
        'campaign_email_id' => $duplicate->id,
        'recipient_id' => $duplicate->recipient_id,
    ]);

    $link = TrackedLink::factory()->create(['campaign_email_id' => $duplicate->id]);

    $click = LinkClick::factory()->create([
        'campaign_email_id' => $duplicate->id,
        'tracked_link_id' => $link->id,
        'recipient_id' => $duplicate->recipient_id,
    ]);

    mmosRunUniqueIndexMigration();

    expect(CampaignEmail::query()->count())->toBe(1)
        ->and(CampaignEmail::query()->sole()->id)->toBe($kept->id)
        ->and($open->fresh()->campaign_email_id)->toBe($kept->id)
        ->and($link->fresh()->campaign_email_id)->toBe($kept->id)
        ->and($click->fresh()->campaign_email_id)->toBe($kept->id);
});

test('the migration leaves distinct recipient and step pairs alone', function () {
    Schema::table('campaign_emails', function (Blueprint $table) {
        $table->dropUnique(['recipient_id', 'campaign_step_id']);
    });

    $email = CampaignEmail::factory()->create();
    $other = CampaignEmail::factory()->create(['recipient_id' => $email->recipient_id]);

    mmosRunUniqueIndexMigration();

    expect(CampaignEmail::query()->count())->toBe(2)
        ->and(CampaignEmail::query()->orderBy('id')->pluck('id')->all())->toBe([$email->id, $other->id]);
});
