<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('category stores recommendation keywords', function () {
    $category = Category::factory()->create(['keywords' => ['تعليم', 'جامعة', 'منحة']]);
    expect($category->fresh()->keywords)->toBe(['تعليم', 'جامعة', 'منحة']);
});
