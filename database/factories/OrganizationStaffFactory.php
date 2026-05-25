<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationStaffFactory extends Factory
{
    protected $model = OrganizationStaff::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => null,
            'organization_role_id' => OrganizationRole::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
            'invitation_token' => null,
        ];
    }
}
