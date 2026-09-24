<?php

namespace Database\Factories;

use App\Enums\PlaceholderType;
use App\Models\EmailTemplate;
use App\Models\Placeholder;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Placeholder>
 */
class PlaceholderFactory extends Factory
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
            'owner_type' => EmailTemplate::class,
            'owner_id' => EmailTemplate::factory(),
            'name' => fake()->unique()->word(),
            'fallback' => null,
            'type' => PlaceholderType::Text,
        ];
    }

    /**
     * Indicate that the placeholder belongs to the given email template.
     */
    public function forTemplate(EmailTemplate $template): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $template->team_id,
            'owner_type' => $template->getMorphClass(),
            'owner_id' => $template->id,
        ]);
    }

    /**
     * Indicate the placeholder type.
     */
    public function ofType(PlaceholderType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
