<?php

namespace App\Actions\Contacts;

use App\Enums\ContactIdentityType;
use App\Enums\ContactImportDuplicateAction;
use App\Enums\ContactImportStatus;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactIdentity;
use App\Models\ContactImport;
use App\Models\ContactList;
use App\Models\Unsubscribe;
use App\Support\CsvRows;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportContacts
{
    protected const CHUNK_SIZE = 500;

    protected const MAX_FAILURES = 100;

    protected ContactImport $import;

    protected ?int $nameColumn = null;

    protected int $emailColumn;

    /** @var array<int, ContactField> */
    protected array $fieldColumns = [];

    protected ?ContactList $list = null;

    /** @var array<string, int> */
    protected array $seenEmails = [];

    /** @var array{created_count: int, updated_count: int, skipped_count: int, invalid_count: int} */
    protected array $counts = ['created_count' => 0, 'updated_count' => 0, 'skipped_count' => 0, 'invalid_count' => 0];

    /** @var array<int, array{row: int, reason: string}> */
    protected array $failures = [];

    public function __construct(
        protected CreateContact $createContact,
        protected SaveContactProperty $saveContactProperty,
        protected AttachContactsToList $attachContactsToList,
    ) {}

    /**
     * Import every data row of the stored CSV into the import's team.
     *
     * Rows are deduplicated on normalized email against the team's existing
     * identities and earlier rows of the same file, and addresses on the team's
     * unsubscribe list are skipped.
     */
    public function handle(ContactImport $import): void
    {
        $this->import = $import;
        $import->update(['status' => ContactImportStatus::Processing]);

        $this->resolveMapping();

        $chunk = [];

        foreach (CsvRows::read(Storage::disk('local')->path($import->file_path)) as $row => $values) {
            if ($row === 1) {
                continue;
            }

            $chunk[$row] = $values;

            if (count($chunk) === self::CHUNK_SIZE) {
                $this->processChunk($chunk);
                $chunk = [];
            }
        }

        $this->processChunk($chunk);

        $import->update([
            ...$this->progress(),
            'status' => ContactImportStatus::Completed,
            'completed_at' => now(),
        ]);

        Storage::disk('local')->delete($import->file_path);
    }

    /**
     * Resolve the stored column mapping into column indexes and team fields.
     */
    protected function resolveMapping(): void
    {
        $fieldIds = [];

        foreach ($this->import->mapping as $index => $target) {
            match (true) {
                $target === 'name' => $this->nameColumn = (int) $index,
                $target === 'email' => $this->emailColumn = (int) $index,
                str_starts_with($target, 'field:') => $fieldIds[(int) $index] = (int) substr($target, 6),
                default => null,
            };
        }

        $fields = ContactField::forTeam($this->import->team_id)->whereKey($fieldIds)->get()->keyBy('id');

        foreach ($fieldIds as $index => $fieldId) {
            if ($fields->has($fieldId)) {
                $this->fieldColumns[$index] = $fields->get($fieldId);
            }
        }

        $this->list = $this->import->contact_list_id === null
            ? null
            : ContactList::forTeam($this->import->team_id)->find($this->import->contact_list_id);
    }

    /**
     * Import a chunk of rows keyed by row number.
     *
     * @param  array<int, array<int, string>>  $chunk
     */
    protected function processChunk(array $chunk): void
    {
        $rows = [];

        foreach ($chunk as $row => $values) {
            $parsed = $this->parseRow($row, $values);

            if ($parsed !== null) {
                $rows[$row] = $parsed;
            }
        }

        if ($rows === []) {
            $this->import->update($this->progress());

            return;
        }

        $emails = array_column($rows, 'email');

        $suppressed = Unsubscribe::forTeam($this->import->team_id)
            ->whereIn('email', $emails)
            ->pluck('email')
            ->flip();

        /** @var Collection<string, ContactIdentity> $identities */
        $identities = ContactIdentity::query()
            ->where('team_id', $this->import->team_id)
            ->forType(ContactIdentityType::Email)
            ->whereIn('normalized_value', $emails)
            ->with(['contact' => fn ($query) => $query->withTrashed()])
            ->get()
            ->keyBy('normalized_value');

        $listContactIds = [];

        foreach ($rows as $row => $data) {
            if ($suppressed->has($data['email'])) {
                $this->skip($row, __('Email is on the suppression list.'));

                continue;
            }

            $contact = $identities->get($data['email'])?->contact;

            $contactId = $contact === null
                ? $this->createContact($row, $data)
                : $this->updateContact($row, $contact, $data);

            if ($contactId !== null) {
                $listContactIds[] = $contactId;
            }
        }

        if ($this->list !== null && $listContactIds !== []) {
            $this->attachContactsToList->handle($this->list, $listContactIds);
        }

        $this->import->update($this->progress());
    }

    /**
     * Validate a row and extract its contact data, recording invalid and in-file duplicate rows.
     *
     * @param  array<int, string>  $values
     * @return array{email: string, name: string|null, properties: array<int, string>}|null
     */
    protected function parseRow(int $row, array $values): ?array
    {
        $email = ContactIdentityType::Email->normalize($values[$this->emailColumn] ?? '');

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->invalid($row, __('Missing or invalid email address.'));

            return null;
        }

        $name = $this->nameColumn === null ? '' : ($values[$this->nameColumn] ?? '');

        if (mb_strlen($name) > 255) {
            $this->invalid($row, __('Name must not be longer than 255 characters.'));

            return null;
        }

        $properties = [];

        foreach ($this->fieldColumns as $index => $field) {
            $value = $values[$index] ?? '';

            if (mb_strlen($value) > 2000) {
                $this->invalid($row, __(':field must not be longer than 2000 characters.', ['field' => $field->name]));

                return null;
            }

            if ($value !== '') {
                $properties[$index] = $value;
            }
        }

        if (isset($this->seenEmails[$email])) {
            $this->skip($row, __('Duplicate of row :row in this file.', ['row' => $this->seenEmails[$email]]));

            return null;
        }

        $this->seenEmails[$email] = $row;

        return ['email' => $email, 'name' => $name === '' ? null : $name, 'properties' => $properties];
    }

    /**
     * Create a contact for the row, returning its id when it was created.
     *
     * @param  array{email: string, name: string|null, properties: array<int, string>}  $data
     */
    protected function createContact(int $row, array $data): ?int
    {
        try {
            $contact = DB::transaction(function () use ($data): Contact {
                $contact = $this->createContact->handle($this->import->team, $this->import->user, [
                    'name' => $data['name'] ?? $data['email'],
                    'email' => $data['email'],
                    'source' => 'import',
                ]);

                $this->saveProperties($contact, $data['properties']);

                return $contact;
            });
        } catch (UniqueConstraintViolationException) {
            $this->skip($row, __('A contact with this email already exists.'));

            return null;
        }

        $this->counts['created_count']++;

        return $contact->id;
    }

    /**
     * Update or skip the existing contact for the row, returning its id when it was updated.
     *
     * Only mapped, non-blank cells overwrite existing values.
     *
     * @param  array{email: string, name: string|null, properties: array<int, string>}  $data
     */
    protected function updateContact(int $row, Contact $contact, array $data): ?int
    {
        if ($contact->trashed()) {
            $this->skip($row, __('Email belongs to a deleted contact.'));

            return null;
        }

        if ($this->import->duplicate_action === ContactImportDuplicateAction::Skip) {
            $this->skip($row, __('A contact with this email already exists.'));

            return null;
        }

        DB::transaction(function () use ($contact, $data): void {
            if ($data['name'] !== null) {
                $contact->update(['name' => $data['name']]);
            }

            $this->saveProperties($contact, $data['properties']);
        });

        $this->counts['updated_count']++;

        return $contact->id;
    }

    /**
     * Store the row's field values on the contact.
     *
     * @param  array<int, string>  $properties
     */
    protected function saveProperties(Contact $contact, array $properties): void
    {
        foreach ($properties as $index => $value) {
            $this->saveContactProperty->handle($contact, $this->fieldColumns[$index], $value);
        }
    }

    /**
     * Record a skipped row.
     */
    protected function skip(int $row, string $reason): void
    {
        $this->counts['skipped_count']++;
        $this->recordFailure($row, $reason);
    }

    /**
     * Record an invalid row.
     */
    protected function invalid(int $row, string $reason): void
    {
        $this->counts['invalid_count']++;
        $this->recordFailure($row, $reason);
    }

    /**
     * Keep the reason for a row that was not imported, up to the stored limit.
     */
    protected function recordFailure(int $row, string $reason): void
    {
        if (count($this->failures) < self::MAX_FAILURES) {
            $this->failures[] = ['row' => $row, 'reason' => $reason];
        }
    }

    /**
     * Get the current counters and failures for persisting on the import.
     *
     * @return array<string, mixed>
     */
    protected function progress(): array
    {
        return [...$this->counts, 'failures' => $this->failures];
    }
}
