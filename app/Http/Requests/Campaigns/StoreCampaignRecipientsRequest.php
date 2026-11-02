<?php

namespace App\Http\Requests\Campaigns;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Contact;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
     * team's state, so a foreign id has to fail exactly like a nonexistent one —
     * including the JSON body, which is why this throws the binding's own
     * exception instead of aborting.
     */
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        if (! $campaign instanceof Campaign) {
            throw (new ModelNotFoundException)->setModel(Campaign::class);
        }

        if ($campaign->team_id === $this->resolveCurrentTeam($this)->id) {
            return true;
        }

        throw (new ModelNotFoundException)->setModel(Campaign::class, [$campaign->id]);
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
            // Existence is checked for the whole array at once in after().
            'contacts.*' => ['integer'],
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

                $this->validateContactsExist($validator);

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

    /**
     * Flag every submitted contact that is not a live contact of the team.
     *
     * A `Rule::exists` on `contacts.*` runs one query per id, so a large
     * selection would run thousands of queries before the action starts. This
     * checks them all in one query and reports each missing id under its own
     * index, exactly like the per-element rule did.
     */
    private function validateContactsExist(Validator $validator): void
    {
        $contacts = $this->input('contacts');

        if (! is_array($contacts)) {
            return;
        }

        // Elements that already failed the `integer` rule keep that error alone.
        $contacts = array_filter(
            $contacts,
            fn (mixed $id, int|string $index): bool => ! $validator->errors()->has("contacts.{$index}"),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($contacts === []) {
            return;
        }

        $existing = array_flip(Contact::query()
            ->forTeam($this->resolveCurrentTeam($this)->id)
            ->whereIntegerInRaw('id', array_map(intval(...), $contacts))
            ->pluck('id')
            ->all());

        foreach ($contacts as $index => $id) {
            if (! isset($existing[(int) $id])) {
                $validator->errors()->add("contacts.{$index}", __('validation.exists', [
                    'attribute' => $validator->getDisplayableAttribute("contacts.{$index}"),
                ]));
            }
        }
    }
}
