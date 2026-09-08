<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('real Syrian volunteer groups are seeded with members categories and posts', function () {
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('groups')->where('status', 'active')->count())->toBeGreaterThanOrEqual(20)
        ->and(DB::table('group_categories')->count())->toBeGreaterThanOrEqual(40)
        ->and(DB::table('group_members')->where('status', 'active')->count())->toBeGreaterThanOrEqual(100)
        ->and(DB::table('posts')->whereNotNull('group_id')->count())->toBeGreaterThanOrEqual(20);

    expect(DB::table('groups')->where('name', 'فريق صناع الغد التطوعي')->where('location', 'ريف دمشق')->exists())->toBeTrue()
        ->and(DB::table('groups')->where('name', 'فريق Advisors التطوعي')->where('location', 'دمشق')->exists())->toBeTrue()
        ->and(DB::table('groups')->where('name', 'فريق نبض التطوعي')->where('location', 'حمص')->exists())->toBeTrue()
        ->and(DB::table('groups')->where('name', 'فريق حرير التطوعي')->where('location', 'طرطوس')->exists())->toBeTrue()
        ->and(DB::table('groups')->where('name', 'فريق نبض الشباب')->where('location', 'السويداء')->exists())->toBeTrue()
        ->and(DB::table('groups')->where('name', 'فريق ألوان التطوعي الشبابي')->where('location', 'حماة')->exists())->toBeTrue()
        ->and(DB::table('groups')->where('name', 'فريق صناع الأثر التطوعي')->where('location', 'درعا')->exists())->toBeTrue();
});

test('group activities preserve documented facts without exposing seed metadata', function () {
    $this->seed(DatabaseSeeder::class);

    $nabd = DB::table('groups')->where('name', 'فريق نبض التطوعي')->first();
    expect($nabd)->not->toBeNull();

    $nabdPost = DB::table('posts')
        ->where('group_id', $nabd->id)
        ->where('title', 'دورات مجانية لتعويض الفاقد التعليمي')
        ->first();

    expect($nabdPost)->not->toBeNull()
        ->and($nabdPost->summary)->toContain('700 طالب وطالبة')
        ->and($nabdPost->audience)->toBe('student');

    $harir = DB::table('groups')->where('name', 'فريق حرير التطوعي')->first();
    $harirStudentPost = DB::table('posts')
        ->where('group_id', $harir->id)
        ->where('title', 'نقل مجاني لطلاب الجامعة خلال الامتحانات')
        ->first();

    expect($harirStudentPost)->not->toBeNull()
        ->and($harirStudentPost->summary)->toContain('1200 طالب وطالبة')
        ->and($harirStudentPost->audience)->toBe('student');

    foreach (['المصدر المرجعي', 'نوع التوثيق', 'بيانات JOD', 'documented', 'seed data'] as $forbidden) {
        expect(DB::table('groups')->where('description', 'like', '%'.$forbidden.'%')->exists())->toBeFalse()
            ->and(DB::table('posts')->whereNotNull('group_id')->where('content', 'like', '%'.$forbidden.'%')->exists())->toBeFalse();
    }
});

test('group media uses official discovered images or local deterministic fallback', function () {
    $this->seed(DatabaseSeeder::class);

    $groupIds = DB::table('groups')->pluck('id');
    $media = DB::table('media')
        ->where('model_type', 'group')
        ->whereIn('model_id', $groupIds)
        ->get(['model_id', 'prop', 'path', 'mime_type']);

    expect($media->count())->toBeGreaterThanOrEqual(40);

    foreach ($groupIds as $groupId) {
        expect($media->where('model_id', $groupId)->where('prop', 'avatar')->isNotEmpty())->toBeTrue()
            ->and($media->where('model_id', $groupId)->where('prop', 'cover')->isNotEmpty())->toBeTrue();
    }

    foreach ($media as $item) {
        expect((string) $item->path)->toStartWith('demo/syria/groups/')
            ->and((string) $item->mime_type)->toStartWith('image/');
    }
});
