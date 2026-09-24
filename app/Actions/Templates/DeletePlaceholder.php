<?php

namespace App\Actions\Templates;

use App\Models\Placeholder;

class DeletePlaceholder
{
    /**
     * Delete the given placeholder.
     */
    public function handle(Placeholder $placeholder): void
    {
        $placeholder->delete();
    }
}
