<?php

namespace App\Http\Requests\MailerConnections;

use App\Concerns\ResolvesCurrentTeam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailerConnectionSignatureRequest extends FormRequest
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
            'signature_id' => [
                'nullable',
                'integer',
                Rule::exists('signatures', 'id')->where('team_id', $teamId),
            ],
        ];
    }
}
