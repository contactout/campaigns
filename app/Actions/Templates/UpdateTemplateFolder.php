<?php

namespace App\Actions\Templates;

use App\Models\TemplateFolder;

class UpdateTemplateFolder
{
    /**
     * Rename the given template folder.
     */
    public function handle(TemplateFolder $folder, string $name): TemplateFolder
    {
        $folder->fill(['name' => $name]);

        $folder->save();

        return $folder;
    }
}
