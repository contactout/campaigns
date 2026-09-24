<?php

namespace App\Http\Requests\Contacts;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
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
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('contact_identities', 'normalized_value')
                    ->where(fn (Builder $query) => $query
                        ->where('team_id', $teamId)
                        ->where('identity_type', ContactIdentityType::Email->value)),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'lists' => ['nullable', 'array'],
            'lists.*' => [Rule::exists('contact_lists', 'id')->where('team_id', $teamId)],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email) && $email !== '') {
            $this->merge(['email' => ContactIdentityType::Email->normalize($email)]);
        }

        $phone = $this->input('phone');

        if (is_string($phone) && $phone !== '') {
            $this->merge([
                'phone' => ContactIdentityType::Phone->normalize($phone) ?: null,
            ]);
        }
    }
}
