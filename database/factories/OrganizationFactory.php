<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'organization_type' => $this->faker->randomElement(['NGO', 'Charity', 'Government']),
            'registration_number' => $this->faker->numerify('ORG-###-###'),
            'establishment_date' => $this->faker->date(),
            'short_address' => $this->faker->address(),
            'location' => $this->faker->city(),
            'description' => $this->faker->paragraph(),
            'license_document_name' => null,
            'delegation_document_name' => null,
            'owner_full_name' => $this->faker->name(),
            'owner_email' => $this->faker->email(),
            'owner_phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->url(),
            'social_media' => [],
            'status' => 'active',
            'verification_status' => 'verified',
            'accepted_at' => now(),
        ];
    }
}
