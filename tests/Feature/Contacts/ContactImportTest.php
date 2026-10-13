<?php

use App\Actions\Contacts\ImportContacts;
use App\Enums\ContactIdentityType;
use App\Enums\ContactImportStatus;
use App\Enums\TeamRole;
use App\Jobs\Contacts\ImportContactsFromCsv;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactIdentity;
use App\Models\ContactImport;
use App\Models\ContactList;
use App\Models\Team;
use App\Models\Unsubscribe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a team with the given user attached as a member.
 */
function importTeamWithMember(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    return [$team, $user];
}

/**
 * Create an existing contact with the given email for the team.
 */
function importExistingContact(Team $team, string $email, string $name = 'Existing'): Contact
{
    $contact = Contact::factory()->forTeam($team)->create(['name' => $name]);

    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => $email,
    ]);

    return $contact;
}

/**
 * Post a CSV import for the team and return the test response.
 *
 * @param  array<int, string|null>  $columns
 * @param  array<string, mixed>  $options
 */
function postImport(Team $team, User $user, string $csv, array $columns = ['name', 'email'], array $options = [])
{
    return test()->actingAs($user)->post(route('contacts.imports.store', ['current_team' => $team->slug]), [
        'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
        'columns' => $columns,
        'duplicate_action' => 'skip',
        ...$options,
    ]);
}

/**
 * Get the team's contact with the given email.
 */
function importedContact(Team $team, string $email): ?Contact
{
    return ContactIdentity::query()
        ->where('team_id', $team->id)
        ->where('normalized_value', $email)
        ->first()
        ?->contact;
}

beforeEach(function (): void {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    Storage::fake('local');
});

test('uploading a csv queues the import', function () {
    Queue::fake();
    [$team, $user] = importTeamWithMember();

    postImport($team, $user, "Name,Email\nAda,ada@example.com\n")
        ->assertRedirect(route('contacts.index', ['current_team' => $team->slug]));

    $import = ContactImport::sole();

    expect($import->team_id)->toBe($team->id)
        ->and($import->user_id)->toBe($user->id)
        ->and($import->status)->toBe(ContactImportStatus::Pending)
        ->and($import->mapping)->toBe([0 => 'name', 1 => 'email']);

    Storage::disk('local')->assertExists($import->file_path);
    Queue::assertPushed(ImportContactsFromCsv::class, fn (ImportContactsFromCsv $job) => $job->import->is($import));
});

test('imports new contacts with mapped and newly created fields', function () {
    [$team, $user] = importTeamWithMember();
    $company = ContactField::factory()->forTeam($team)->create(['name' => 'Company']);

    postImport(
        $team,
        $user,
        "\xEF\xBB\xBFName,Email,Company,Role,Ignored\nAda Lovelace, ADA@Example.com ,Analytical,Engineer,x\n,grace@example.com,Navy,,y\n",
        ['name', 'email', "field:{$company->id}", 'new', null],
    )->assertSessionHasNoErrors();

    $import = ContactImport::sole();
    $role = ContactField::forTeam($team->id)->where('name', 'Role')->sole();
    $ada = importedContact($team, 'ada@example.com');
    $grace = importedContact($team, 'grace@example.com');

    expect($import->status)->toBe(ContactImportStatus::Completed)
        ->and($import->created_count)->toBe(2)
        ->and($import->updated_count)->toBe(0)
        ->and($import->skipped_count)->toBe(0)
        ->and($import->invalid_count)->toBe(0)
        ->and($ada->name)->toBe('Ada Lovelace')
        ->and($ada->source)->toBe('import')
        ->and($ada->user_id)->toBe($user->id)
        ->and($ada->propertyValue($company->id))->toBe('Analytical')
        ->and($ada->propertyValue($role->id))->toBe('Engineer')
        ->and($grace->name)->toBe('grace@example.com')
        ->and($grace->propertyValue($role->id))->toBeNull();

    Storage::disk('local')->assertMissing($import->file_path);
});

test('existing contacts are skipped by default', function () {
    [$team, $user] = importTeamWithMember();
    $existing = importExistingContact($team, 'ada@example.com', 'Original');

    postImport($team, $user, "Name,Email\nAda Updated,ada@example.com\nGrace,grace@example.com\n");

    $import = ContactImport::sole();

    expect($import->created_count)->toBe(1)
        ->and($import->skipped_count)->toBe(1)
        ->and($import->failures)->toBe([['row' => 2, 'reason' => 'A contact with this email already exists.']])
        ->and($existing->fresh()->name)->toBe('Original')
        ->and(Contact::forTeam($team->id)->count())->toBe(2);
});

