<?php

use Illuminate\Support\Facades\Schema;

/**
 * Load the migration that adds the campaign email lookup indexes.
 */
function celiLookupIndexMigration(): object
{
    return require database_path('migrations/2026_11_02_120000_add_lookup_indexes_to_campaign_emails_table.php');
}

/**
 * Get the column lists of the campaign email indexes.
 *
 * @return array<int, array<int, string>>
 */
function celiIndexColumns(): array
{
    return collect(Schema::getIndexes('campaign_emails'))
        ->pluck('columns')
        ->all();
}

/**
 * Get the mailer connection foreign key of the campaign emails table, if any.
 *
 * @return array<string, mixed>|null
 */
function celiMailerConnectionForeignKey(): ?array
{
    return collect(Schema::getForeignKeys('campaign_emails'))
        ->first(fn (array $key): bool => $key['columns'] === ['mailer_connection_id']);
}

test('campaign emails are indexed for dispatch, reply and bounce lookups', function () {
    expect(celiIndexColumns())
        ->toContain(['status', 'scheduled_at'])
        ->toContain(['mailer_connection_id', 'message_id'])
        ->toContain(['mailer_connection_id', 'dispatched_at']);
});

test('rolling the lookup indexes back keeps the mailer connection foreign key indexed', function () {
    $migration = celiLookupIndexMigration();

    $migration->down();

    expect(celiIndexColumns())
        ->not->toContain(['status', 'scheduled_at'])
        ->not->toContain(['mailer_connection_id', 'message_id'])
        ->not->toContain(['mailer_connection_id', 'dispatched_at']);

    $connectionIndexes = fn (): int => collect(celiIndexColumns())
        ->filter(fn (array $columns): bool => $columns === ['mailer_connection_id'])
        ->count();

    expect($connectionIndexes())->toBe(1);

    $foreignKey = celiMailerConnectionForeignKey();

    expect($foreignKey)->not->toBeNull()
        ->and($foreignKey['foreign_table'])->toBe('mailer_connections')
        ->and($foreignKey['foreign_columns'])->toBe(['id'])
        ->and(strtolower((string) $foreignKey['on_delete']))->toBe('set null');

    // A second round trip reuses the standalone index instead of adding another.
    $migration->up();
    $migration->down();

    expect($connectionIndexes())->toBe(1);

    $migration->up();

    expect(celiIndexColumns())->toContain(['mailer_connection_id', 'message_id']);
});
