<?php

namespace App\Http\Requests\Templates;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\PlaceholderType;
use App\Models\EmailTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlaceholderRequest extends FormRequest
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
        $template = $this->route('template');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('placeholders', 'name')->where(fn (Builder $query) => $query
                    ->where('team_id', $teamId)
                    ->where('owner_type', $template instanceof EmailTemplate ? $template->getMorphClass() : EmailTemplate::class)
                    ->where('owner_id', $template instanceof EmailTemplate ? $template->getKey() : 0)),
            ],
            'fallback' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PlaceholderType::class)],
        ];
    }
}
