<?php

namespace App\Actions\Templates;

use App\Enums\PlaceholderType;
use App\Models\Placeholder;

class UpdatePlaceholder
{
    /**
     * Update the given placeholder.
     *
     * @param  array{name: string, fallback?: string|null, type: PlaceholderType|string}  $data
     */
    public function handle(Placeholder $placeholder, array $data): Placeholder
    {
        $placeholder->fill([
            'name' => $data['name'],
            'fallback' => $data['fallback'] ?? null,
            'type' => $data['type'],
        ]);

        $placeholder->save();

        return $placeholder;
    }
}
