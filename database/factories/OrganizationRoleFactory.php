<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationRoleFactory extends Factory
{
    protected $model = OrganizationRole::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'permissions' => ['org.campaigns.view', 'org.posts.view'],
            'is_active' => true,
            'is_system' => false,
            'members_count' => $this->faker->numberBetween(0, 5),
        ];
    }
}
