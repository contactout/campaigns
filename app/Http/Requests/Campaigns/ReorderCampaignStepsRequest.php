<?php

namespace App\Http\Requests\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignStep;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderCampaignStepsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'steps' => ['required', 'array'],
            'steps.*' => [
                'integer',
                'distinct',
                Rule::exists('campaign_steps', 'id')->where('campaign_id', $this->campaignId()),
            ],
        ];
    }

    /**
     * Get the validation callbacks that apply to the request.
     *
     * The submitted ids must be a permutation of every step in the campaign:
     * renumbering only a subset would leave two steps sharing a sequence.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var array<int, int|string> $submitted */
                $submitted = $this->input('steps');

                $submittedIds = array_map(intval(...), $submitted);
                sort($submittedIds);

                $stepIds = CampaignStep::query()
                    ->where('campaign_id', $this->campaignId())
                    ->orderBy('id')
                    ->pluck('id')
                    ->all();

                if ($submittedIds !== $stepIds) {
                    $validator->errors()->add(
                        'steps',
                        __('The steps must include every step of the campaign exactly once.'),
                    );
                }
            },
        ];
    }

    /**
     * Get the id of the campaign being reordered.
     */
    private function campaignId(): ?int
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof Campaign ? $campaign->id : null;
    }
}
