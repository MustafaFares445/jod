<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('authenticated password change requires a six digit verification code', function () {
    $user = User::factory()->create(['password' => bcrypt('current-password')]);
    Sanctum::actingAs($user);

    $this->postJson('/api/mobile/me/change-password/code', [
        'currentPassword' => 'current-password',
    ])->assertOk()->assertJsonPath('data.verificationRequired', true);

    DB::table('password_reset_tokens')->where('email', $user->email)->update([
        'token' => '123456',
        'created_at' => now(),
    ]);

    $this->patchJson('/api/mobile/me/change-password', [
        'currentPassword' => 'current-password',
        'code' => '123456',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk()->assertJsonPath('data.passwordChanged', true);

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
