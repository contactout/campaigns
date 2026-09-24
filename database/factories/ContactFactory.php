<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
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
            'name' => fake()->name(),
            'source' => 'manual',
            'avatar_url' => null,
            'status' => ContactStatus::NotContacted,
            'timezone' => null,
            'last_contacted_at' => null,
            'last_responded_at' => null,
            'do_not_contact_at' => null,
            'do_not_contact_by' => null,
        ];
    }

    /**
     * Indicate that the contact belongs to the given team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }
}
