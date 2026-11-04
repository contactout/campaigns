<?php

namespace App\Http\Requests\Templates;

use App\Concerns\ResolvesCurrentTeam;
use App\Rules\KnownPlaceholders;
use App\Services\Mail\MergeFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
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
        $fields = MergeFields::forTeam($teamId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255', new KnownPlaceholders($fields)],
            'body' => ['required', 'string', new KnownPlaceholders($fields, allowSignature: true, html: true)],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('template_folders', 'id')->where('team_id', $teamId),
            ],
            'is_draft' => ['sometimes', 'boolean'],
        ];
    }
}
