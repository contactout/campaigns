<?php

namespace App\Actions\Templates;

use App\Models\TemplateFolder;

class DeleteTemplateFolder
{
    /**
     * Delete the given template folder.
     *
     * Filed templates have their `folder_id` nulled by the foreign key.
     */
    public function handle(TemplateFolder $folder): void
    {
        $folder->delete();
    }
}
