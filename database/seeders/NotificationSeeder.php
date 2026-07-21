<?php

namespace Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        Notification::create([
            'id' => SeedIds::id('notifications.newCampaignSubmitted'),
            'creator_id' => SeedIds::id('users.johnAdmin'),
            'title' => 'تم إرسال حملة جديدة للمراجعة',
            'body' => 'أرسلت مؤسسة العون حملة جديدة وهي بانتظار مراجعة الإدارة.',
            'category' => 'campaign',
            'mailbox' => 'sent',
            'recipient_scope' => 'all',
            'recipient_label' => 'جميع مديري المنصة',
            'priority' => 'normal',
            'status' => 'sent',
            'reference_label' => 'صندوق العلاج الطبي الطارئ',
            'reference_path' => '/admin/campaigns/'.SeedIds::id('campaigns.emergencyMedicalFund'),
            'created_at' => now()->subDay(),
            'sent_at' => now()->subDay(),
            'read_at' => null,
        ]);

        Notification::create([
            'id' => SeedIds::id('notifications.postApprovalAlert'),
            'creator_id' => SeedIds::id('users.sarahAhmed'),
            'title' => 'تمت الموافقة على المنشور',
            'body' => 'تمت الموافقة على منشورك ونشره على المنصة.',
            'category' => 'post',
            'mailbox' => 'inbox',
            'recipient_scope' => 'organizations',
            'recipient_label' => 'فريق عمل المؤسسة',
            'priority' => 'high',
            'status' => 'unread',
            'reference_label' => 'مطلوب دعم عاجل لمتضرري الفيضانات',
            'reference_path' => '/posts/'.SeedIds::id('posts.emergencyFloodRelief'),
            'created_at' => now()->subHours(2),
            'sent_at' => now()->subHours(2),
            'read_at' => null,
        ]);

        Notification::create([
            'id' => SeedIds::id('notifications.reportSubmitted'),
            'creator_id' => SeedIds::id('users.johnAdmin'),
            'title' => 'تم إرسال بلاغ جديد',
            'body' => 'تم إرسال بلاغ جديد ويحتاج إلى مراجعة الإدارة.',
            'category' => 'report',
            'mailbox' => 'inbox',
            'recipient_scope' => 'users',
            'recipient_label' => 'مديرو المنصة',
            'priority' => 'high',
            'status' => 'read',
            'reference_label' => 'نشاط مشبوه في حملة',
            'reference_path' => '/admin/reports/'.SeedIds::id('reports.suspiciousCampaignActivity'),
            'created_at' => now()->subDays(2),
            'sent_at' => now()->subDays(2),
            'read_at' => now()->subDay(),
        ]);

        Notification::create([
            'id' => SeedIds::id('notifications.platformMaintenance'),
            'creator_id' => null,
            'title' => 'موعد صيانة المنصة',
            'body' => 'ستتوقف المنصة للصيانة يوم الجمعة الساعة العاشرة مساءً لمدة ساعتين.',
            'category' => 'system',
            'mailbox' => 'sent',
            'recipient_scope' => 'all',
            'recipient_label' => 'جميع المستخدمين والمؤسسات',
            'priority' => 'high',
            'status' => 'sent',
            'reference_label' => null,
            'reference_path' => null,
            'created_at' => now()->subDays(3),
            'sent_at' => now()->subDays(3),
            'read_at' => null,
        ]);

        Notification::create([
            'id' => SeedIds::id('notifications.badgeAwarded'),
            'creator_id' => null,
            'title' => 'تم منحك شارة جديدة',
            'body' => 'حصلت على شارة "كبير المتبرعين" بعد تجاوز مجموع تبرعاتك 1000 دولار.',
            'category' => 'badge',
            'mailbox' => 'sent',
            'recipient_scope' => 'users',
            'recipient_label' => 'المستخدم الحاصل على الشارة',
            'priority' => 'normal',
            'status' => 'sent',
            'reference_label' => 'كبير المتبرعين',
            'reference_path' => '/badges/'.SeedIds::id('badges.topDonor'),
            'created_at' => now()->subDays(5),
            'sent_at' => now()->subDays(5),
            'read_at' => null,
        ]);
    }
}
