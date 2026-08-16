<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAuthAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_registration_accepts_current_screen_field_names_without_email(): void
    {
        $response = $this->postJson('/api/mobile/auth/register', [
            'firstName' => 'Ahmad',
            'lastName' => 'Mohammad',
            'phoneNumber' => '0999999999',
            'password' => 'Password123!',
            'confirmPassword' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Ahmad Mohammad')
            ->assertJsonPath('data.user.phone', '0999999999')
            ->assertJsonPath('data.user.email', null);

        $this->assertDatabaseHas('users', [
            'name' => 'Ahmad Mohammad',
            'phone' => '0999999999',
            'email' => null,
        ]);
    }

    public function test_mobile_login_accepts_phone_number_screen_alias(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '0999999994',
            'password' => Hash::make('Password123!'),
        ]);

        $this->postJson('/api/mobile/auth/login', [
            'phoneNumber' => $user->phone,
            'password' => 'Password123!',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.phone', $user->phone);
    }

    public function test_phone_first_account_can_apply_and_donate_without_placeholder_email(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '0999999993',
        ]);
        $organization = Organization::factory()->create();
        $volunteerCampaign = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Volunteer Campaign',
            'category' => 'employment',
            'status' => 'active',
            'organization_id' => $organization->id,
        ]);
        Post::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Volunteer with us',
            'type' => 'volunteer_opportunity',
            'status' => 'published',
            'organization_id' => $organization->id,
            'campaign_id' => $volunteerCampaign->id,
            'published_at' => now(),
        ]);
        $donationCampaign = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Donation Campaign',
            'category' => 'emergency',
            'status' => 'active',
            'organization_id' => $organization->id,
            'goal_amount' => 1000,
            'raised_amount' => 0,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/campaigns/{$volunteerCampaign->id}/applications", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->postJson("/api/mobile/campaigns/{$donationCampaign->id}/donations", [
            'amount' => 25,
            'paymentMethod' => 'cash',
        ])->assertOk()
            ->assertJsonPath('data.amount', 25);

        $this->assertDatabaseHas('campaign_applications', [
            'created_by' => $user->id,
            'campaign_id' => $volunteerCampaign->id,
            'email' => null,
        ]);
        $this->assertDatabaseHas('donations', [
            'created_by' => $user->id,
            'campaign_id' => $donationCampaign->id,
            'email' => null,
        ]);
    }

    public function test_phone_first_account_can_complete_four_digit_password_reset_flow(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '0999999998',
            'password' => Hash::make('OldPassword123!'),
        ]);
        $sentCode = null;

        config()->set('mobile_auth.password_reset.webhook_url', 'https://sms.example.test/reset');
        config()->set('mobile_auth.password_reset.webhook_token', 'secret-token');

        Http::fake(function ($request) use (&$sentCode) {
            $sentCode = $request['code'];

            return Http::response(['accepted' => true], 200);
        });

        $this->postJson('/api/mobile/auth/forgot-password', [
            'phoneNumber' => $user->phone,
        ])->assertOk()
            ->assertJsonPath('data.resetCodeSent', true);

        $this->assertIsString($sentCode);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $sentCode);

        $stored = DB::table('mobile_password_reset_codes')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($stored);
        $this->assertNotSame($sentCode, $stored->code_hash);
        $this->assertSame(0, (int) $stored->attempts);

        $this->postJson('/api/mobile/auth/verify-reset-code', [
            'phoneNumber' => $user->phone,
            'code' => $sentCode,
        ])->assertOk()
            ->assertJsonPath('data.resetCodeVerified', true);

        $this->postJson('/api/mobile/auth/reset-password', [
            'phoneNumber' => $user->phone,
            'code' => $sentCode,
            'newPassword' => 'NewPassword123!',
            'confirmPassword' => 'NewPassword123!',
        ])->assertOk()
            ->assertJsonPath('data.resetPasswordUpdated', true);

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseMissing('mobile_password_reset_codes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_four_digit_reset_code_is_invalidated_after_maximum_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '0999999997',
        ]);
        $sentCode = null;

        config()->set('mobile_auth.password_reset.max_attempts', 5);
        config()->set('mobile_auth.password_reset.webhook_url', 'https://sms.example.test/reset');

        Http::fake(function ($request) use (&$sentCode) {
            $sentCode = $request['code'];

            return Http::response([], 200);
        });

        $this->postJson('/api/mobile/auth/forgot-password', [
            'phoneNumber' => $user->phone,
        ])->assertOk();

        $wrongCode = $sentCode === '0000' ? '0001' : '0000';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/mobile/auth/verify-reset-code', [
                'phoneNumber' => $user->phone,
                'code' => $wrongCode,
            ])->assertUnprocessable()
                ->assertJsonPath('error.code', 'invalid_reset_code');
        }

        $this->assertDatabaseMissing('mobile_password_reset_codes', [
            'user_id' => $user->id,
        ]);

        $this->postJson('/api/mobile/auth/verify-reset-code', [
            'phoneNumber' => $user->phone,
            'code' => $sentCode,
        ])->assertUnprocessable();
    }

    public function test_unknown_phone_does_not_reveal_account_existence(): void
    {
        config()->set('mobile_auth.password_reset.webhook_url', 'https://sms.example.test/reset');
        Http::fake();

        $this->postJson('/api/mobile/auth/forgot-password', [
            'phoneNumber' => '0999999996',
        ])->assertOk()
            ->assertJsonPath('data.resetCodeSent', true);

        Http::assertNothingSent();
        $this->assertDatabaseCount('mobile_password_reset_codes', 0);
    }

    public function test_failed_sms_delivery_does_not_leave_usable_reset_code(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '0999999995',
        ]);

        config()->set('mobile_auth.password_reset.webhook_url', 'https://sms.example.test/reset');
        Http::fake([
            'https://sms.example.test/reset' => Http::response([], 503),
        ]);

        $this->postJson('/api/mobile/auth/forgot-password', [
            'phoneNumber' => $user->phone,
        ])->assertStatus(503)
            ->assertJsonPath('error.code', 'reset_delivery_failed');

        $this->assertDatabaseMissing('mobile_password_reset_codes', [
            'user_id' => $user->id,
        ]);
    }
}
