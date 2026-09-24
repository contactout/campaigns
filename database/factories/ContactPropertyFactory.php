<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactProperty>
 */
class ContactPropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'contact_field_id' => ContactField::factory(),
            'value' => fake()->word(),
        ];
    }
}