test('existing contacts are updated when requested without clearing blank cells', function () {
    [$team, $user] = importTeamWithMember();
    $company = ContactField::factory()->forTeam($team)->create(['name' => 'Company']);
    $title = ContactField::factory()->forTeam($team)->create(['name' => 'Title']);
    $existing = importExistingContact($team, 'ada@example.com', 'Original');
    $existing->properties()->create(['contact_field_id' => $title->id, 'value' => 'Countess']);

    postImport(
        $team,
        $user,
        "Name,Email,Company,Title\nAda Updated,ada@example.com,Analytical,\n",
        ['name', 'email', "field:{$company->id}", "field:{$title->id}"],
        ['duplicate_action' => 'update'],
    );

    $import = ContactImport::sole();
    $existing->refresh();

    expect($import->updated_count)->toBe(1)
        ->and($import->created_count)->toBe(0)
        ->and($existing->name)->toBe('Ada Updated')
        ->and($existing->propertyValue($company->id))->toBe('Analytical')
        ->and($existing->propertyValue($title->id))->toBe('Countess')
        ->and(Contact::forTeam($team->id)->count())->toBe(1);
});

test('duplicate emails within the file are imported once', function () {
    [$team, $user] = importTeamWithMember();

    postImport($team, $user, "Name,Email\nAda,ada@example.com\nAda Again,ADA@example.com \n");

    $import = ContactImport::sole();

    expect($import->created_count)->toBe(1)
        ->and($import->skipped_count)->toBe(1)
        ->and($import->failures)->toBe([['row' => 3, 'reason' => 'Duplicate of row 2 in this file.']])
        ->and(importedContact($team, 'ada@example.com')->name)->toBe('Ada');
});

test('rows with missing or invalid data are reported as invalid', function () {
    [$team, $user] = importTeamWithMember();
    $longName = str_repeat('a', 256);

    postImport($team, $user, "Name,Email\nNo Email,\nBad,not-an-email\n{$longName},long@example.com\n\nGood,good@example.com\n");

    $import = ContactImport::sole();

    expect($import->created_count)->toBe(1)
        ->and($import->invalid_count)->toBe(3)
        ->and(array_column($import->failures, 'row'))->toBe([2, 3, 4])
        ->and(Contact::forTeam($team->id)->count())->toBe(1);
});

test('addresses on the team suppression list are skipped', function () {
    [$team, $user] = importTeamWithMember();
    Unsubscribe::factory()->forTeam($team)->create(['email' => 'gone@example.com']);
    Unsubscribe::factory()->create(['email' => 'ada@example.com']);

    postImport($team, $user, "Name,Email\nGone,Gone@example.com\nAda,ada@example.com\n");

    $import = ContactImport::sole();

    expect($import->created_count)->toBe(1)
        ->and($import->skipped_count)->toBe(1)
        ->and($import->failures)->toBe([['row' => 2, 'reason' => 'Email is on the suppression list.']])
        ->and(importedContact($team, 'gone@example.com'))->toBeNull();
});

test('created and updated contacts are attached to the chosen list', function () {
    [$team, $user] = importTeamWithMember();
    $list = ContactList::factory()->create(['team_id' => $team->id]);
    $existing = importExistingContact($team, 'ada@example.com');
    $skipped = importExistingContact($team, 'old@example.com');

    postImport(
        $team,
        $user,
        "Name,Email\nAda,ada@example.com\nGrace,grace@example.com\n",
        options: ['duplicate_action' => 'update', 'list_id' => $list->id],
    );

    expect($list->contacts()->pluck('contacts.id')->sort()->values()->all())
        ->toBe(collect([$existing->id, importedContact($team, 'grace@example.com')->id])->sort()->values()->all())
        ->and($list->contacts()->whereKey($skipped->id)->exists())->toBeFalse();
});

test('imports are isolated to the current team', function () {
    [$team, $user] = importTeamWithMember();
    $otherTeam = Team::factory()->create();
    $otherContact = importExistingContact($otherTeam, 'ada@example.com', 'Other Team Ada');

    postImport($team, $user, "Name,Email\nAda,ada@example.com\n");

    expect(ContactImport::sole()->created_count)->toBe(1)
        ->and(importedContact($team, 'ada@example.com')->name)->toBe('Ada')
        ->and($otherContact->fresh()->name)->toBe('Other Team Ada');
});

