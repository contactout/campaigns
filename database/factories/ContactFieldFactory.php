<?php

namespace Database\Factories;

use App\Enums\PlaceholderType;
use App\Models\ContactField;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactField>
 */
class ContactFieldFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'type' => PlaceholderType::Text,
            'fallback' => null,
            'position' => 0,
        ];
    }

    /**
     * Indicate that the field belongs to the given team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    /**
     * Indicate the field type.
     */
    public function ofType(PlaceholderType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
