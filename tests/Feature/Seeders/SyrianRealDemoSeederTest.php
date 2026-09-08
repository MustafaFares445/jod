<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('Syrian seed cleanup column names match the current publisher schemas', function () {
    expect(Schema::hasColumn('publisher_follows', 'target_type'))->toBeTrue()
        ->and(Schema::hasColumn('hidden_publishers', 'publisher_type'))->toBeTrue()
        ->and(Schema::hasColumn('hidden_publishers', 'target_type'))->toBeFalse();
});

test('Syrian demo seed provides broad realistic content across backend types', function () {
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('organizations')->count())->toBeGreaterThanOrEqual(32)
        ->and(DB::table('campaigns')->count())->toBeGreaterThanOrEqual(340)
        ->and(DB::table('posts')->count())->toBeGreaterThanOrEqual(700)
        ->and(DB::table('categories')->count())->toBe(12);

    $expectedTypes = [
        'general',
        'job_opportunity',
        'campaign_teaser',
        'campaign_update',
        'campaign_summary',
        'donation_campaign',
        'service_offer',
        'volunteer_opportunity',
        'awareness',
        'help_request',
    ];

    $types = DB::table('posts')->distinct()->pluck('type')->all();
    foreach ($expectedTypes as $type) {
        expect($types)->toContain($type);
    }

    expect(DB::table('organizations')->where('name', 'فريق ملهم التطوعي')->exists())->toBeTrue()
        ->and(DB::table('organizations')->where('name', 'مؤسسة سند الشباب التنموية')->exists())->toBeTrue()
        ->and(DB::table('organizations')->where('name', 'محافظة حلب')->exists())->toBeTrue()
        ->and(DB::table('campaigns')->where('title', 'حلب ست الكل')->where('location', 'حلب')->exists())->toBeTrue()
        ->and(DB::table('campaigns')->where('title', 'أربعاء الرستن')->where('location', 'حمص')->exists())->toBeTrue()
        ->and(DB::table('campaigns')->where('title', 'فجر القصير')->where('location', 'حمص')->exists())->toBeTrue()
        ->and(DB::table('campaigns')->where('title', 'Scale Up 2026')->exists())->toBeTrue();

    $aleppo = DB::table('campaigns')->where('title', 'حلب ست الكل')->first();
    expect($aleppo)->not->toBeNull()
        ->and($aleppo->content)->toContain('426,879,000')
        ->and((float) $aleppo->raised_amount)->toBe(0.0);

    expect(DB::table('posts')->where('type', 'help_request')->count())->toBeGreaterThanOrEqual(8)
        ->and(DB::table('posts')->where('type', 'help_request')->where('urgency', 'critical')->exists())->toBeTrue();
});

test('visible Syrian seed content does not expose seed metadata or provenance language', function () {
    $this->seed(DatabaseSeeder::class);

    $forbidden = [
        'بيانات JOD',
        'المصدر المرجعي',
        'نوع التوثيق',
        'هذا سجل تجريبي',
        'ضمن بيانات JOD',
        'المصدر الرسمي',
        'صورة افتراضية ثابتة',
    ];

    foreach ($forbidden as $phrase) {
        expect(DB::table('posts')->where('title', 'like', '%'.$phrase.'%')->exists())->toBeFalse()
            ->and(DB::table('posts')->where('summary', 'like', '%'.$phrase.'%')->exists())->toBeFalse()
            ->and(DB::table('posts')->where('content', 'like', '%'.$phrase.'%')->exists())->toBeFalse()
            ->and(DB::table('campaigns')->where('summary', 'like', '%'.$phrase.'%')->exists())->toBeFalse()
            ->and(DB::table('campaigns')->where('content', 'like', '%'.$phrase.'%')->exists())->toBeFalse()
            ->and(DB::table('organizations')->where('description', 'like', '%'.$phrase.'%')->exists())->toBeFalse();
    }

    expect(DB::table('posts')->where('content', 'like', '%نبحث عن جهة أو شخص قادر على تقديم المساعدة%')->exists())->toBeTrue()
        ->and(DB::table('posts')->where('type', 'campaign_update')->where('content', 'like', '%تواصل الفرق تنفيذ الأنشطة%')->exists())->toBeTrue();
});

test('Syrian demo media uses official campaign images or deterministic local fallbacks only', function () {
    $this->seed(DatabaseSeeder::class);

    $media = DB::table('media')->get(['path', 'mime_type', 'description']);
    expect($media->count())->toBeGreaterThan(700);

    foreach ($media as $item) {
        expect((string) $item->path)->toStartWith('demo/syria/')
            ->and(strtolower((string) $item->path))->not->toContain('unsplash')
            ->and(strtolower((string) $item->path))->not->toContain('pexels')
            ->and(strtolower((string) $item->path))->not->toContain('mountain')
            ->and(strtolower((string) $item->path))->not->toContain('forest')
            ->and((string) $item->description)->not->toContain('المصدر الرسمي')
            ->and((string) $item->description)->not->toContain('افتراضية');
    }

    expect($media->contains(fn ($item) => (string) $item->mime_type === 'image/svg+xml'))->toBeTrue();
});
