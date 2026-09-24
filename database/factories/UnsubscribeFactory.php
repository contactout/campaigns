<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Unsubscribe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unsubscribe>
 */
class UnsubscribeFactory extends Factory
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
            'campaign_id' => null,
            'recipient_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'reason' => 'recipient',
        ];
    }

    /**
     * Indicate that the unsubscribe belongs to the given team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes): array => [
            'team_id' => $team->id,
        ]);
    }
}
