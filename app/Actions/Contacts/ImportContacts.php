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

    /** @var array{created_count: int, updated_count: int, skipped_count: int, invalid_count: int} */
    protected array $counts;

    /** @var array<int, array{row: int, reason: string}> */
    protected array $failures;

    public function __construct(
        protected CreateContact $createContact,
        protected SaveContactProperty $saveContactProperty,
        protected AttachContactsToList $attachContactsToList,
    ) {}

    /**
     * Import up to the given number of data rows from the import's saved file offset.
     *
     * Rows are deduplicated on normalized email against the team's existing
     * identities and earlier rows of the same file, and addresses on the team's
     * unsubscribe list are skipped. The counters, offset and status are saved on
     * the import; it is completed once the end of the file is reached.
     */
    public function handle(ContactImport $import, int $maxRows): void
    {
        $this->import = $import;
        $this->counts = [
            'created_count' => $import->created_count,
            'updated_count' => $import->updated_count,
            'skipped_count' => $import->skipped_count,
            'invalid_count' => $import->invalid_count,
        ];
        $this->failures = $import->failures ?? [];

        $this->resolveMapping();

        $offset = $import->byte_offset;
        $line = $import->rows_read;
        $isHeader = $offset === 0;
        $rowsRead = 0;
        $finished = true;
        $chunk = [];

        foreach (CsvRows::read(Storage::disk('local')->path($import->file_path), $offset, $line) as $row => [$values, $end]) {
            if ($rowsRead === $maxRows) {
                $finished = false;

                break;
            }

            $offset = $end;
            $line = $row;

            if ($isHeader) {
                $isHeader = false;

                continue;
            }

            $rowsRead++;
            $chunk[$row] = $values;

            if (count($chunk) === self::CHUNK_SIZE) {
                $this->processChunk($chunk);
                $chunk = [];
            }
        }

        $this->processChunk($chunk);

        $import->update([
            ...$this->counts,
            'failures' => $this->failures,
            'byte_offset' => $offset,
            'rows_read' => $line,
            'status' => $finished ? ContactImportStatus::Completed : ContactImportStatus::Processing,
            'completed_at' => $finished ? now() : null,
        ]);

        if ($finished) {
            DB::table('contact_import_emails')->where('contact_import_id', $import->id)->delete();
        }
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

        $rows = $this->withoutFileDuplicates($rows);

        if ($rows === []) {
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
    }

    /**
     * Drop rows whose email appeared on an earlier row of the file, remembering the rest.
     *
     * Seen emails are stored per import so duplicates are caught across job runs.
     *
     * @param  array<int, array{email: string, name: string|null, properties: array<int, string>}>  $rows
     * @return array<int, array{email: string, name: string|null, properties: array<int, string>}>
     */
    protected function withoutFileDuplicates(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        /** @var array<string, int> $seen */
        $seen = DB::table('contact_import_emails')
            ->where('contact_import_id', $this->import->id)
            ->whereIn('email', array_column($rows, 'email'))
            ->pluck('row', 'email')
            ->all();

        $unique = [];

        foreach ($rows as $row => $data) {
            if (isset($seen[$data['email']])) {
                $this->skip($row, __('Duplicate of row :row in this file.', ['row' => $seen[$data['email']]]));

                continue;
            }

            $seen[$data['email']] = $row;
            $unique[$row] = $data;
        }

        DB::table('contact_import_emails')->insert(array_map(
            fn (int $row, array $data): array => ['contact_import_id' => $this->import->id, 'email' => $data['email'], 'row' => $row],
            array_keys($unique),
            $unique,
        ));

        return $unique;
    }

    /**
     * Validate a row and extract its contact data, recording invalid rows.
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
}
