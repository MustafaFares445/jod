<?php

declare(strict_types=1);

it('defines real image sources for every seeded content category', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(database_path('data/syrian_real_image_fallbacks.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $fallbacks = $manifest['fallbacks'] ?? [];
    $expected = [
        'organization',
        'health',
        'education',
        'food',
        'emergency',
        'shelter',
        'employment',
        'volunteering',
        'protection',
        'wash',
        'children',
        'disability',
        'reconstruction',
    ];

    foreach ($expected as $category) {
        expect($fallbacks)->toHaveKey($category)
            ->and((string) ($fallbacks[$category]['file_name'] ?? ''))->not->toBe('')
            ->and((string) ($fallbacks[$category]['source_page'] ?? ''))
            ->toStartWith('https://commons.wikimedia.org/wiki/File:');
    }
});

it('does not use generated text cards as the final production fallback strategy', function (): void {
    $source = (string) file_get_contents(database_path('seeders/RealSeedMediaRefinementSeeder.php'));

    expect($source)
        ->toContain("where('mime_type', 'image/svg+xml')")
        ->toContain("where('path', 'like', '%/fallback/%')")
        ->toContain('Special:Redirect/file/')
        ->toContain('A missing image is preferable to exposing an artificial placeholder as real content.');
});
