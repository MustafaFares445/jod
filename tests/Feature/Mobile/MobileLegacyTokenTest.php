<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLegacyTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_non_expiring_mobile_token_cannot_access_hardened_routes(): void
    {
        $user = User::factory()->create();
        $legacyToken = $user->createToken('mobile-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$legacyToken)
            ->getJson('/api/mobile/me')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'access_token_required');
    }
}
