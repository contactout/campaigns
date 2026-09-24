<?php

namespace App\Http\Requests\MailerConnections;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMailerConnectionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['required', Rule::in(['none', 'tls', 'ssl'])],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Build the normalized SMTP settings array stored in the encrypted column.
     *
     * A blank password is omitted so updates can preserve the existing credential.
     *
     * @return array{host: string, port: int, username: string|null, password?: string, encryption: string, from_email: string, from_name: string|null}
     */
    public function settings(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $settings = [
            'host' => (string) $data['host'],
            'port' => (int) $data['port'],
            'username' => ($data['username'] ?? null) ?: null,
            'encryption' => (string) $data['encryption'],
            'from_email' => (string) $data['from_email'],
            'from_name' => ($data['from_name'] ?? null) ?: null,
        ];

        if (isset($data['password']) && $data['password'] !== '') {
            $settings['password'] = (string) $data['password'];
        }

        return $settings;
    }
}
