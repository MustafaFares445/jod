<?php

declare(strict_types=1);

use App\Enums\NotificationEventType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('group creation persists group notifications without category truncation', function () {
    $owner = User::factory()->create(['status' => 'active']);
    $invited = User::factory()->create(['status' => 'active']);

    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/mobile/groups', [
        'name' => 'Volunteer Support Team',
        'description' => 'A volunteer team created to support community assistance requests.',
        'categories' => ['community'],
        'location' => 'Damascus',
        'rules' => ['Respect all team members and beneficiaries.'],
        'purpose' => 'Coordinate trusted volunteer support for community needs.',
        'invitedUserIds' => [$invited->id],
    ])->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $groupId = (string) $response->json('data.id');

    $this->assertDatabaseHas('notifications', [
        'recipient_id' => $owner->id,
        'category' => 'group',
        'event_type' => NotificationEventType::GroupSubmitted->value,
    ]);
    $this->assertDatabaseHas('notifications', [
        'recipient_id' => $invited->id,
        'category' => 'group',
        'event_type' => NotificationEventType::GroupInvitationCreated->value,
        'reference_label' => 'Volunteer Support Team',
    ]);
    $this->assertDatabaseHas('groups', [
        'id' => $groupId,
        'owner_id' => $owner->id,
        'status' => 'pending',
    ]);
});
