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
        ->where('email', 'contact@helpfoundation.org')
        ->firstOrFail();

    expect($organization->name)->toBe('مؤسسة العون')
        ->and($organization->owner_full_name)->toBe('سارة أحمد')
        ->and($organization->location)->toBe('عمّان، الأردن')
        ->and($organization->social_media)->toBeArray()
        ->and($organization->social_media['facebook'])->toBe('facebook.com/helpfoundation');

    $report = Report::query()
        ->where('title', 'نشاط مشبوه في حملة')
        ->firstOrFail();

    expect($report->description)->toBe('المعلومات المعلنة في الحملة لا تتطابق مع الأنشطة المنفذة على أرض الواقع.')
        ->and($report->evidence)->toBeArray()
        ->and($report->timeline)->toBeArray()
        ->and($report->evidence[1]['content'])->toBe('تعرض الحملة تبرعات دون نشر تحديثات واضحة عن الأنشطة.')
        ->and($report->timeline[0]['note'])->toBe('تم إرسال البلاغ.');

    $admin = User::query()->where('email', 'admin@jod.com')->firstOrFail();
    Sanctum::actingAs($admin);

    $response = $this->getJson("/api/v1/admin/organizations/{$organization->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'مؤسسة العون')
        ->assertJsonPath('data.ownerFullName', 'سارة أحمد')
        ->assertJsonPath('data.location', 'عمّان، الأردن');

    expect($response->getContent())
        ->toContain('مؤسسة العون')
        ->toContain('سارة أحمد')
        ->not->toContain('\\u0645\\u0624');
});
