<?php

namespace App\Actions\Templates;

use App\Models\EmailTemplate;

class UpdateTemplate
{
    /**
     * Update the given email template.
     *
     * @param  array{name: string, subject?: string|null, body: string, folder_id?: int|null, is_draft?: bool}  $data
     */
    public function handle(EmailTemplate $template, array $data): EmailTemplate
    {
        $template->fill([
            'name' => $data['name'],
            'subject' => $data['subject'] ?? '',
            'body' => $data['body'],
        ]);

        if (array_key_exists('folder_id', $data)) {
            $template->folder_id = $data['folder_id'];
        }

        if (array_key_exists('is_draft', $data)) {
            $template->is_draft = (bool) $data['is_draft'];
        }

        $template->save();

        return $template;
    }
}
