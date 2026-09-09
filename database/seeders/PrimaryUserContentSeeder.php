<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PrimaryUserContentSeeder extends Seeder
{
    public function run(): void
    {
        $userId = PrimaryUserJourneySeeder::userId();
        if ($userId === null || ! Schema::hasTable('posts')) {
            return;
        }

        $education = $this->categoryId('التعليم');
        $health = $this->categoryId('الصحة') ?? $education;
        $shelter = $this->categoryId('الإيواء') ?? $education;
        if ($education === null) {
            return;
        }

        $requests = [
            ['laptop', 'أحتاج حاسوباً محمولاً لمتابعة الدراسة الجامعية', 'أبحث عن حاسوب مستعمل بحالة جيدة يساعدني على متابعة المحاضرات وإنجاز المشاريع الجامعية.', $education, 'student', 'open', 'important', 2, 21],
            ['transport', 'مساعدة في تكاليف المواصلات إلى الجامعة', 'أحتاج مساعدة جزئية في أجور المواصلات خلال الأسابيع المتبقية من الفصل الدراسي.', $education, 'student', 'in_progress', 'normal', 7, 14],
            ['books', 'كتب ومراجع للفصل الدراسي الحالي', 'تم تأمين كامل الكتب المطلوبة من خلال أحد المتطوعين.', $education, 'student', 'fulfilled', 'normal', 24, 10],
            ['room', 'مساعدة في إصلاح نافذة وغرفة متضررة في المنزل', 'تم تأمين جزء من المواد اللازمة للإصلاح وما زلت أحتاج إلى بعض المستلزمات.', $shelter, 'general', 'partially_fulfilled', 'important', 18, 8],
            ['pressure', 'أبحث عن جهاز قياس ضغط منزلي', 'لم يتوفر الجهاز خلال المدة المحددة للطلب.', $health, 'general', 'not_fulfilled', 'important', 35, -3],
            ['supplies', 'حقيبة وقرطاسية لطالب في المرحلة الثانوية', 'انتهت مدة الطلب قبل اكتمال تأمين الحقيبة والقرطاسية.', $education, 'student', 'expired', 'normal', 40, -8],
        ];

        foreach ($requests as [$key, $title, $summary, $categoryId, $audience, $helpStatus, $urgency, $daysAgo, $expiresIn]) {
            $id = PrimaryUserJourneySeeder::id('help-request:'.$key);
            $publishedAt = now()->subDays($daysAgo);
            $this->upsert('posts', ['id' => $id], [
                'id' => $id,
                'title' => $title,
                'summary' => $summary,
                'content' => $summary.' أفضل التنسيق والتواصل من خلال المنصة لتحديد التفاصيل وطريقة الاستلام المناسبة.',
                'type' => 'help_request',
                'audience' => $audience,
                'status' => 'published',
                'help_status' => $helpStatus,
                'urgency' => $urgency,
                'urgency_reason' => $urgency === 'important' ? 'الاحتياج مرتبط بموعد قريب أو بمتابعة الدراسة والحياة اليومية.' : null,
                'location' => 'حلب',
                'organization_id' => null,
                'group_id' => null,
                'campaign_id' => null,
                'category_id' => $categoryId,
                'author_id' => $userId,
                'updated_by' => $userId,
                'views_count' => 80 + $daysAgo,
                'reactions_count' => 5,
                'applications_count' => 0,
                'published_at' => $publishedAt,
                'submitted_at' => $publishedAt->copy()->subHours(6),
                'expires_at' => now()->addDays($expiresIn),
                'fulfilled_at' => in_array($helpStatus, ['fulfilled', 'partially_fulfilled'], true) ? now()->subDays(3) : null,
                'created_at' => $publishedAt->copy()->subDay(),
            ]);
        }

        foreach ([
            ['draft', 'طلب دعم لشراء مراجع جامعية', null],
            ['pending', 'طلب مساعدة في صيانة حاسوب للدراسة', null],
            ['blocked', 'طلب مساعدة لشراء جهاز دراسي', 'يحتاج الطلب إلى توضيح الاحتياج وآلية الاستلام قبل إعادة إرساله للنشر.'],
        ] as [$status, $title, $reason]) {
            $id = PrimaryUserJourneySeeder::id('help-request:'.$status);
            $this->upsert('posts', ['id' => $id], [
                'id' => $id,
                'title' => $title,
                'summary' => 'أعمل على استكمال تفاصيل الطلب وتحديد الاحتياج بصورة أوضح قبل نشره بشكل نهائي.',
                'content' => 'أعمل على استكمال تفاصيل الطلب وتحديد الاحتياج بصورة أوضح قبل نشره بشكل نهائي، مع إبقاء التواصل داخل المنصة.',
                'type' => 'help_request',
                'audience' => 'student',
                'status' => $status,
                'help_status' => 'open',
                'urgency' => 'normal',
                'location' => 'حلب',
                'category_id' => $education,
                'author_id' => $userId,
                'updated_by' => $userId,
                'block_reason' => $reason,
                'blocked_at' => $status === 'blocked' ? now()->subDays(3) : null,
                'submitted_at' => $status === 'draft' ? null : now()->subDays(5),
                'published_at' => null,
                'created_at' => now()->subDays(8),
            ]);
        }

        $serviceId = PrimaryUserJourneySeeder::id('service-offer:laptop-maintenance');
        $this->upsert('posts', ['id' => $serviceId], [
            'id' => $serviceId,
            'title' => 'صيانة برمجية مجانية لحواسيب الطلاب',
            'summary' => 'أستطيع مساعدة الطلاب في تنظيف النظام وتثبيت البرامج الدراسية وحل المشكلات البرمجية البسيطة.',
            'content' => 'أقدم المساعدة في الصيانة البرمجية البسيطة للحواسيب المستخدمة للدراسة، ويتم تنسيق الموعد والتفاصيل عبر المنصة.',
            'type' => 'service_offer',
            'audience' => 'student',
            'status' => 'published',
            'location' => 'حلب',
            'category_id' => $education,
            'author_id' => $userId,
            'updated_by' => $userId,
            'views_count' => 184,
            'reactions_count' => 27,
            'applications_count' => 9,
            'published_at' => now()->subDays(12),
            'submitted_at' => now()->subDays(13),
            'created_at' => now()->subDays(14),
        ]);

        $this->seedPersonalCampaigns($userId, $education);
    }

    private function seedPersonalCampaigns(string $userId, string $categoryId): void
    {
        if (! Schema::hasTable('campaigns')) {
            return;
        }

        $adminId = DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');
        $rows = [
            ['pending', 'تأمين حقائب مدرسية لطلاب من الأسر محدودة الدخل', 7500000, null],
            ['active', 'دعم أجور المواصلات لطلاب جامعيين خلال الفصل الحالي', 12000000, null],
            ['closed', 'تجهيز مكتبة صفية صغيرة بكتب ومراجع أساسية', 5000000, 'اكتملت المساهمة المطلوبة وتم إغلاق الحملة بعد تأمين الاحتياج.'],
            ['rejected', 'دعم شراء أجهزة لوحية لطلاب جامعيين', 18000000, 'بحاجة إلى تفاصيل أوضح حول عدد المستفيدين وآلية التوزيع والميزانية.'],
        ];

        foreach ($rows as $index => [$status, $title, $goal, $reason]) {
            $campaignId = PrimaryUserJourneySeeder::id('personal-campaign:'.$status);
            $this->upsert('campaigns', ['id' => $campaignId], [
                'id' => $campaignId,
                'title' => $title,
                'summary' => 'مبادرة شخصية صغيرة لدعم احتياج تعليمي محدد بشكل مباشر ومنظم.',
                'content' => 'تهدف المبادرة إلى تغطية احتياج تعليمي محدد مع توضيح الهدف والمتابعة من خلال المنصة حتى اكتمال الحملة.',
                'category_id' => $categoryId,
                'audience' => 'student',
                'status' => $status,
                'location' => 'حلب',
                'organization_id' => null,
                'group_id' => null,
                'creator_id' => $userId,
                'goal_amount' => $goal,
                'raised_amount' => 0,
                'beneficiaries_count' => 15 + $index * 5,
                'donors_count' => 0,
                'applicants_count' => 0,
                'start_date' => now()->subDays(20 + $index * 5)->toDateString(),
                'end_date' => now()->addDays(25)->toDateString(),
                'submitted_at' => now()->subDays(18 + $index * 4),
                'reviewed_by' => in_array($status, ['active', 'closed', 'rejected'], true) ? $adminId : null,
                'reviewed_at' => in_array($status, ['active', 'closed', 'rejected'], true) ? now()->subDays(10 + $index) : null,
                'closed_at' => $status === 'closed' ? now()->subDays(4) : null,
                'closed_reason' => $status === 'closed' ? $reason : null,
                'rejection_reason' => $status === 'rejected' ? $reason : null,
                'created_at' => now()->subDays(22 + $index * 5),
            ]);

            $postId = PrimaryUserJourneySeeder::id('personal-campaign-post:'.$status);
            $postStatus = $status === 'active' ? 'published' : ($status === 'pending' ? 'pending' : 'blocked');
            $this->upsert('posts', ['id' => $postId], [
                'id' => $postId,
                'title' => $title,
                'summary' => 'مبادرة شخصية لدعم احتياج تعليمي محدد ومتابعة المساهمات من خلال المنصة.',
                'content' => 'مبادرة شخصية لدعم احتياج تعليمي محدد ومتابعة المساهمات من خلال المنصة حتى اكتمال الهدف.',
                'type' => 'donation_campaign',
                'audience' => 'student',
                'status' => $postStatus,
                'location' => 'حلب',
                'campaign_id' => $campaignId,
                'category_id' => $categoryId,
                'author_id' => $userId,
                'updated_by' => $userId,
                'block_reason' => in_array($status, ['closed', 'rejected'], true) ? $reason : null,
                'blocked_at' => in_array($status, ['closed', 'rejected'], true) ? now()->subDays(4) : null,
                'blocked_by' => in_array($status, ['closed', 'rejected'], true) ? $adminId : null,
                'published_at' => $status === 'active' ? now()->subDays(12) : null,
                'submitted_at' => now()->subDays(18),
                'created_at' => now()->subDays(20),
            ]);
        }
    }

    private function categoryId(string $name): ?string
    {
        if (! Schema::hasTable('categories')) {
            return null;
        }

        $id = DB::table('categories')->where('name', $name)->value('id');
        if (! is_string($id)) {
            $id = DB::table('categories')->where('status', 'active')->orderBy('id')->value('id');
        }

        return is_string($id) ? $id : null;
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
