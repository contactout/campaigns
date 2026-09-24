<?php

namespace App\Http\Requests\Campaigns;

use App\Concerns\ResolvesCurrentTeam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
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
            'timezone' => ['required', 'string', 'timezone'],
            'mailer_connection_id' => [
                'nullable',
                'integer',
                Rule::exists('mailer_connections', 'id')->where('team_id', $teamId),
            ],
        ];
    }
}
