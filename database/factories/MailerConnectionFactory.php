<?php

namespace Database\Factories;

use App\Enums\MailerConnectionStatus;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailerConnection>
 */
class MailerConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'name' => fake()->word().' inbox',
            'mailer_type' => MailerType::Smtp,
            'smtp_setting' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => fake()->userName(),
                'password' => 'secret',
                'encryption' => 'tls',
                'from_email' => fake()->safeEmail(),
                'from_name' => fake()->name(),
            ],
            'status' => MailerConnectionStatus::Pending,
            'exception_type' => null,
            'exception_data' => null,
            'threw_at' => null,
            'rate_limit_expired_at' => null,
            'sending_limit' => null,
            'sent_count' => 0,
            'sending_limit_refreshed_at' => null,
        ];
    }

    /**
     * Indicate that the mailer connection belongs to the given team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes): array => [
            'team_id' => $team->id,
        ]);
    }
}
