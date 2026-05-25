<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->grantPermissions($this->user, [
            'platform_settings.view',
            'platform_settings.update',
        ]);
        Sanctum::actingAs($this->user);
        PlatformSetting::truncate();
    }

    public function test_returns_platform_settings(): void
    {
        PlatformSetting::create(['key' => 'siteName', 'value' => json_encode('Test Site')]);
        PlatformSetting::create(['key' => 'allowNewPosts', 'value' => json_encode(true)]);

        $response = $this->getJson('/api/v1/admin/platform-settings');

        $response->assertOk();
        $this->assertEquals('Test Site', $response->json('data.siteName'));
        $this->assertTrue($response->json('data.allowNewPosts'));
    }

    public function test_returns_empty_settings_with_defaults(): void
    {
        $response = $this->getJson('/api/v1/admin/platform-settings');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('siteName', $data);
        $this->assertArrayHasKey('allowNewPosts', $data);
        $this->assertArrayHasKey('requirePostReview', $data);
    }

    public function test_updates_platform_settings(): void
    {
        $payload = [
            'siteName' => 'Updated Site',
            'allowNewPosts' => false,
            'requirePostReview' => true,
        ];

        $response = $this->patchJson('/api/v1/admin/platform-settings', $payload);

        $response->assertOk();
        $this->assertEquals('Updated Site', $response->json('data.siteName'));
        $this->assertFalse($response->json('data.allowNewPosts'));
        $this->assertTrue($response->json('data.requirePostReview'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'siteName',
            'value' => json_encode('Updated Site'),
        ]);
    }

    public function test_updates_partial_settings(): void
    {
        PlatformSetting::create(['key' => 'siteName', 'value' => json_encode('Original Site')]);

        $response = $this->patchJson('/api/v1/admin/platform-settings', ['allowNewPosts' => false]);

        $response->assertOk();

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'siteName',
            'value' => json_encode('Original Site'),
        ]);

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'allowNewPosts',
            'value' => json_encode(false),
        ]);
    }

    public function test_validates_settings_update(): void
    {
        $response = $this->patchJson('/api/v1/admin/platform-settings', [
            'siteName' => str_repeat('a', 256), // exceeds max length
        ]);

        $response->assertUnprocessable();
    }
}
