<?php

namespace App\Http\Requests\Contacts;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\PlaceholderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactFieldRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contact_fields', 'name')->where(fn (Builder $query) => $query
                    ->where('team_id', $teamId)),
            ],
            'type' => ['required', Rule::enum(PlaceholderType::class)],
            'fallback' => ['nullable', 'string', 'max:255'],
        ];
    }
}
