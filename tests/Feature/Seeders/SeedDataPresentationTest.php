<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('does not persist demo or seed terminology in user-facing seeded data', function (): void {
    $this->seed(DatabaseSeeder::class);

    $textColumns = [
        'categories' => ['name', 'description'],
        'organizations' => ['name', 'description', 'short_address', 'location'],
        'organization_roles' => ['name', 'description'],
        'organization_staff' => ['name'],
        'users' => ['name', 'bio', 'location', 'address', 'city', 'governorate'],
        'campaigns' => ['title', 'summary', 'content', 'closed_reason', 'rejection_reason', 'location'],
        'posts' => ['title', 'summary', 'content', 'urgency_reason', 'block_reason', 'location'],
        'articles' => ['title', 'excerpt', 'content', 'author_name'],
        'reports' => ['title', 'description'],
        'badges' => ['name', 'description', 'criteria'],
        'groups' => ['name', 'description', 'category', 'location', 'purpose', 'rejection_reason', 'suspension_reason'],
        'group_posts' => ['body'],
        'group_comments' => ['body'],
        'notifications' => ['title', 'body', 'recipient_label', 'reference_label'],
        'media' => ['description', 'original_name'],
        'capabilities' => ['name', 'description'],
    ];

    $forbidden = [
        'بيانات jod',
        'هذا سجل تجريبي',
        'تجريبي',
        'تجريبية',
        'نوع التوثيق',
        'المصدر المرجعي',
        'demo data',
        'seed data',
        'test data',
        'synthetic',
        'provenance',
        'documented',
        'program_based',
        '@demo.',
        'jod-demo',
    ];

    foreach ($textColumns as $table => $columns) {
        if (! Schema::hasTable($table)) {
            continue;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $value) {
                $normalized = mb_strtolower((string) $value);
                foreach ($forbidden as $marker) {
                    expect($normalized)
                        ->not->toContain(mb_strtolower($marker), "Forbidden marker [{$marker}] found in {$table}.{$column}");
                }
            }
        }
    }

    foreach ([
        ['organizations', 'email'],
        ['organizations', 'organization_number'],
        ['organizations', 'bank_account_number'],
        ['organizations', 'bank_name'],
        ['organizations', 'iban'],
        ['users', 'email'],
        ['organization_staff', 'email'],
        ['media', 'path'],
    ] as [$table, $column]) {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            continue;
        }

        foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $value) {
            expect((string) $value)
                ->not->toMatch('/\bdemo\b|jod-demo|@demo\.|demo\/syria\//i');
        }
    }
});

it('replaces legacy articles and reports with natural Arabic content', function (): void {
    $this->seed(DatabaseSeeder::class);

    if (Schema::hasTable('articles')) {
        expect(DB::table('articles')->count())->toBeGreaterThanOrEqual(8)
            ->and(DB::table('articles')->where('status', 'published')->count())->toBeGreaterThanOrEqual(8);

        $article = DB::table('articles')->where('slug', 'student-support-during-crises')->first();
        expect($article)->not->toBeNull()
            ->and((string) $article->title)->toContain('دعم الطلاب');
    }

    if (Schema::hasTable('reports')) {
        expect(DB::table('reports')->count())->toBeGreaterThanOrEqual(6)
            ->and(DB::table('reports')->where('entity_type', 'post')->count())->toBeGreaterThanOrEqual(6);
    }
});
