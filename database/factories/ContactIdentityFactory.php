<?php

namespace Database\Factories;

use App\Enums\ContactIdentityType;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactIdentity>
 */
class ContactIdentityFactory extends Factory
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
            'contact_id' => Contact::factory(),
            'identity_type' => ContactIdentityType::Email,
            'normalized_value' => fake()->unique()->safeEmail(),
        ];
    }
}
