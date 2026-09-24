<?php

namespace App\Http\Requests\Templates;

use App\Concerns\ResolvesCurrentTeam;
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('template_folders', 'id')->where('team_id', $teamId),
            ],
            'is_draft' => ['sometimes', 'boolean'],
        ];
    }
}
