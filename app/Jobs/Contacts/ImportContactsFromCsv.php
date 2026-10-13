<?php

namespace App\Jobs\Contacts;

use App\Actions\Contacts\ImportContacts;
use App\Enums\ContactImportStatus;
use App\Models\ContactImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportContactsFromCsv implements ShouldQueue
{
    use Queueable;

    /**
     * A retry would report rows created by the failed attempt as existing contacts.
     */
    public int $tries = 1;

    public int $timeout = 900;

    /**
     * Create a new job instance.
     */
    public function __construct(public ContactImport $import) {}

    /**
     * Import the contacts from the uploaded CSV file.
     */
    public function handle(ImportContacts $importContacts): void
    {
        $importContacts->handle($this->import);
    }

    /**
     * Mark the import as failed and discard the uploaded file.
     */
    public function failed(?Throwable $exception): void
    {
        $this->import->update([
            'status' => ContactImportStatus::Failed,
            'completed_at' => now(),
        ]);

        Storage::disk('local')->delete($this->import->file_path);
    }
}
