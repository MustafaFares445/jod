<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::query()->create([
        'name' => 'Settings Org',
        'email' => 'settings@example.com',
        'bank_name' => 'Initial Bank',
        'iban' => 'JO00TEST0000000000000000000',
    ]);

    $this->user = User::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->grantPermissions($this->user, [
        [PermissionGroup::ORG_SETTINGS, PermissionAction::VIEW],
        [PermissionGroup::ORG_SETTINGS, PermissionAction::UPDATE],
    ]);

    Sanctum::actingAs($this->user);
});
test('returns organization profile and bank account', function () {
    $this->getJson('/api/v1/org/settings/profile')
        ->assertOk()
        ->assertJsonPath('data.name', 'Settings Org');

    $this->getJson('/api/v1/org/settings/bank-account')
        ->assertOk()
        ->assertJsonPath('data.bankName', 'Initial Bank');
});
test('updates organization profile and bank account', function () {
    $this->patchJson('/api/v1/org/settings/profile', [
        'name' => 'Updated Settings Org',
        'email' => 'updated-settings@example.com',
        'phone' => '+962790000010',
    ])->assertOk()->assertJsonPath('data.name', 'Updated Settings Org');

    $this->patchJson('/api/v1/org/settings/bank-account', [
        'bankName' => 'Updated Bank',
        'iban' => 'JO94CBJO0010000000000131000302',
    ])->assertOk()->assertJsonPath('data.bankName', 'Updated Bank');

    $this->assertDatabaseHas('organizations', [
        'id' => $this->organization->id,
        'bank_name' => 'Updated Bank',
        'iban' => 'JO94CBJO0010000000000131000302',
    ]);
});
