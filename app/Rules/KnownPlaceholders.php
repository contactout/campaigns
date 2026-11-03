<?php

namespace App\Rules;

use App\Services\Mail\MergeFields;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class KnownPlaceholders implements ValidationRule
{
    /**
     * @param  array<int, array{name: string, label: string}>  $fields
     */
    public function __construct(private array $fields, private bool $allowSignature = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $known = array_map(strtolower(...), array_column($this->fields, 'name'));

        if ($this->allowSignature) {
            $known[] = 'signature';
        }

        preg_match_all('/'.MergeFields::PATTERN.'/', $value, $matches);

        $unknown = array_values(array_unique(array_filter(
            $matches[1],
            static fn (string $name): bool => ! in_array(strtolower($name), $known, true),
        )));

        if ($unknown !== []) {
            $fail(__('Unknown placeholders: :tags. Use the available contact fields.', [
                'tags' => implode(', ', array_map(static fn (string $name): string => '{{'.$name.'}}', $unknown)),
            ]));
        }
    }
}
