<?php

declare(strict_types=1);
use App\Models\User;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('legacy non expiring mobile token cannot access hardened routes', function () {
    $user = User::factory()->create();
    $legacyToken = $user->createToken('mobile-token')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$legacyToken)
        ->getJson('/api/mobile/me')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'access_token_required');
});
