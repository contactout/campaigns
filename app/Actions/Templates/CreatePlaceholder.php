<?php

namespace App\Actions\Templates;

use App\Enums\PlaceholderType;
use App\Models\EmailTemplate;
use App\Models\Placeholder;

class CreatePlaceholder
{
    /**
     * Create a placeholder on the given email template.
     *
     * @param  array{name: string, fallback?: string|null, type: PlaceholderType|string}  $data
     */
    public function handle(EmailTemplate $template, array $data): Placeholder
    {
        return $template->placeholders()->create([
            'team_id' => $template->team_id,
            'name' => $data['name'],
            'fallback' => $data['fallback'] ?? null,
            'type' => $data['type'],
        ]);
    }
}
