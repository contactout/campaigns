<?php

namespace App\Http\Requests\Campaigns;

use App\Concerns\ResolvesCurrentTeam;
use App\Data\CampaignSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCampaignRequest extends FormRequest
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
            'settings' => ['sometimes', 'array'],
            'settings.sending_days' => ['required_with:settings', 'array', 'min:1'],
            'settings.sending_days.*' => ['required', 'integer', 'min:1', 'max:7', 'distinct'],
            'settings.sending_hour_from' => ['required_with:settings', 'integer', 'between:0,23'],
            'settings.sending_hour_to' => ['required_with:settings', 'integer', 'between:0,23'],
            'settings.open_tracking' => ['sometimes', 'boolean'],
            'settings.link_tracking' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the validation callbacks that apply to the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('settings')) {
                    return;
                }

                if ($validator->errors()->has('settings.sending_hour_from')
                    || $validator->errors()->has('settings.sending_hour_to')) {
                    return;
                }

                $from = (int) $this->input('settings.sending_hour_from');
                $to = (int) $this->input('settings.sending_hour_to');

                if (! CampaignSettings::isValidHourRange($from, $to)) {
                    $validator->errors()->add(
                        'settings.sending_hour_to',
                        __('The last send hour must be later than the first, or both 00:00 for no restriction.'),
                    );
                }
            },
        ];
    }
}
