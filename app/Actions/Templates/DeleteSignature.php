<?php

namespace App\Actions\Templates;

use App\Models\Signature;

class DeleteSignature
{
    /**
     * Delete the given signature.
     */
    public function handle(Signature $signature): void
    {
        $signature->delete();
    }
}
