<?php

namespace App\Services\Mail;

use App\Models\ContactField;

class MergeFields
{
    public const string PATTERN = '\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}';

    /**
     * Contact fields available to both editors and the recipient renderer.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public static function forTeam(int $teamId): array
    {
        return collect([
            ['name' => 'name', 'label' => 'Full name'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'phone', 'label' => 'Phone'],
        ])->merge(
            ContactField::query()->forTeam($teamId)->orderBy('name')->get()
                ->filter(fn (ContactField $field): bool => preg_match('/^[A-Za-z0-9_.-]+$/', $field->name) === 1)
                ->map(fn (ContactField $field): array => [
                    'name' => $field->name,
                    'label' => $field->name,
                ]),
        )->values()->all();
    }
}