test('another teams list or field cannot be used', function () {
    Queue::fake();
    [$team, $user] = importTeamWithMember();
    $otherTeam = Team::factory()->create();
    $otherList = ContactList::factory()->create(['team_id' => $otherTeam->id]);
    $otherField = ContactField::factory()->forTeam($otherTeam)->create();

    postImport($team, $user, "Email\nada@example.com\n", ['email'], ['list_id' => $otherList->id])
        ->assertSessionHasErrors('list_id');

    postImport($team, $user, "Email,Field\nada@example.com,x\n", ['email', "field:{$otherField->id}"])
        ->assertSessionHasErrors('columns');

    expect(ContactImport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('exactly one column must be mapped to email', function (array $columns) {
    Queue::fake();
    [$team, $user] = importTeamWithMember();

    postImport($team, $user, "Name,Email\nAda,ada@example.com\n", $columns)
        ->assertSessionHasErrors('columns');

    Queue::assertNothingPushed();
})->with([
    'no email column' => [['name', null]],
    'two email columns' => [['email', 'email']],
]);

test('non members cannot import contacts', function () {
    Queue::fake();
    $team = Team::factory()->create();

    postImport($team, User::factory()->create(), "Name,Email\nAda,ada@example.com\n")
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('a byte order mark and blank lines before the header are skipped', function () {
    [$team, $user] = importTeamWithMember();

    postImport($team, $user, "\xEF\xBB\xBF\n\nName,Email\nAda,ada@example.com\n");

    $import = ContactImport::sole();

    expect($import->created_count)->toBe(1)
        ->and($import->invalid_count)->toBe(0)
        ->and($import->failures)->toBe([]);
});

test('large imports resume across bounded runs', function () {
    Queue::fake();
    [$team, $user] = importTeamWithMember();

    postImport($team, $user, "Name,Email\nAda,ada@example.com\nGrace,grace@example.com\nAlan,alan@example.com\nAda Again,ada@example.com\nKaty,katy@example.com\n");

    $import = ContactImport::sole();
    $runs = 0;
    $job = new ImportContactsFromCsv($import, 0, 2);

    while ($job !== null) {
        Queue::fake();
        $job->handle(app(ImportContacts::class));
        $runs++;

        $next = Queue::pushed(ImportContactsFromCsv::class)->first();
        $job = $next;
    }

    $import->refresh();

    expect($runs)->toBe(3)
        ->and($import->status)->toBe(ContactImportStatus::Completed)
        ->and($import->created_count)->toBe(4)
        ->and($import->skipped_count)->toBe(1)
        ->and($import->failures)->toBe([['row' => 5, 'reason' => 'Duplicate of row 2 in this file.']])
        ->and($import->rows_read)->toBe(6)
        ->and(DB::table('contact_import_emails')->count())->toBe(0)
        ->and(Contact::forTeam($team->id)->count())->toBe(4);

    Storage::disk('local')->assertMissing($import->file_path);
});

test('a duplicate or stale run does not reimport or overwrite progress', function () {
    Queue::fake();
    [$team, $user] = importTeamWithMember();

    postImport($team, $user, "Name,Email\nAda,ada@example.com\nGrace,grace@example.com\nAlan,alan@example.com\n");

    $import = ContactImport::sole();
    Queue::fake();

    (new ImportContactsFromCsv($import, 0, 2))->handle(app(ImportContacts::class));
    $afterFirstRun = $import->fresh();

    (new ImportContactsFromCsv($import, 0, 2))->handle(app(ImportContacts::class));
    (new ImportContactsFromCsv($import, 0, 2))->failed(new RuntimeException('Stale delivery.'));

    $import->refresh();

    expect($afterFirstRun->status)->toBe(ContactImportStatus::Processing)
        ->and($afterFirstRun->byte_offset)->toBeGreaterThan(0)
        ->and($import->status)->toBe(ContactImportStatus::Processing)
        ->and($import->byte_offset)->toBe($afterFirstRun->byte_offset)
        ->and($import->created_count)->toBe(2)
        ->and(Contact::forTeam($team->id)->count())->toBe(2);

    Storage::disk('local')->assertExists($import->file_path);
    Queue::assertPushed(ImportContactsFromCsv::class, 1);
});

test('a failed run marks the import failed and discards the file', function () {
    Queue::fake();
    [$team, $user] = importTeamWithMember();

    postImport($team, $user, "Name,Email\nAda,ada@example.com\nGrace,grace@example.com\nAlan,alan@example.com\n");

    $import = ContactImport::sole();

    (new ImportContactsFromCsv($import, 0, 2))->handle(app(ImportContacts::class));
    $offset = $import->fresh()->byte_offset;

    (new ImportContactsFromCsv($import, $offset, 2))->failed(new RuntimeException('Database went away.'));

    $import->refresh();

    expect($import->status)->toBe(ContactImportStatus::Failed)
        ->and($import->completed_at)->not->toBeNull()
        ->and($import->created_count)->toBe(2)
        ->and(DB::table('contact_import_emails')->count())->toBe(0);

    Storage::disk('local')->assertMissing($import->file_path);
});

test('the contacts index shows the users latest import summary', function () {
    [$team, $user] = importTeamWithMember();

    postImport($team, $user, "Name,Email\nAda,ada@example.com\nBad,nope\n");

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('latestImport.status', 'completed')
            ->where('latestImport.finished', true)
            ->where('latestImport.created_count', 1)
            ->where('latestImport.invalid_count', 1)
            ->has('latestImport.failures', 1));
});
