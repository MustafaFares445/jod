<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'action' => $this->faker->randomElement(['create', 'update', 'delete', 'approve', 'reject', 'publish']),
            'entity_type' => $this->faker->randomElement(['Post', 'Campaign', 'User', 'Organization']),
            'entity_id' => $this->faker->numberBetween(1, 100),
            'metadata' => $this->faker->boolean(50) ? ['reason' => $this->faker->sentence()] : null,
            'at' => now()->subDays(rand(0, 30)),
        ];
    }
}
