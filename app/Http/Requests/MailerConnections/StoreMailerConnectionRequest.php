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
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'between:1,65535'],
            'imap_encryption' => ['nullable', Rule::in(['ssl', 'tls', 'starttls', 'none'])],
            'imap_username' => ['nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:255'],
            'sending_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'min_seconds_between_sends' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }

    /**
     * Build the connection's sending caps; a blank value means no cap.
     *
     * @return array{sending_limit: int|null, min_seconds_between_sends: int|null}
     */
    public function limits(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return [
            'sending_limit' => isset($data['sending_limit']) ? (int) $data['sending_limit'] : null,
            'min_seconds_between_sends' => isset($data['min_seconds_between_sends']) ? (int) $data['min_seconds_between_sends'] : null,
        ];
    }

    /**
     * Build the normalized settings array stored in the encrypted column.
     *
     * Blank IMAP fields are stored as null, and a blank password is omitted so
     * updates can preserve the existing credential.
     *
     * @return array{host: string, port: int, username: string|null, password?: string, encryption: string, from_email: string, from_name: string|null, imap_host: string|null, imap_port: int|null, imap_encryption: string|null, imap_username: string|null, imap_password?: string}
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
            'imap_host' => ($data['imap_host'] ?? null) ?: null,
            'imap_port' => isset($data['imap_port']) ? (int) $data['imap_port'] : null,
            'imap_encryption' => ($data['imap_encryption'] ?? null) ?: null,
            'imap_username' => ($data['imap_username'] ?? null) ?: null,
        ];

        if (isset($data['password']) && $data['password'] !== '') {
            $settings['password'] = (string) $data['password'];
        }

        if (isset($data['imap_password']) && $data['imap_password'] !== '') {
            $settings['imap_password'] = (string) $data['imap_password'];
        }

        return $settings;
    }
}
