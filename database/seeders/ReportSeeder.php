<?php

namespace Database\Seeders;

use App\Models\Report;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        Report::create([
            'id' => SeedIds::id('reports.suspiciousCampaignActivity'),
            'reporter_id' => SeedIds::id('users.ahmedMohammed'),
            'assignee_id' => null,
            'title' => 'نشاط مشبوه في حملة',
            'description' => 'المعلومات المعلنة في الحملة لا تتطابق مع الأنشطة المنفذة على أرض الواقع.',
            'category' => 'fraud',
            'severity' => 'high',
            'entity_type' => 'campaign',
            'entity_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'status' => 'new',
            'evidence' => json_encode([
                ['type' => 'url', 'content' => 'https://example.com/campaign-update'],
                ['type' => 'text', 'content' => 'تعرض الحملة تبرعات دون نشر تحديثات واضحة عن الأنشطة.'],
            ]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now(), 'note' => 'تم إرسال البلاغ.'],
            ]),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Report::create([
            'id' => SeedIds::id('reports.inappropriatePostContent'),
            'reporter_id' => SeedIds::id('users.sarahAhmed'),
            'assignee_id' => SeedIds::id('users.johnAdmin'),
            'title' => 'محتوى غير مناسب في منشور',
            'description' => 'يحتوي المنشور على عبارات وصور غير مناسبة للنشر على المنصة.',
            'category' => 'inappropriate',
            'severity' => 'high',
            'entity_type' => 'post',
            'entity_id' => SeedIds::id('posts.volunteerOpportunityTeacherNeeded'),
            'status' => 'in_progress',
            'evidence' => json_encode([
                ['type' => 'screenshot', 'content' => 'screenshot_001.jpg'],
            ]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now()->subDays(2), 'note' => 'تم إرسال البلاغ.'],
                ['status' => 'in_progress', 'timestamp' => now()->subDay(), 'note' => 'تم إسناد البلاغ إلى مدير للمراجعة.'],
            ]),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDay(),
        ]);

        Report::create([
            'id' => SeedIds::id('reports.userImpersonationAttempt'),
            'reporter_id' => SeedIds::id('users.mohammedAli'),
            'assignee_id' => SeedIds::id('users.johnAdmin'),
            'title' => 'محاولة انتحال شخصية مستخدم',
            'description' => 'يبدو أن حساب المستخدم ينتحل هوية شخص آخر.',
            'category' => 'fraud',
            'severity' => 'critical',
            'entity_type' => 'user',
            'entity_id' => SeedIds::id('users.fatimaHassan'),
            'status' => 'in_progress',
            'evidence' => json_encode([]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now()->subDays(3), 'note' => 'تم إرسال البلاغ.'],
                ['status' => 'in_progress', 'timestamp' => now()->subDays(2), 'note' => 'البلاغ قيد التحقيق.'],
                ['action' => 'request_info', 'status' => 'in_progress', 'timestamp' => now()->subDay(), 'note' => 'تم طلب معلومات إضافية.'],
            ]),
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDay(),
        ]);

        Report::create([
            'id' => SeedIds::id('reports.spamPostReported'),
            'reporter_id' => SeedIds::id('users.sarahAhmed'),
            'assignee_id' => SeedIds::id('users.johnAdmin'),
            'title' => 'بلاغ عن منشورات مزعجة',
            'description' => 'نشر المستخدم عدة منشورات مزعجة ومتكررة.',
            'category' => 'spam',
            'severity' => 'medium',
            'entity_type' => 'post',
            'entity_id' => SeedIds::id('posts.draftPostNotPublished'),
            'status' => 'closed',
            'evidence' => json_encode([]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now()->subDays(7), 'note' => 'تم إرسال البلاغ.'],
                ['status' => 'in_progress', 'timestamp' => now()->subDays(6), 'note' => 'البلاغ قيد المراجعة.'],
                ['status' => 'closed', 'timestamp' => now()->subDays(5), 'note' => 'تم إيقاف حساب المستخدم.'],
            ]),
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(5),
        ]);

        Report::create([
            'id' => SeedIds::id('reports.typoInCampaignDescription'),
            'reporter_id' => SeedIds::id('users.ahmedMohammed'),
            'assignee_id' => null,
            'title' => 'خطأ إملائي في وصف حملة',
            'description' => 'يوجد خطأ إملائي في وصف الحملة المنشور.',
            'category' => 'other',
            'severity' => 'low',
            'entity_type' => 'campaign',
            'entity_id' => SeedIds::id('campaigns.foodSecurityProgram'),
            'status' => 'new',
            'evidence' => json_encode([]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now()->subDay(), 'note' => 'تم إرسال البلاغ.'],
            ]),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }
}
