<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    protected $model = Donation::class;

    /**
     * @return array{
     *     organization_id: Factory<Organization>,
     *     campaign_id: null,
     *     name: string,
     *     email: string,
     *     phone: string,
     *     campaign_title: string,
     *     amount_or_type: float,
     *     donated_at: \Illuminate\Support\Carbon,
     *     city: string,
     *     source: string,
     *     payment_method: string,
     *     campaign_ref: null,
     *     assigned_to: null,
     *     internal_notes: null,
     *     created_by: Factory<User>
     * }
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'campaign_id' => null,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'campaign_title' => $this->faker->sentence(3),
            'amount_or_type' => $this->faker->randomFloat(2, 1, 1000),
            'donated_at' => now(),
            'city' => $this->faker->city(),
            'source' => 'mobile_app',
            'payment_method' => 'cash',
            'campaign_ref' => null,
            'assigned_to' => null,
            'internal_notes' => null,
            'created_by' => User::factory(),
            'is_anonymous' => false,
        ];
    }
}
