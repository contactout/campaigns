<?php

namespace App\Http\Requests\Campaigns;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignRecipientsRequest extends FormRequest
{
    use ResolvesCurrentTeam;

    /**
     * Reject another team's campaign as missing, not as forbidden.
     *
     * `{campaign}` is an implicit route binding, so it resolves any campaign id.
     * Without this check the `idle_only` rule below would answer for another
     * team's campaign: 302 back with an error when it is running, 404 when the
     * campaign is not started. That difference is a tenancy oracle over another
     * team's state, so a foreign id has to fail exactly like a nonexistent one.
     */
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        abort_unless(
            $campaign instanceof Campaign
                && $campaign->team_id === $this->resolveCurrentTeam($this)->id,
            404,
        );

        return true;
    }

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
            // Sent by the contacts grid's "Add to campaign" dialog, which only
            // offers Draft and Stopped campaigns. The campaign detail's own
            // Add recipients dialog omits it, because running campaigns accept
            // recipients by design.
            'idle_only' => ['sometimes', 'boolean'],
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

                $campaign = $this->route('campaign');

                if ($this->boolean('idle_only')
                    && $campaign instanceof Campaign
                    && ! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Stopped], true)) {
                    // Closes the window between the dialog's partial reload and
                    // this POST: another member can start the campaign in between.
                    $validator->errors()->add('campaign', __('That campaign has started and can no longer receive contacts.'));
                }
            },
        ];
    }
}
