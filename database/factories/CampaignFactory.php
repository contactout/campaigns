<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
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
            'name' => fake()->sentence(3),
            'status' => CampaignStatus::Draft,
            'timezone' => 'UTC',
            'mailer_connection_id' => null,
            'settings' => null,
            'started_at' => null,
            'interrupted_reason' => null,
        ];
    }

    /**
     * Indicate that the campaign belongs to the given team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    /**
     * Indicate that the campaign is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::Active,
            'started_at' => now(),
        ]);
    }
}
