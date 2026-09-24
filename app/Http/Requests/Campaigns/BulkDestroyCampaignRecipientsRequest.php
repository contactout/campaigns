<?php

namespace App\Http\Requests\Campaigns;

use App\Models\Campaign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDestroyCampaignRecipientsRequest extends FormRequest
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
            'recipients' => ['required', 'array'],
            'recipients.*' => [
                'integer',
                Rule::exists('recipients', 'id')->where('campaign_id', $campaignId),
            ],
        ];
    }
}
