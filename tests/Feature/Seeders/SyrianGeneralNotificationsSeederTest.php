<?php

declare(strict_types=1);

use App\Enums\NotificationEventType;

it('defines realistic general notifications with valid event categories', function (): void {
    $data = json_decode(
        (string) file_get_contents(database_path('data/syrian_real_notifications.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $notifications = collect($data['notifications'] ?? []);

    expect($notifications)->toHaveCount(20);
    expect($notifications->pluck('key')->unique())->toHaveCount(20);
    expect($notifications->where('priority', 'high')->count())->toBeGreaterThanOrEqual(5);
    expect($notifications->where('scope', 'users')->count())->toBeGreaterThanOrEqual(5);
    expect($notifications->where('category', 'campaign')->count())->toBeGreaterThanOrEqual(8);
    expect($notifications->where('category', 'post')->count())->toBeGreaterThanOrEqual(6);
    expect($notifications->where('category', 'system')->count())->toBeGreaterThanOrEqual(2);

    foreach ($notifications as $notification) {
        $eventType = NotificationEventType::tryFrom((string) $notification['event_type']);
        expect($eventType)->not->toBeNull();
        expect($eventType?->category())->toBe($notification['category']);
        expect(trim((string) $notification['title']))->not->toBe('');
        expect(trim((string) $notification['body']))->not->toBe('');
        expect($notification['priority'])->toBeIn(['normal', 'high']);
        expect($notification['scope'])->toBeIn(['all', 'users', 'organizations']);
    }
});

it('keeps technical seed wording out of notification copy', function (): void {
    $json = (string) file_get_contents(database_path('data/syrian_real_notifications.json'));

    foreach ([
        'بيانات JOD',
        'seed data',
        'documented',
        'نوع التوثيق',
        'المصدر المرجعي',
    ] as $forbidden) {
        expect(mb_strtolower($json))->not->toContain(mb_strtolower($forbidden));
    }
});
