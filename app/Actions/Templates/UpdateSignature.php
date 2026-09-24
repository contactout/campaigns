<?php

namespace App\Actions\Templates;

use App\Models\Signature;

class UpdateSignature
{
    /**
     * Update the given signature.
     *
     * The single-default rule is enforced by the Signature model.
     *
     * @param  array{name: string, body: string, is_default?: bool}  $data
     */
    public function handle(Signature $signature, array $data): Signature
    {
        $signature->fill([
            'name' => $data['name'],
            'body' => $data['body'],
        ]);

        if (array_key_exists('is_default', $data)) {
            $signature->is_default = (bool) $data['is_default'];
        }

        $signature->save();

        return $signature;
    }
}
