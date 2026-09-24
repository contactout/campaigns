<?php

namespace App\Http\Requests\Templates;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\PlaceholderType;
use App\Models\Placeholder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaceholderRequest extends FormRequest
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
        $placeholder = $this->route('placeholder');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('placeholders', 'name')
                    ->ignore($placeholder instanceof Placeholder ? $placeholder->getKey() : 0)
                    ->where(fn (Builder $query) => $query
                        ->where('team_id', $teamId)
                        ->where('owner_type', $placeholder instanceof Placeholder ? $placeholder->owner_type : '')
                        ->where('owner_id', $placeholder instanceof Placeholder ? $placeholder->owner_id : 0)),
            ],
            'fallback' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PlaceholderType::class)],
        ];
    }
}
