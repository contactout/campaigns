<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\TemplateFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
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
            'folder_id' => null,
            'name' => fake()->words(2, true),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'is_draft' => false,
        ];
    }

    /**
     * Indicate that the template belongs to the given team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    /**
     * Indicate that the template is filed in the given folder.
     */
    public function forFolder(TemplateFolder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $folder->team_id,
            'folder_id' => $folder->id,
        ]);
    }

    /**
     * Indicate that the template is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => true,
        ]);
    }
}
