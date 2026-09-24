<?php

namespace App\Http\Requests\Campaigns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignStepRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'day' => ['required', 'integer', 'min:0', 'max:365'],
            'time' => ['nullable', 'date_format:H:i'],
            'is_threaded' => ['sometimes', 'boolean'],
        ];
    }
}
