<?php

namespace App\Http\Requests\Campaigns;

use App\Concerns\ResolvesCurrentTeam;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignRecipientsRequest extends FormRequest
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
            'contacts' => ['nullable', 'array'],
            'contacts.*' => [
                'integer',
                Rule::exists('contacts', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'lists' => ['nullable', 'array'],
            'lists.*' => [
                'integer',
                Rule::exists('contact_lists', 'id')->where('team_id', $teamId),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (empty($this->input('contacts')) && empty($this->input('lists'))) {
                    $validator->errors()->add('contacts', __('Select at least one contact or list.'));
                }
            },
        ];
    }
}
