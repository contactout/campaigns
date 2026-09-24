<?php

namespace App\Http\Requests\Contacts;

use App\Concerns\ResolvesCurrentTeam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactPropertyRequest extends FormRequest
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
            'contact_field_id' => [
                'required',
                'integer',
                Rule::exists('contact_fields', 'id')->where(fn (Builder $query) => $query
                    ->where('team_id', $teamId)),
            ],
            'value' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
