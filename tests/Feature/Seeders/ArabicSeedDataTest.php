<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('seeded Arabic data stays UTF-8 and JSON casts are not double encoded', function () {
    $this->seed(DatabaseSeeder::class);

    $organization = Organization::query()
        ->where('email', 'info@molhamteam.com')
        ->firstOrFail();

    expect($organization->name)->toBe('فريق ملهم التطوعي')
        ->and($organization->location)->toBe('سوريا')
        ->and($organization->social_media)->toBeArray()
        ->and($organization->social_media['source'])->toBe('https://molhamteam.com/campaigns');

    $report = Report::query()
        ->where('title', 'نشاط مشبوه في حملة')
        ->firstOrFail();

    expect($report->description)->toBe('المعلومات المعلنة في الحملة لا تتطابق مع الأنشطة المنفذة على أرض الواقع.')
        ->and($report->evidence)->toBeArray()
        ->and($report->timeline)->toBeArray();

    $admin = User::query()->where('email', 'admin@jod.com')->firstOrFail();
    Sanctum::actingAs($admin);

    $response = $this->getJson("/api/v1/admin/organizations/{$organization->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'فريق ملهم التطوعي')
        ->assertJsonPath('data.location', 'سوريا');

    expect($response->getContent())
        ->toContain('فريق ملهم التطوعي')
        ->not->toContain('\\u0641\\u0631');
});
