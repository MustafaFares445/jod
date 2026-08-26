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
        #[Max(500)]
        public ?string $excerpt = null,
        public ?string $content = null,
        #[In('draft', 'published')]
        public ?string $status = 'draft',
        public ?Carbon $publishedAt = null,
        #[Max(255)]
        public ?string $authorName = null,
        public ?string $authorId = null,
    ) {}

    public function onlyModelAttributes(): array
    {
        return array_filter([
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'status' => $this->status,
            'published_at' => $this->publishedAt?->toDateTimeString(),
            'author_name' => $this->authorName,
            'author_id' => $this->authorId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
