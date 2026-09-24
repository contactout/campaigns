<?php

namespace App\Actions\Templates;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;

class DeleteTemplate
{
    /**
     * Delete the given email template and its placeholders.
     */
    public function handle(EmailTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            $template->placeholders()->delete();

            $template->delete();
        });
    }
}
