<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PrimaryUserCommunitySeeder extends Seeder
{
    public function run(): void
    {
        $userId = PrimaryUserJourneySeeder::userId();
        if ($userId === null) {
            return;
        }

        $this->seedPreferences($userId);
        $this->seedFollows($userId);
        $this->seedGroups($userId);
        $this->seedNotifications($userId);
    }

    private function seedPreferences(string $userId): void
    {
        if (Schema::hasTable('user_preferences')) {
            $this->upsert('user_preferences', ['user_id' => $userId], [
                'id' => PrimaryUserJourneySeeder::id('preference:'.$userId),
                'user_id' => $userId,
                'intent' => 'both',
                'preferred_cities' => json_encode(['حلب', 'إدلب'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'preferred_governorate' => 'حلب',
                'preferred_radius_km' => 25,
                'remote_help_enabled' => true,
                'availability_status' => 'evenings',
                'onboarding_completed_at' => now()->subMonths(7),
                'created_at' => now()->subMonths(7),
            ]);
        }

        if (Schema::hasTable('user_category_interests') && Schema::hasTable('categories')) {
            $categories = DB::table('categories')
                ->where('status', 'active')
                ->whereIn('name', ['التعليم', 'الصحة', 'الغذاء', 'التطوع'])
                ->orderBy('name')
                ->get(['id']);

            foreach ($categories->values() as $index => $category) {
                $this->upsert('user_category_interests', [
                    'user_id' => $userId,
                    'category_id' => (string) $category->id,
                ], [
                    'id' => PrimaryUserJourneySeeder::id('interest:'.$userId.':'.$category->id),
                    'user_id' => $userId,
                    'category_id' => (string) $category->id,
                    'explicit_weight' => $index < 2 ? 10.0 : 7.0,
                    'behavioral_weight' => 20.0 - ($index * 3),
                    'last_interaction_at' => now()->subDays($index + 1),
                    'created_at' => now()->subMonths(6),
                ]);
            }
        }

        if (Schema::hasTable('user_capabilities') && Schema::hasTable('capabilities')) {
            $capabilities = DB::table('capabilities')->where('status', 'active')->orderBy('sort_order')->limit(3)->pluck('id');
            foreach ($capabilities as $capabilityId) {
                $this->upsert('user_capabilities', [
                    'user_id' => $userId,
                    'capability_id' => (string) $capabilityId,
                ], [
                    'user_id' => $userId,
                    'capability_id' => (string) $capabilityId,
                    'created_at' => now()->subMonths(5),
                ]);
            }
        }
    }

    private function seedFollows(string $userId): void
    {
        if (! Schema::hasTable('publisher_follows')) {
            return;
        }

        $organizations = Schema::hasTable('organizations')
            ? DB::table('organizations')->where('status', 'active')->orderBy('id')->limit(2)->pluck('id')
            : collect();

        foreach ($organizations as $index => $organizationId) {
            $this->upsert('publisher_follows', [
                'follower_user_id' => $userId,
                'target_type' => 'organization',
                'target_id' => (string) $organizationId,
            ], [
                'id' => PrimaryUserJourneySeeder::id('follow:organization:'.$organizationId),
                'follower_user_id' => $userId,
                'target_type' => 'organization',
                'target_id' => (string) $organizationId,
                'notification_level' => $index === 0 ? 'all' : 'important',
                'created_at' => now()->subDays(20 + $index),
            ]);
        }

        $otherUserId = DB::table('users')
            ->where('id', '!=', $userId)
            ->where('user_type', '!=', 'admin')
            ->orderBy('id')
            ->value('id');
        if (is_string($otherUserId) && $otherUserId !== '') {
            $this->upsert('publisher_follows', [
                'follower_user_id' => $userId,
                'target_type' => 'user',
                'target_id' => $otherUserId,
            ], [
                'id' => PrimaryUserJourneySeeder::id('follow:user:'.$otherUserId),
                'follower_user_id' => $userId,
                'target_type' => 'user',
                'target_id' => $otherUserId,
                'notification_level' => 'all',
                'created_at' => now()->subDays(11),
            ]);
        }
    }

    private function seedGroups(string $userId): void
    {
        if (! Schema::hasTable('groups')) {
            return;
        }

        $groups = DB::table('groups')
            ->whereIn('status', ['approved', 'active'])
            ->orderBy('id')
            ->limit(3)
            ->get(['id', 'owner_id']);

        if (Schema::hasTable('group_members')) {
            foreach ($groups->take(2)->values() as $index => $group) {
                $this->upsert('group_members', [
                    'group_id' => (string) $group->id,
                    'user_id' => $userId,
                ], [
                    'id' => PrimaryUserJourneySeeder::id('group-member:'.$group->id.':'.$userId),
                    'group_id' => (string) $group->id,
                    'user_id' => $userId,
                    'role' => $index === 0 ? 'member' : 'moderator',
                    'status' => 'active',
                    'joined_at' => now()->subDays(45 - ($index * 12)),
                    'created_at' => now()->subDays(45 - ($index * 12)),
                ]);
            }
        }

        $invitationGroup = $groups->get(2);
        if ($invitationGroup !== null && Schema::hasTable('group_invitations')) {
            $inviterId = is_string($invitationGroup->owner_id ?? null)
                ? $invitationGroup->owner_id
                : DB::table('users')->where('id', '!=', $userId)->where('user_type', '!=', 'admin')->orderBy('id')->value('id');

            if (is_string($inviterId) && $inviterId !== '') {
                $this->upsert('group_invitations', [
                    'group_id' => (string) $invitationGroup->id,
                    'invited_user_id' => $userId,
                ], [
                    'id' => PrimaryUserJourneySeeder::id('group-invitation:'.$invitationGroup->id.':'.$userId),
                    'group_id' => (string) $invitationGroup->id,
                    'invited_user_id' => $userId,
                    'invited_by' => $inviterId,
                    'status' => 'pending',
                    'responded_at' => null,
                    'created_at' => now()->subDays(2),
                ]);
            }
        }

        $firstGroup = $groups->first();
        if ($firstGroup === null || ! Schema::hasTable('group_posts')) {
            return;
        }

        $groupPostId = PrimaryUserJourneySeeder::id('group-post:'.$firstGroup->id.':'.$userId);
        $this->upsert('group_posts', ['id' => $groupPostId], [
            'id' => $groupPostId,
            'group_id' => (string) $firstGroup->id,
            'author_id' => $userId,
            'body' => 'مرحباً جميعاً، أستطيع المساعدة في تنسيق المبادرات التعليمية خلال المساء وعطلة نهاية الأسبوع.',
            'status' => 'published',
            'is_pinned' => false,
            'likes_count' => 6,
            'comments_count' => 2,
            'created_at' => now()->subDays(6),
        ]);

        if (! Schema::hasTable('group_comments')) {
            return;
        }

        $otherGroupPostId = DB::table('group_posts')
            ->where('group_id', (string) $firstGroup->id)
            ->where('id', '!=', $groupPostId)
            ->orderBy('id')
            ->value('id');
        if (is_string($otherGroupPostId) && $otherGroupPostId !== '') {
            $commentId = PrimaryUserJourneySeeder::id('group-comment:'.$otherGroupPostId.':'.$userId);
            $this->upsert('group_comments', ['id' => $commentId], [
                'id' => $commentId,
                'group_id' => (string) $firstGroup->id,
                'post_id' => $otherGroupPostId,
                'author_id' => $userId,
                'body' => 'مبادرة جميلة، يمكنني المشاركة في التنسيق مساء الخميس إذا كان الموعد مناسباً.',
                'created_at' => now()->subDays(3),
            ]);
        }
    }

    private function seedNotifications(string $userId): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $events = [
            ['help_offer.created', 'وصلك عرض مساعدة جديد', 'أرسل أحد المتطوعين عرض مساعدة على طلب الحاسوب المحمول.', 'help', 'high', '/my-help-requests'],
            ['help_offer.accepted', 'تم قبول عرض المساعدة', 'تم قبول عرض المساعدة الذي قدمته، ويمكنك الآن متابعة التنسيق من داخل المنصة.', 'help', 'high', '/my-help-offers'],
            ['donation.accepted', 'تم قبول طلب التبرع', 'تمت الموافقة على طلب تبرعك ويمكن متابعة خطوات التواصل والتنسيق.', 'donation', 'high', '/my-donations'],
            ['donation.completed', 'تم تأكيد استلام تبرعك', 'تم تأكيد استلام مساهمتك بنجاح. شكراً لمشاركتك.', 'donation', 'normal', '/my-donations'],
            ['application.accepted', 'تم قبول طلب التطوع', 'تم قبول طلبك للمشاركة في إحدى فرص التطوع ويمكنك متابعة الخطوات التالية.', 'applicant', 'high', '/my-applications'],
            ['campaign.published', 'تمت الموافقة على حملتك', 'تم اعتماد حملة دعم أجور المواصلات وأصبحت متاحة لاستقبال المساهمات.', 'campaign', 'high', '/my-campaigns'],
            ['post.published', 'تم نشر طلب المساعدة', 'تمت مراجعة أحد طلبات المساعدة ونشره للمستخدمين.', 'post', 'normal', '/my-posts'],
            ['report.in_progress', 'بدأت مراجعة بلاغك', 'بدأ فريق الإدارة مراجعة البلاغ الذي أرسلته وسيظهر أي تحديث جديد في الإشعارات.', 'report', 'normal', '/notifications'],
            ['group.invitation_created', 'دعوة جديدة للانضمام إلى فريق', 'وصلتك دعوة للانضمام إلى فريق تطوعي ومشاركة نشاطاته القادمة.', 'group', 'normal', '/groups'],
        ];

        $creatorId = DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');
        foreach ($events as $index => [$eventType, $title, $body, $category, $priority, $path]) {
            $id = PrimaryUserJourneySeeder::id('notification:'.$eventType.':'.$index);
            $sentAt = now()->subDays(9 - min($index, 8));
            $this->upsert('notifications', ['id' => $id], [
                'id' => $id,
                'title' => $title,
                'body' => $body,
                'mailbox' => 'inbox',
                'status' => 'sent',
                'category' => $category,
                'event_type' => $eventType,
                'recipient_scope' => 'users',
                'recipient_label' => PrimaryUserJourneySeeder::NAME,
                'priority' => $priority,
                'reference_path' => $path,
                'creator_id' => $creatorId,
                'recipient_id' => $userId,
                'sent_at' => $sentAt,
                'read_at' => $index % 3 === 0 ? null : $sentAt->copy()->addHours(2),
                'created_at' => $sentAt,
            ]);
        }
    }

    private function upsert(string $table, array $where, array $attributes): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing($table));
        $where = array_intersect_key($where, $columns);
        $attributes = array_intersect_key($attributes, $columns);
        if ($where === []) {
            return;
        }

        if (isset($columns['updated_at'])) {
            $attributes['updated_at'] = now();
        }

        DB::table($table)->updateOrInsert($where, $attributes);
    }
}
