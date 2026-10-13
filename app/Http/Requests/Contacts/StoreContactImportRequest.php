<?php

namespace App\Http\Requests\Contacts;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\ContactImportDuplicateAction;
use App\Models\ContactField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContactImportRequest extends FormRequest
{
    use ResolvesCurrentTeam;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->resolveCurrentTeam($this)->id;

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'columns' => ['required', 'array'],
            'columns.*' => ['nullable', 'string', 'regex:/^(name|email|new|field:\d+)$/'],
            'duplicate_action' => ['required', Rule::enum(ContactImportDuplicateAction::class)],
            'list_id' => ['nullable', 'integer', Rule::exists('contact_lists', 'id')->where('team_id', $teamId)],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var array<int|string, string|null> $columns */
                $columns = $this->input('columns', []);
                $targets = array_count_values(array_filter($columns, fn (?string $target): bool => $target !== null && $target !== 'new'));

                if (($targets['email'] ?? 0) !== 1) {
                    $validator->errors()->add('columns', __('Map exactly one column to Email.'));
                }

                if (($targets['name'] ?? 0) > 1) {
                    $validator->errors()->add('columns', __('Map at most one column to Name.'));
                }

                $fieldTargets = array_filter($targets, fn (string $target): bool => str_starts_with($target, 'field:'), ARRAY_FILTER_USE_KEY);

                if (max([0, ...array_values($fieldTargets)]) > 1) {
                    $validator->errors()->add('columns', __('Map each contact field to at most one column.'));
                }

                $fieldIds = array_map(fn (string $target): int => (int) substr($target, 6), array_keys($fieldTargets));
                $ownedFields = ContactField::forTeam($this->resolveCurrentTeam($this)->id)->whereKey($fieldIds)->count();

                if ($ownedFields !== count($fieldIds)) {
                    $validator->errors()->add('columns', __('One or more mapped contact fields do not exist.'));
                }
            },
        ];
    }
}
