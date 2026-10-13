<?php

namespace App\Jobs\Contacts;

use App\Actions\Contacts\ImportContacts;
use App\Enums\ContactImportStatus;
use App\Models\ContactImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportContactsFromCsv implements ShouldQueue
{
    use Queueable;

    /**
     * Rows per run, sized so a run finishes well inside the job timeout.
     */
    public const ROWS_PER_RUN = 1000;

    public int $tries = 3;

    /**
     * Must stay below the queue connection's retry_after (90 seconds by default).
     */
    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance for the run starting at the given file offset.
     */
    public function __construct(
        public ContactImport $import,
        public int $offset = 0,
        public int $rowsPerRun = self::ROWS_PER_RUN,
    ) {}

    /**
     * Import the next batch of rows, then queue a run for the remainder.
     *
     * A run commits its rows and progress atomically, and does nothing unless
     * the import is unfinished and still at this run's offset, so retries and
     * duplicate deliveries never import a batch twice.
     */
    public function handle(ImportContacts $importContacts): void
    {
        $import = DB::transaction(function () use ($importContacts): ?ContactImport {
            $import = ContactImport::query()->lockForUpdate()->find($this->import->id);

            if ($import === null || $import->status->isFinished() || $import->byte_offset !== $this->offset) {
                return null;
            }

            $importContacts->handle($import, $this->rowsPerRun);

            return $import;
        });

        if ($import === null) {
            return;
        }

        if ($import->status->isFinished()) {
            Storage::disk('local')->delete($import->file_path);

            return;
        }

        self::dispatch($import, $import->byte_offset, $this->rowsPerRun);
    }

    /**
     * Mark the import as failed and discard the uploaded file.
     *
     * Ignored when the import has already moved past this run's offset.
     */
    public function failed(?Throwable $exception): void
    {
        $failed = ContactImport::query()
            ->whereKey($this->import->id)
            ->where('byte_offset', $this->offset)
            ->whereNotIn('status', [ContactImportStatus::Completed->value, ContactImportStatus::Failed->value])
            ->update([
                'status' => ContactImportStatus::Failed->value,
                'completed_at' => now(),
            ]);

        if ($failed === 0) {
            return;
        }

        DB::table('contact_import_emails')->where('contact_import_id', $this->import->id)->delete();
        Storage::disk('local')->delete($this->import->file_path);
    }
}
