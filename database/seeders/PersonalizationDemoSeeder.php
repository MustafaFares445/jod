<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PersonalizationDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('user_preferences')) {
            return;
        }

        $users = DB::table('users')
            ->whereNull('organization_id')
            ->where(function ($query): void {
                $query->whereNull('user_type')->orWhere('user_type', '!=', 'admin');
            })
            ->orderBy('id')
            ->limit(12)
            ->get(['id', 'name', 'email']);

        $categories = DB::table('categories')
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'name']);

        $capabilities = DB::table('capabilities')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug']);

        $organizations = DB::table('organizations')
            ->orderBy('id')
            ->get(['id']);

        if ($users->isEmpty() || $categories->isEmpty()) {
            $this->command?->warn('Personalization demo seed skipped because users/categories are missing.');
            return;
        }

        DB::transaction(function () use ($users, $categories, $capabilities, $organizations): void {
            $this->seedPreferences($users);
            $this->seedInterests($users, $categories);
            $this->seedCapabilities($users, $capabilities);
            $this->seedPublisherFollows($users, $organizations);
            $this->seedCategoryKeywords($categories);

            $helpRequests = $this->prepareHelpRequests($capabilities);
            $this->seedFeedback($users, $organizations);
            $this->seedRecommendationHistory($users, $helpRequests);
        });
    }

    private function seedPreferences(Collection $users): void
    {
        $intents = ['giver', 'receiver', 'both', 'giver', 'both', 'receiver'];
        $cities = ['دمشق', 'حلب', 'حمص', 'اللاذقية', 'دمشق', 'حلب'];
        $availability = ['available', 'weekends', 'evenings', 'remote_only', 'available', 'busy'];

        foreach ($users->values() as $index => $user) {
            $now = now();
            DB::table('user_preferences')->updateOrInsert(
                ['user_id' => (string) $user->id],
                [
                    'id' => $this->id('preference:'.$user->id),
                    'intent' => $intents[$index % count($intents)],
                    'preferred_city' => $cities[$index % count($cities)],
                    'preferred_governorate' => $cities[$index % count($cities)],
                    'preferred_radius_km' => [10, 25, 50, null][$index % 4],
                    'remote_help_enabled' => $index % 3 !== 1,
                    'availability_status' => $availability[$index % count($availability)],
                    'onboarding_completed_at' => $now->copy()->subDays(20 - ($index % 10)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function seedInterests(Collection $users, Collection $categories): void
    {
        foreach ($users->values() as $userIndex => $user) {
            $count = min(5, $categories->count());
            for ($offset = 0; $offset < $count; $offset++) {
                $category = $categories[($userIndex + ($offset * 2)) % $categories->count()];
                $explicit = $offset < 3 ? 10.0 : 0.0;
                $behavioral = (float) max(1, 24 - ($offset * 5) + (($userIndex % 4) * 2));
                $lastInteraction = now()->subDays(($userIndex + $offset) % 14)->subHours($offset);

                DB::table('user_category_interests')->updateOrInsert(
                    [
                        'user_id' => (string) $user->id,
                        'category_id' => (string) $category->id,
                    ],
                    [
                        'id' => $this->id('interest:'.$user->id.':'.$category->id),
                        'explicit_weight' => $explicit,
                        'behavioral_weight' => $behavioral,
                        'last_interaction_at' => $lastInteraction,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function seedCapabilities(Collection $users, Collection $capabilities): void
    {
        if ($capabilities->isEmpty() || ! Schema::hasTable('user_capabilities')) {
            return;
        }

        foreach ($users->values() as $userIndex => $user) {
            $intent = DB::table('user_preferences')->where('user_id', $user->id)->value('intent');
            $count = $intent === 'receiver' ? 1 : 3;

            for ($offset = 0; $offset < min($count, $capabilities->count()); $offset++) {
                $capability = $capabilities[($userIndex + ($offset * 3)) % $capabilities->count()];
                DB::table('user_capabilities')->updateOrInsert(
                    [
                        'user_id' => (string) $user->id,
                        'capability_id' => (string) $capability->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function seedPublisherFollows(Collection $users, Collection $organizations): void
    {
        if (! Schema::hasTable('publisher_follows')) {
            return;
        }

        foreach ($users->values() as $userIndex => $user) {
            if ($organizations->isNotEmpty()) {
                for ($offset = 0; $offset < min(2, $organizations->count()); $offset++) {
                    $organization = $organizations[($userIndex + $offset) % $organizations->count()];
                    $this->upsertFollow((string) $user->id, 'organization', (string) $organization->id, $offset === 0 ? 'all' : 'important');
                }
            }

            if ($users->count() > 1) {
                $target = $users[($userIndex + 1) % $users->count()];
                if ((string) $target->id !== (string) $user->id) {
                    $this->upsertFollow((string) $user->id, 'user', (string) $target->id, 'all');
                }
            }
        }
    }

    private function upsertFollow(string $userId, string $targetType, string $targetId, string $notificationLevel): void
    {
        DB::table('publisher_follows')->updateOrInsert(
            [
                'follower_user_id' => $userId,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ],
            [
                'id' => $this->id('follow:'.$userId.':'.$targetType.':'.$targetId),
                'notification_level' => $notificationLevel,
                'created_at' => now()->subDays(15),
                'updated_at' => now(),
            ],
        );
    }

    private function seedCategoryKeywords(Collection $categories): void
    {
        if (! Schema::hasTable('category_keywords')) {
            return;
        }

        $aliases = [
            'الصحة' => ['طب', 'علاج', 'دواء', 'رعاية طبية', 'مستشفى'],
            'التعليم' => ['دراسة', 'طلاب', 'مدرس', 'جامعة', 'منحة'],
            'الغذاء' => ['طعام', 'سلال غذائية', 'وجبات', 'مواد غذائية', 'إطعام'],
            'الطوارئ' => ['إغاثة', 'حالة طارئة', 'كارثة', 'عاجل', 'إنقاذ'],
            'الإيواء' => ['سكن', 'مأوى', 'إيجار', 'منزل', 'نازحين'],
            'التوظيف' => ['عمل', 'وظيفة', 'فرصة عمل', 'باحث عن عمل', 'توظيف'],
            'العمل' => ['وظيفة', 'توظيف', 'فرصة عمل', 'مهني', 'دخل'],
            'التطوع' => ['متطوع', 'متطوعين', 'فرصة تطوع', 'مبادرة', 'خدمة مجتمع'],
            'الأطفال' => ['طفل', 'أيتام', 'رعاية أطفال', 'طفولة', 'أطفال'],
            'كبار السن' => ['مسنين', 'رعاية مسنين', 'كبار السن', 'رعاية منزلية', 'مسن'],
            'الملابس' => ['كسوة', 'ملابس', 'ثياب', 'تبرع ملابس', 'احتياجات شتوية'],
            'ذوي الإعاقة' => ['إعاقة', 'ذوي الهمم', 'احتياجات خاصة', 'تأهيل', 'دعم الإعاقة'],
        ];

        foreach ($categories as $category) {
            $keywords = $aliases[(string) $category->name] ?? [
                (string) $category->name,
                'مساعدة '.$category->name,
                'دعم '.$category->name,
                'تبرع '.$category->name,
                'خدمة '.$category->name,
            ];
            $keywords[] = (string) $category->name;

            foreach (array_values(array_unique(array_filter($keywords))) as $keyword) {
                DB::table('category_keywords')->updateOrInsert(
                    [
                        'category_id' => (string) $category->id,
                        'keyword' => $keyword,
                    ],
                    [
                        'id' => $this->id('keyword:'.$category->id.':'.$keyword),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function prepareHelpRequests(Collection $capabilities): Collection
    {
        $helpRequests = DB::table('posts')
            ->where('type', 'help_request')
            ->orderBy('id')
            ->get();

        if ($helpRequests->count() < 6) {
            $needed = 6 - $helpRequests->count();
            $extra = DB::table('posts')
                ->whereNotIn('id', $helpRequests->pluck('id'))
                ->where('status', 'published')
                ->whereNotNull('category_id')
                ->orderBy('id')
                ->limit($needed)
                ->pluck('id');

            if ($extra->isNotEmpty()) {
                DB::table('posts')->whereIn('id', $extra)->update([
                    'type' => 'help_request',
                    'help_status' => 'open',
                    'updated_at' => now(),
                ]);
            }

            $helpRequests = DB::table('posts')
                ->where('type', 'help_request')
                ->orderBy('id')
                ->get();
        }

        $statuses = ['open', 'in_progress', 'fulfilled', 'partially_fulfilled', 'not_fulfilled', 'expired'];
        $urgencies = ['normal', 'important', 'urgent', 'critical', 'important', 'urgent'];
        $adminId = DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');

        foreach ($helpRequests->values() as $index => $post) {
            $status = $statuses[$index % count($statuses)];
            $urgency = $urgencies[$index % count($urgencies)];
            $fulfilledAt = in_array($status, ['fulfilled', 'partially_fulfilled'], true)
                ? now()->subDays(3 + ($index % 8))
                : null;
            $expiresAt = $status === 'expired'
                ? now()->subDays(2)
                : now()->addDays(4 + ($index % 12));

            DB::table('posts')->where('id', $post->id)->update([
                'help_status' => $status,
                'urgency' => $urgency,
                'urgency_reason' => $urgency === 'critical' ? 'حالة تجريبية حرجة لاختبار المراجعة والترتيب.' : null,
                'urgency_reviewed_by' => $urgency === 'critical' ? $adminId : null,
                'urgency_reviewed_at' => $urgency === 'critical' && $adminId !== null ? now()->subDays(1) : null,
                'expires_at' => $expiresAt,
                'fulfilled_at' => $fulfilledAt,
                'updated_at' => now(),
            ]);

            if ($capabilities->isNotEmpty() && Schema::hasTable('post_capabilities')) {
                $requiredCount = min(2, $capabilities->count());
                for ($offset = 0; $offset < $requiredCount; $offset++) {
                    $capability = $capabilities[($index + $offset) % $capabilities->count()];
                    DB::table('post_capabilities')->updateOrInsert(
                        [
                            'post_id' => (string) $post->id,
                            'capability_id' => (string) $capability->id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        }

        return DB::table('posts')->where('type', 'help_request')->orderBy('id')->get();
    }

    private function seedFeedback(Collection $users, Collection $organizations): void
    {
        $posts = DB::table('posts')->where('status', 'published')->orderBy('id')->limit(12)->get(['id']);

        if (Schema::hasTable('post_feedback') && $posts->isNotEmpty()) {
            foreach ($users->take(min(8, $users->count()))->values() as $index => $user) {
                $post = $posts[$index % $posts->count()];
                DB::table('post_feedback')->updateOrInsert(
                    [
                        'user_id' => (string) $user->id,
                        'post_id' => (string) $post->id,
                        'type' => 'not_interested',
                    ],
                    [
                        'id' => $this->id('feedback:'.$user->id.':'.$post->id.':not_interested'),
                        'created_at' => now()->subDays(2 + $index),
                        'updated_at' => now(),
                    ],
                );
            }

            foreach ($users->take(min(4, $users->count()))->values() as $index => $user) {
                $post = $posts[($index + 5) % $posts->count()];
                DB::table('post_feedback')->updateOrInsert(
                    [
                        'user_id' => (string) $user->id,
                        'post_id' => (string) $post->id,
                        'type' => 'hide',
                    ],
                    [
                        'id' => $this->id('feedback:'.$user->id.':'.$post->id.':hide'),
                        'created_at' => now()->subDays(1 + $index),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        if (Schema::hasTable('hidden_publishers') && $organizations->isNotEmpty()) {
            foreach ($users->take(min(4, $users->count()))->values() as $index => $user) {
                $organization = $organizations[$index % $organizations->count()];
                DB::table('hidden_publishers')->updateOrInsert(
                    [
                        'user_id' => (string) $user->id,
                        'publisher_type' => 'organization',
                        'publisher_id' => (string) $organization->id,
                    ],
                    [
                        'id' => $this->id('hidden-publisher:'.$user->id.':'.$organization->id),
                        'created_at' => now()->subDays($index + 1),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function seedRecommendationHistory(Collection $users, Collection $helpRequests): void
    {
        if (! Schema::hasTable('recommendation_impressions') || ! Schema::hasTable('user_interactions')) {
            return;
        }

        $posts = DB::table('posts')
            ->where('status', 'published')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->get(['id', 'category_id', 'organization_id', 'author_id', 'location', 'type']);
        $campaigns = DB::table('campaigns')
            ->where('status', 'active')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->get(['id', 'category_id', 'organization_id', 'location']);

        if ($posts->isEmpty() && $campaigns->isEmpty()) {
            return;
        }

        $feedTypes = ['for_you', 'following', 'nearby', 'urgent'];
        $interactionTypes = ['post_open', 'post_save', 'post_view', 'help_offer', 'volunteer_application', 'not_interested', 'hide_post'];

        foreach ($users->values() as $userIndex => $user) {
            for ($slot = 0; $slot < 20; $slot++) {
                $useCampaign = $campaigns->isNotEmpty() && $slot % 5 === 4;
                if ($useCampaign) {
                    $subject = $campaigns[($userIndex + $slot) % $campaigns->count()];
                    $subjectType = 'campaign';
                    $publisherType = 'organization';
                    $publisherId = $subject->organization_id;
                } else {
                    if ($posts->isEmpty()) {
                        continue;
                    }
                    $subject = $posts[($userIndex * 3 + $slot) % $posts->count()];
                    $subjectType = 'post';
                    $publisherType = $subject->organization_id ? 'organization' : 'user';
                    $publisherId = $subject->organization_id ?: $subject->author_id;
                }

                $daysAgo = 1 + (($userIndex * 3 + $slot) % 28);
                $shownAt = now()->subDays($daysAgo)->setTime(8 + ($slot % 10), ($slot * 7) % 60);
                $feedType = $feedTypes[($userIndex + $slot) % count($feedTypes)];
                $reasons = match ($slot % 4) {
                    0 => ['explicit_interest', 'same_city'],
                    1 => ['followed_publisher', 'fresh'],
                    2 => ['behavioral_interest', 'same_governorate'],
                    default => ['discovery', 'intent_match'],
                };

                $impressionId = $this->id('impression:'.$user->id.':'.$subjectType.':'.$subject->id.':'.$slot);
                DB::table('recommendation_impressions')->updateOrInsert(
                    ['id' => $impressionId],
                    [
                        'user_id' => (string) $user->id,
                        'subject_type' => $subjectType,
                        'subject_id' => (string) $subject->id,
                        'feed_type' => $feedType,
                        'category_id' => $subject->category_id,
                        'publisher_type' => $publisherType,
                        'publisher_id' => $publisherId,
                        'city' => $subject->location,
                        'score' => 45 + (($userIndex * 7 + $slot * 3) % 95),
                        'reasons' => json_encode($reasons, JSON_UNESCAPED_UNICODE),
                        'shown_at' => $shownAt,
                        'created_at' => $shownAt,
                        'updated_at' => $shownAt,
                    ],
                );

                if ($slot % 3 === 2) {
                    continue;
                }

                $eventType = $useCampaign
                    ? ($slot % 2 === 0 ? 'campaign_open' : 'campaign_donation')
                    : $interactionTypes[($userIndex + $slot) % count($interactionTypes)];

                if ($eventType === 'help_offer' && ! $helpRequests->contains('id', $subject->id)) {
                    $eventType = 'post_open';
                }

                $occurredAt = $shownAt->copy()->addMinutes(5 + (($slot * 3) % 45));
                DB::table('user_interactions')->updateOrInsert(
                    ['id' => $this->id('interaction:'.$user->id.':'.$subjectType.':'.$subject->id.':'.$slot)],
                    [
                        'user_id' => (string) $user->id,
                        'event_type' => $eventType,
                        'subject_type' => $subjectType,
                        'subject_id' => (string) $subject->id,
                        'category_id' => $subject->category_id,
                        'publisher_type' => $publisherType,
                        'publisher_id' => $publisherId,
                        'metadata' => json_encode([
                            'seeded' => true,
                            'source' => 'personalization_demo',
                            'feedType' => $feedType,
                        ], JSON_UNESCAPED_UNICODE),
                        'occurred_at' => $occurredAt,
                        'created_at' => $occurredAt,
                        'updated_at' => $occurredAt,
                    ],
                );
            }
        }
    }

    private function id(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-personalization-demo:'.$key), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
