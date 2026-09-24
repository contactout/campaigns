<?php

namespace App\Actions\Contacts;

use App\Enums\PlaceholderType;
use App\Models\ContactField;

class UpdateContactField
{
    /**
     * Update the given custom contact field.
     *
     * @param  array{name: string, type: PlaceholderType|string, fallback?: string|null}  $data
     */
    public function handle(ContactField $field, array $data): ContactField
    {
        $field->fill([
            'name' => $data['name'],
            'type' => $data['type'],
            'fallback' => $data['fallback'] ?? null,
        ]);

        $field->save();

        return $field;
    }
}
