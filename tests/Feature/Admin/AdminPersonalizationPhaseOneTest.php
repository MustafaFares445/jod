<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Capability;
use App\Models\Post;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function grantAdminPhaseOnePermission(User $user, PermissionGroup $group, PermissionAction $action): void
{
    $name = PermissionNameResolver::resolve($group, $action);
    Permission::findOrCreate($name, 'web');
    $user->givePermissionTo($name);
}

test('admin manages capabilities and reads personalization', function () {
    $admin = User::factory()->create(['user_type' => 'admin']);
    grantAdminPhaseOnePermission($admin, PermissionGroup::CAPABILITY, PermissionAction::VIEW);
    grantAdminPhaseOnePermission($admin, PermissionGroup::CAPABILITY, PermissionAction::CREATE);
    grantAdminPhaseOnePermission($admin, PermissionGroup::USER, PermissionAction::VIEW);
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/admin/capabilities', ['name' => 'Teaching', 'slug' => 'teaching', 'status' => 'active', 'sortOrder' => 10]);
    $response->assertCreated()->assertJsonPath('data.slug', 'teaching');

    $user = User::factory()->create();
    $this->getJson("/api/v1/admin/users/{$user->id}/personalization")
        ->assertOk()
        ->assertJsonPath('data.onboardingCompleted', false);
});

test('admin updates help request lifecycle', function () {
    $admin = User::factory()->create(['user_type' => 'admin']);
    grantAdminPhaseOnePermission($admin, PermissionGroup::POST_REVIEW, PermissionAction::APPROVE);
    Sanctum::actingAs($admin);

    $post = Post::factory()->create(['type' => 'help_request', 'urgency' => 'normal', 'help_status' => 'open']);
    $this->patchJson("/api/v1/admin/posts/{$post->id}/lifecycle", [
        'urgency' => 'critical',
        'urgencyReason' => 'Immediate medical need',
        'fulfillmentStatus' => 'in_progress',
        'expiresAt' => now()->addDays(2)->toIso8601String(),
    ])->assertOk()
        ->assertJsonPath('data.urgency', 'critical')
        ->assertJsonPath('data.fulfillmentStatus', 'in_progress');
});
