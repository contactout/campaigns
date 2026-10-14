<?php

namespace App\Actions\Contacts;

use App\Enums\ContactImportDuplicateAction;
use App\Enums\ContactImportStatus;
use App\Enums\PlaceholderType;
use App\Jobs\Contacts\ImportContactsFromCsv;
use App\Models\ContactImport;
use App\Models\Team;
use App\Models\User;
use App\Support\CsvRows;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StartContactImport
{
    public function __construct(protected CreateContactField $createContactField) {}

    /**
     * Store the uploaded CSV, create any requested fields and queue the import.
     *
     * Columns mapped to "new" become a Text contact field named after the CSV
     * header, reusing an existing team field with the same name.
     *
     * @param  array<int|string, string|null>  $columns
     */
    public function handle(
        Team $team,
        User $user,
        UploadedFile $file,
        array $columns,
        ContactImportDuplicateAction $duplicateAction,
        ?int $listId,
    ): ContactImport {
        $header = CsvRows::header($file->getRealPath());
        $path = $file->store('contact-imports', 'local');

        $import = DB::transaction(function () use ($team, $user, $file, $columns, $duplicateAction, $listId, $header, $path): ContactImport {
            $mapping = [];

            foreach ($columns as $index => $target) {
                if ($target === null) {
                    continue;
                }

                if ($target === 'new') {
                    $name = $header[(int) $index] ?? '';

                    if ($name === '') {
                        continue;
                    }

                    $field = $team->contactFields()->where('name', $name)->first()
                        ?? $this->createContactField->handle($team, $user, ['name' => $name, 'type' => PlaceholderType::Text]);

                    $target = "field:{$field->id}";
                }

                $mapping[(int) $index] = $target;
            }

            return $team->contactImports()->create([
                'user_id' => $user->id,
                'contact_list_id' => $listId,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mapping' => $mapping,
                'duplicate_action' => $duplicateAction,
                'status' => ContactImportStatus::Pending,
            ]);
        });

        ImportContactsFromCsv::dispatch($import);

        return $import;
    }
}
