<?php

namespace App\Http\Requests\Campaigns;

use App\Concerns\ResolvesCurrentTeam;
use App\Rules\KnownPlaceholders;
use App\Services\Mail\MergeFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignStepRequest extends FormRequest
{
    use ResolvesCurrentTeam;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fields = MergeFields::forTeam($this->resolveCurrentTeam($this)->id);

        return [
            'subject' => ['required', 'string', 'max:255', new KnownPlaceholders($fields)],
            'body' => ['required', 'string', new KnownPlaceholders($fields, allowSignature: true, html: true)],
            'day' => ['required', 'integer', 'min:0', 'max:365'],
            'time' => ['nullable', 'date_format:H:i'],
            'is_threaded' => ['sometimes', 'boolean'],
        ];
    }
}
