<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PersonalizationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('complete demo seed includes realistic personalization recommendation and matching data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('user_preferences')->count())->toBeGreaterThanOrEqual(12)
        ->and(DB::table('user_preferences')->distinct()->count('intent'))->toBe(3)
        ->and(DB::table('user_category_interests')->count())->toBeGreaterThanOrEqual(48)
        ->and(DB::table('user_capabilities')->count())->toBeGreaterThanOrEqual(24)
        ->and(DB::table('publisher_follows')->count())->toBeGreaterThanOrEqual(30)
        ->and(DB::table('category_keywords')->count())->toBeGreaterThanOrEqual(48)
        ->and(DB::table('post_feedback')->count())->toBeGreaterThanOrEqual(10)
        ->and(DB::table('hidden_publishers')->count())->toBeGreaterThanOrEqual(3)
        ->and(DB::table('recommendation_impressions')->count())->toBeGreaterThanOrEqual(200)
        ->and(DB::table('user_interactions')->count())->toBeGreaterThanOrEqual(150)
        ->and(DB::table('post_capabilities')->count())->toBeGreaterThanOrEqual(10);

    expect(DB::table('recommendation_impressions')->distinct()->count('feed_type'))->toBe(4)
        ->and(DB::table('posts')->where('type', 'help_request')->count())->toBeGreaterThanOrEqual(6)
        ->and(DB::table('posts')->where('type', 'help_request')->where('urgency', 'critical')->exists())->toBeTrue()
        ->and(DB::table('posts')->where('type', 'help_request')->where('help_status', 'fulfilled')->exists())->toBeTrue()
        ->and(DB::table('posts')->where('type', 'help_request')->where('help_status', 'partially_fulfilled')->exists())->toBeTrue()
        ->and(DB::table('posts')->where('type', 'help_request')->where('help_status', 'expired')->exists())->toBeTrue();
});

test('personalization demo seeder is idempotent', function () {
    $this->seed(DatabaseSeeder::class);

    $tables = [
        'user_preferences',
        'user_category_interests',
        'user_capabilities',
        'publisher_follows',
        'category_keywords',
        'post_feedback',
        'hidden_publishers',
        'recommendation_impressions',
        'user_interactions',
        'post_capabilities',
    ];

    $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

    $this->seed(PersonalizationDemoSeeder::class);

    foreach ($tables as $table) {
        expect(DB::table($table)->count())->toBe($before[$table], $table.' should not gain duplicate demo rows');
    }
});
