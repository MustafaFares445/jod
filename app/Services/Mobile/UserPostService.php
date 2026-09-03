<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Post;
use App\Models\User;
use App\Services\NotificationEventService;
use App\Support\Mobile\SyrianGovernorates;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserPostService
{
    public function __construct(
        private readonly PostImageService $imageService,
        private readonly NotificationEventService $notifications,
    ) {}

    public function paginate(User $user, array $params): LengthAwarePaginator
    {
        $query = Post::query()->with('images')->where('author_id', $user->id);
        $status = $params['filter']['status'] ?? null;
        if ($status) $query->where('status', $status);

        [$column, $direction] = $this->normalizeSort($params['sort'] ?? '-createdAt');
        return $query->orderBy($column, $direction)->orderBy('id')->paginate((int) ($params['perPage'] ?? 20));
    }

    public function create(User $user, array $data): Post
    {
        return DB::transaction(function () use ($user, $data): Post {
            $isDraft = (bool) ($data['saveAsDraft'] ?? false);
            $post = Post::query()->create([
                'title' => $data['title'] ?? null,
                'summary' => $this->summaryFromDetails($data['details'] ?? null),
                'content' => $data['details'] ?? null,
                'type' => $data['type'],
                'status' => $isDraft ? 'draft' : 'pending',
                'location' => $this->locationFromData($data),
                'category_id' => $data['categoryId'] ?? null,
                'author_id' => $user->id,
                'updated_by' => $user->id,
                'submitted_at' => $isDraft ? null : now(),
            ]);

            if (! $isDraft) $this->notifyAdminsForReview($post, $user);
            return $post->load('images');
        });
    }

    public function update(Post $post, array $data): Post
    {
        $attributes = [];
        if (array_key_exists('title', $data)) $attributes['title'] = $data['title'];
        if (array_key_exists('details', $data)) {
            $attributes['summary'] = $this->summaryFromDetails($data['details']);
            $attributes['content'] = $data['details'];
        }
        if (array_key_exists('type', $data)) $attributes['type'] = $data['type'];
        if (array_key_exists('cityId', $data) || array_key_exists('city', $data)) $attributes['location'] = $this->locationFromData($data);
        if (array_key_exists('categoryId', $data)) $attributes['category_id'] = $data['categoryId'];

        if ($attributes !== []) {
            $attributes['updated_by'] = $post->author_id;
            $post->update($attributes);
        }
        return $post->refresh()->load('images');
    }

    public function submit(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $lockedPost = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedPost->status, ['draft', 'blocked'], true)) {
                throw ValidationException::withMessages(['status' => ['Only draft or blocked posts can be submitted.']]);
            }

            $this->validateForSubmission($lockedPost);
            $lockedPost->update([
                'status' => 'pending',
                'submitted_at' => now(),
                'published_at' => null,
                'updated_by' => $lockedPost->author_id,
                'block_reason' => null,
                'blocked_at' => null,
                'blocked_by' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);

            $author = User::query()->find($lockedPost->author_id);
            if ($author !== null) $this->notifyAdminsForReview($lockedPost, $author);
            return $lockedPost->refresh()->load('images');
        });
    }

    public function delete(Post $post): void
    {
        $this->imageService->purge($post);
        $post->delete();
    }

    private function notifyAdminsForReview(Post $post, User $author): void
    {
        $title = filled($post->title) ? (string) $post->title : 'منشور بدون عنوان';
        $this->notifications->notifyAdmins(
            NotificationEventType::PostSubmitted,
            'منشور جديد بانتظار المراجعة',
            "أرسل {$author->name} المنشور «{$title}» للمراجعة.",
            'post', 'normal', $title, '/admin/posts/review/'.$post->id, (string) $author->id,
        );
    }

    private function validateForSubmission(Post $post): void
    {
        $errors = [];
        if (! filled($post->title) || mb_strlen(trim((string) $post->title)) < 4) $errors['title'] = ['عنوان المنشور مطلوب ويجب ألا يقل عن 4 أحرف.'];
        if (! filled($post->content) || mb_strlen(trim((string) $post->content)) < 10) $errors['details'] = ['تفاصيل المنشور مطلوبة ويجب ألا تقل عن 10 أحرف.'];
        if (! filled($post->location) || ! in_array((string) $post->location, SyrianGovernorates::names(), true)) $errors['city'] = ['اختر محافظة سورية صحيحة.'];

        $hasActiveCategory = filled($post->category_id)
            && DB::table('categories')->where('id', $post->category_id)->where('status', 'active')->exists();
        if (! $hasActiveCategory) $errors['categoryId'] = ['تصنيف المنشور مطلوب ويجب أن يكون فعالاً.'];
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    /** @param array<string, mixed> $data */
    private function locationFromData(array $data): ?string
    {
        if (array_key_exists('cityId', $data)) {
            return SyrianGovernorates::nameForId($data['cityId']);
        }

        return $data['city'] ?? null;
    }

    private function normalizeSort(string $sort): array
    {
        return match ($sort) {
            'createdAt' => ['created_at', 'asc'], 'updatedAt' => ['updated_at', 'asc'], '-updatedAt' => ['updated_at', 'desc'],
            'title' => ['title', 'asc'], '-title' => ['title', 'desc'], default => ['created_at', 'desc'],
        };
    }

    private function summaryFromDetails(?string $details): ?string
    {
        return $details === null ? null : mb_substr($details, 0, 255);
    }
}
