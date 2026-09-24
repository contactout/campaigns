<?php

namespace App\Http\Requests\Contacts;

use App\Enums\ContactIdentityType;
use App\Enums\ContactStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactCellRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(['name', 'email', 'phone', 'status', 'timezone'])],
            'value' => [
                'nullable',
                'string',
                'max:255',
                Rule::when(
                    $this->input('field') === 'status',
                    [Rule::enum(ContactStatus::class), 'required'],
                ),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');

        if (! is_string($value)) {
            return;
        }

        $field = $this->input('field');

        if ($field === 'email') {
            $this->merge(['value' => ContactIdentityType::Email->normalize($value)]);

            return;
        }

        if ($field === 'phone') {
            $this->merge(['value' => ContactIdentityType::Phone->normalize($value)]);

            return;
        }

        if ($field === 'name' || $field === 'timezone') {
            $this->merge(['value' => trim($value)]);
        }
    }

    /**
     * Get the field being edited.
     */
    public function field(): string
    {
        return (string) $this->input('field');
    }

    /**
     * Get the normalized value for the field.
     */
    public function value(): ?string
    {
        $value = $this->input('value');

        return is_string($value) ? $value : null;
    }
}
