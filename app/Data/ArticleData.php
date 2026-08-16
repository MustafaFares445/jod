<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class ArticleData extends Data
{
    public function __construct(
        #[Max(255)]
        public ?string $title = null,
        #[Max(255)]
        public ?string $slug = null,
        #[Max(500)]
        public ?string $excerpt = null,
        public ?string $content = null,
        #[In('awareness', 'success_stories', 'campaign_updates', 'volunteer_guides')]
        public ?string $category = null,
        #[Max(2048)]
        public ?string $coverImage = null,
        #[In('draft', 'published')]
        public ?string $status = 'draft',
        public ?Carbon $publishedAt = null,
        #[Max(255)]
        public ?string $authorName = null,
    ) {}

    public function onlyModelAttributes(): array
    {
        return array_filter([
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'category' => $this->category,
            'cover_image' => $this->coverImage,
            'status' => $this->status,
            'published_at' => $this->publishedAt?->toDateTimeString(),
            'author_name' => $this->authorName,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
