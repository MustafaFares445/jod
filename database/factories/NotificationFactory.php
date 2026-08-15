<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array{
     *     title: string,
     *     body: string,
     *     mailbox: string,
     *     status: string,
     *     category: string,
     *     recipient_scope: string,
     *     recipient_label: null,
     *     priority: string,
     *     reference_label: null,
     *     reference_path: null,
     *     organization_id: null,
     *     creator_id: null,
     *     recipient_id: UserFactory,
     *     sent_at: Carbon,
     *     read_at: null
     * }
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'system',
            'recipient_scope' => 'users',
            'recipient_label' => null,
            'priority' => 'normal',
            'reference_label' => null,
            'reference_path' => null,
            'organization_id' => null,
            'creator_id' => null,
            'recipient_id' => User::factory(),
            'sent_at' => now(),
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => [
            'status' => 'read',
            'read_at' => now(),
        ]);
    }
}
