<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SyrianRealGroupsSeeder extends Seeder
{
    /** @var array<string, string> */
    private array $categoryIds = [];

    /** @var array<int, string> */
    private array $memberPool = [];

    public function run(): void
    {
        if (! Schema::hasTable('groups')) {
            return;
        }

        $data = json_decode(
            (string) file_get_contents(database_path('data/syrian_real_groups.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->categoryIds = $this->loadCategoryIds();
        $this->memberPool = DB::table('users')
            ->whereNull('organization_id')
            ->where('user_type', '!=', 'admin')
            ->orderBy('id')
            ->limit(80)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        DB::transaction(function () use ($data): void {
            foreach ($data['groups'] as $index => $group) {
                $this->seedGroup($group, $index);
            }
        });

        foreach ($data['groups'] as $index => $group) {
            $this->seedGroupMedia($group, $index);
        }
    }

    private function seedGroup(array $group, int $groupIndex): void
    {
        $groupId = $this->id('group:'.$group['key']);
        $ownerId = $this->memberPool[$groupIndex % max(1, count($this->memberPool))] ?? null;

        DB::table('groups')->updateOrInsert(
            ['id' => $groupId],
            $this->columns('groups', [
                'id' => $groupId,
                'owner_id' => $ownerId,
                'organization_id' => null,
                'name' => $group['name'],
                'description' => $group['description'],
                'category' => $this->categoryLabel($group['categories'][0] ?? 'volunteering'),
                'location' => $group['location'],
                'status' => 'active',
                'purpose' => $group['purpose'],
                'rules' => json_encode([
                    'احترام جميع الأعضاء والمستفيدين وعدم الإساءة لأي شخص.',
                    'عدم نشر بيانات شخصية أو صور حساسة للمستفيدين دون موافقة.',
                    'الالتزام بتعليمات السلامة والتنظيم أثناء الأنشطة الميدانية.',
                    'يمنع جمع الأموال خارج القنوات المعتمدة داخل المبادرة.',
                    'عند التسجيل في نشاط تطوعي يرجى الالتزام بالموعد أو الاعتذار مسبقاً.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'requires_post_approval' => $groupIndex % 3 !== 0,
                'proposed_admin_ids' => json_encode([], JSON_THROW_ON_ERROR),
                'rejection_reason' => null,
                'suspension_reason' => null,
                'submitted_at' => now()->subMonths(7)->addDays($groupIndex),
                'reviewed_at' => now()->subMonths(7)->addDays($groupIndex + 1),
                'reviewed_by' => null,
                'deleted_at' => null,
                'created_at' => now()->subMonths(7)->addDays($groupIndex),
                'updated_at' => now()->subDays($groupIndex % 12),
            ]),
        );

        if (Schema::hasTable('group_categories')) {
            foreach (array_values(array_unique($group['categories'])) as $category) {
                DB::table('group_categories')->updateOrInsert(
                    ['id' => $this->id('group-category:'.$group['key'].':'.$category)],
                    $this->columns('group_categories', [
                        'id' => $this->id('group-category:'.$group['key'].':'.$category),
                        'group_id' => $groupId,
                        'category' => $this->categoryLabel($category),
                        'created_at' => now()->subMonths(7)->addDays($groupIndex),
                        'updated_at' => now(),
                    ]),
                );
            }
        }

        $this->seedMembers($groupId, $groupIndex, $ownerId, $group['documented_members'] ?? null);
        $this->seedActivities($group, $groupId, $ownerId, $groupIndex);
    }

    private function seedMembers(string $groupId, int $groupIndex, ?string $ownerId, mixed $documentedMembers): void
    {
        if (! Schema::hasTable('group_members') || $this->memberPool === []) {
            return;
        }

        $desired = $documentedMembers !== null
            ? min(18, max(6, (int) round(((int) $documentedMembers) / 8)))
            : 8 + ($groupIndex % 7);

        $members = [];
        if ($ownerId !== null) {
            $members[] = $ownerId;
        }

        for ($offset = 0; count($members) < min($desired, count($this->memberPool)); $offset++) {
            $candidate = $this->memberPool[($groupIndex * 5 + $offset) % count($this->memberPool)];
            if (! in_array($candidate, $members, true)) {
                $members[] = $candidate;
            }
        }

        foreach ($members as $memberIndex => $userId) {
            $role = $memberIndex === 0 ? 'owner' : ($memberIndex === 1 ? 'admin' : ($memberIndex === 2 ? 'moderator' : 'member'));
            $memberId = $this->id('group-member:'.$groupId.':'.$userId);
            DB::table('group_members')->updateOrInsert(
                ['id' => $memberId],
                $this->columns('group_members', [
                    'id' => $memberId,
                    'group_id' => $groupId,
                    'user_id' => $userId,
                    'role' => $role,
                    'status' => 'active',
                    'joined_at' => now()->subMonths(6)->addDays($memberIndex + $groupIndex),
                    'left_at' => null,
                    'created_at' => now()->subMonths(6)->addDays($memberIndex + $groupIndex),
                    'updated_at' => now(),
                ]),
            );
        }
    }

    private function seedActivities(array $group, string $groupId, ?string $ownerId, int $groupIndex): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        foreach ($group['activities'] as $activityIndex => $activity) {
            $postId = $this->id('group-post:'.$group['key'].':'.$activityIndex.':'.$activity['title']);
            $categoryId = $this->categoryIds[$activity['category']] ?? null;
            $publishedAt = $activity['published_at'] ?? now()->subDays(20 + $activityIndex + $groupIndex)->toDateString();

            DB::table('posts')->updateOrInsert(
                ['id' => $postId],
                $this->columns('posts', [
                    'id' => $postId,
                    'title' => $activity['title'],
                    'summary' => $activity['summary'],
                    'content' => $this->activityContent($activity['type'], $activity['summary'], $group['location']),
                    'type' => $activity['type'],
                    'audience' => $activity['audience'] ?? 'general',
                    'status' => 'published',
                    'group_review_status' => 'approved',
                    'group_rejection_reason' => null,
                    'location' => $group['location'],
                    'organization_id' => null,
                    'group_id' => $groupId,
                    'campaign_id' => null,
                    'category_id' => $categoryId,
                    'author_id' => $ownerId,
                    'views_count' => 180 + (($groupIndex * 97 + $activityIndex * 61) % 2400),
                    'reactions_count' => 12 + (($groupIndex * 19 + $activityIndex * 7) % 160),
                    'applications_count' => in_array($activity['type'], ['service_offer', 'volunteer_opportunity'], true)
                        ? 4 + (($groupIndex + $activityIndex) % 14)
                        : 0,
                    'published_at' => $publishedAt,
                    'submitted_at' => $publishedAt,
                    'deleted_at' => null,
                    'created_at' => $publishedAt,
                    'updated_at' => now()->subDays(($groupIndex + $activityIndex) % 8),
                ]),
            );
        }
    }

    private function seedGroupMedia(array $group, int $groupIndex): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $groupId = $this->id('group:'.$group['key']);
        $fallback = database_path('assets/syrian-fallback/community-group.png');
        $fallbackBytes = is_file($fallback) ? (string) file_get_contents($fallback) : '';
        if ($fallbackBytes === '') {
            return;
        }

        $avatarPath = 'demo/syria/groups/'.$group['key'].'-avatar.png';
        Storage::disk('public')->put($avatarPath, $fallbackBytes);

        $coverBytes = null;
        $coverMime = 'image/png';
        $coverPath = 'demo/syria/groups/'.$group['key'].'-cover.png';

        if (! app()->environment('testing')) {
            $officialImage = $this->discoverImageUrl($group['source'] ?? null);
            if ($officialImage !== null) {
                try {
                    $response = Http::timeout(15)->retry(1, 200)->get($officialImage);
                    $contentType = strtolower((string) $response->header('Content-Type'));
                    if ($response->successful() && str_starts_with($contentType, 'image/')) {
                        $coverBytes = $response->body();
                        $coverMime = explode(';', $contentType)[0];
                        $extension = str_contains($coverMime, 'png') ? 'png' : (str_contains($coverMime, 'webp') ? 'webp' : 'jpg');
                        $coverPath = 'demo/syria/groups/'.$group['key'].'-cover.'.$extension;
                    }
                } catch (Throwable) {
                    $coverBytes = null;
                }
            }
        }

        if ($coverBytes === null) {
            $coverBytes = $fallbackBytes;
            $coverMime = 'image/png';
        }

        Storage::disk('public')->put($coverPath, $coverBytes);

        $this->upsertMedia(
            $this->id('group-avatar:'.$group['key']),
            $groupId,
            'avatar',
            $avatarPath,
            'image/png',
            strlen($fallbackBytes),
            'صورة تعريفية للفريق التطوعي.',
        );
        $this->upsertMedia(
            $this->id('group-cover:'.$group['key']),
            $groupId,
            'cover',
            $coverPath,
            $coverMime,
            strlen($coverBytes),
            'صورة مرتبطة بنشاط الفريق ومجال عمله.',
        );
    }

    private function upsertMedia(string $id, string $groupId, string $prop, string $path, string $mime, int $size, string $description): void
    {
        DB::table('media')->updateOrInsert(
            ['id' => $id],
            $this->columns('media', [
                'id' => $id,
                'model_type' => 'group',
                'model_id' => $groupId,
                'post_id' => null,
                'prop' => $prop,
                'disk' => 'public',
                'path' => $path,
                'original_name' => basename($path),
                'description' => $description,
                'mime_type' => $mime,
                'size' => $size,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        );
    }

    private function discoverImageUrl(?string $pageUrl): ?string
    {
        if ($pageUrl === null || $pageUrl === '') {
            return null;
        }

        try {
            $response = Http::timeout(12)->retry(1, 200)->get($pageUrl);
            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();
            foreach ([
                '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
                '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
                '/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            ] as $pattern) {
                if (preg_match($pattern, $html, $matches) === 1) {
                    return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function activityContent(string $type, string $summary, string $location): string
    {
        return match ($type) {
            'service_offer' => $summary.' يمكن للراغبين بالاستفادة متابعة تفاصيل التسجيل والتنسيق مع الفريق بحسب الطاقة المتاحة.',
            'volunteer_opportunity' => $summary.' يمكن للراغبين بالمشاركة التطوعية التواصل مع الفريق والانضمام إلى الأنشطة القادمة في '.$location.'.',
            'donation_campaign' => $summary.' تساهم المشاركة المجتمعية في توسيع نطاق الدعم والوصول إلى عدد أكبر من المستفيدين.',
            'awareness' => $summary.' يهدف النشاط إلى مشاركة المعرفة وتشجيع الشباب على تطوير مهاراتهم والاستفادة من الموارد المتاحة.',
            default => $summary.' يأتي هذا النشاط ضمن جهود الفريق المستمرة لخدمة المجتمع المحلي وتعزيز المشاركة التطوعية في '.$location.'.',
        };
    }

    /** @return array<string, string> */
    private function loadCategoryIds(): array
    {
        if (! Schema::hasTable('categories')) {
            return [];
        }

        $map = [];
        foreach (DB::table('categories')->get(['id', 'name']) as $category) {
            $key = match ((string) $category->name) {
                'الصحة' => 'health',
                'التعليم' => 'education',
                'الغذاء' => 'food',
                'الطوارئ' => 'emergency',
                'الإيواء' => 'shelter',
                'التوظيف' => 'employment',
                'التطوع' => 'volunteering',
                'الحماية' => 'protection',
                'المياه والإصحاح' => 'wash',
                'الأطفال' => 'children',
                'ذوي الإعاقة' => 'disability',
                'إعادة الإعمار' => 'reconstruction',
                default => null,
            };
            if ($key !== null) {
                $map[$key] = (string) $category->id;
            }
        }

        return $map;
    }

    private function categoryLabel(string $key): string
    {
        return match ($key) {
            'health' => 'الصحة',
            'education' => 'التعليم',
            'food' => 'الغذاء',
            'emergency' => 'الطوارئ',
            'shelter' => 'الإيواء',
            'employment' => 'التوظيف',
            'volunteering' => 'التطوع',
            'protection' => 'الحماية',
            'wash' => 'المياه والإصحاح',
            'children' => 'الأطفال',
            'disability' => 'ذوي الإعاقة',
            'reconstruction' => 'إعادة الإعمار',
            default => 'التطوع',
        };
    }

    private function columns(string $table, array $attributes): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        return array_intersect_key($attributes, $columns);
    }

    private function id(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-syria-groups:'.$key), 0, 32);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
