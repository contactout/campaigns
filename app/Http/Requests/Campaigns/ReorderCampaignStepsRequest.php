<?php

namespace App\Http\Requests\Campaigns;

use App\Models\Campaign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCampaignStepsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $campaign = $this->route('campaign');
        $campaignId = $campaign instanceof Campaign ? $campaign->id : null;

        return [
            'steps' => ['required', 'array'],
            'steps.*' => [
                'integer',
                Rule::exists('campaign_steps', 'id')->where('campaign_id', $campaignId),
            ],
        ];
    }
}
