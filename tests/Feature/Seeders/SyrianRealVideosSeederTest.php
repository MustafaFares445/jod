<?php

declare(strict_types=1);

test('real Syrian video seed manifest contains reusable Syria media within upload limits', function (): void {
    $data = json_decode(
        (string) file_get_contents(database_path('data/syrian_real_videos.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $videos = $data['videos'] ?? [];

    expect($videos)->toHaveCount(6);
    expect(array_values(array_unique(array_column($videos, 'key'))))->toHaveCount(6);

    foreach ($videos as $video) {
        expect($video['source_page'])->toStartWith('https://commons.wikimedia.org/wiki/File:');
        expect($video['file_name'])->toEndWith('.webm');
        expect((int) $video['expected_size'])->toBeGreaterThan(1024);
        expect((int) $video['expected_size'])->toBeLessThanOrEqual(100 * 1024 * 1024);
        expect($video['license'])->toBeIn(['CC BY 3.0', 'CC BY-SA 3.0']);
        expect($video['license_url'])->toStartWith('https://creativecommons.org/licenses/');
        expect($video['location'])->not->toBeEmpty();
        expect($video['title'])->not->toBeEmpty();
        expect($video['summary'])->not->toBeEmpty();
    }

    expect(collect($videos)->where('location', 'حلب'))->not->toBeEmpty();
    expect(collect($videos)->where('location', 'دمشق'))->not->toBeEmpty();
    expect(collect($videos)->where('location', 'السويداء'))->not->toBeEmpty();
    expect(collect($videos)->where('audience', 'student'))->toHaveCount(2);
});
