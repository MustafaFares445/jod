<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PrimaryUserContributionSeeder extends Seeder
{
    public function run(): void
    {
        $userId = PrimaryUserJourneySeeder::userId();
        if ($userId === null) {
            return;
        }

        $this->seedHelpOffers($userId);
        $this->seedDonations($userId);
        $this->seedApplications($userId);
        $this->seedReports($userId);
        $this->seedEngagement($userId);
        $this->recalculateCampaigns();
    }

    private function seedHelpOffers(string $userId): void
    {
        if (! Schema::hasTable('help_offers') || ! Schema::hasTable('posts')) {
            return;
        }

        $targets = DB::table('posts')
            ->where('type', 'help_request')
            ->where('status', 'published')
            ->whereNotNull('author_id')
            ->where('author_id', '!=', $userId)
            ->orderBy('id')
            ->limit(7)
            ->get(['id', 'author_id']);

        $statuses = ['pending', 'accepted', 'contacting', 'agreed', 'completed', 'rejected', 'cancelled'];
        foreach ($targets->values() as $index => $target) {
            $status = $statuses[$index];
            $id = PrimaryUserJourneySeeder::id('help-offer:made:'.$status.':'.$target->id);
            $this->upsert('help_offers', ['id' => $id], [
                'id' => $id,
                'post_id' => (string) $target->id,
                'helper_user_id' => $userId,
                'post_owner_id' => (string) $target->author_id,
                'type' => $index === 3 ? 'financial' : 'service',
                'amount' => $index === 3 ? 350000 : null,
                'description' => 'أستطيع المساعدة في هذا الاحتياج ويمكننا تنسيق التفاصيل ووقت التسليم من خلال المنصة.',
                'status' => $status,
                'contact_method' => 'phone',
                'contact_value' => PrimaryUserJourneySeeder::PHONE,
                'phone' => PrimaryUserJourneySeeder::PHONE,
                'accepted_at' => in_array($status, ['accepted', 'contacting', 'agreed', 'completed'], true) ? now()->subDays(6) : null,
                'contacted_at' => in_array($status, ['contacting', 'agreed', 'completed'], true) ? now()->subDays(5) : null,
                'agreed_at' => in_array($status, ['agreed', 'completed'], true) ? now()->subDays(4) : null,
                'helper_agreed_at' => in_array($status, ['agreed', 'completed'], true) ? now()->subDays(4) : null,
                'receiver_agreed_at' => in_array($status, ['agreed', 'completed'], true) ? now()->subDays(4) : null,
                'helper_confirmed_at' => $status === 'completed' ? now()->subDays(3) : null,
                'receiver_confirmed_at' => $status === 'completed' ? now()->subDays(3) : null,
                'completed_at' => $status === 'completed' ? now()->subDays(3) : null,
                'rejected_at' => $status === 'rejected' ? now()->subDays(3) : null,
                'rejection_reason' => $status === 'rejected' ? 'تم تأمين الاحتياج من عرض آخر قبل متابعة هذا العرض.' : null,
                'cancelled_at' => $status === 'cancelled' ? now()->subDays(3) : null,
                'cancel_reason' => $status === 'cancelled' ? 'تغير وقت التوفر ولم يعد بالإمكان الالتزام بالموعد المناسب.' : null,
                'created_at' => now()->subDays(10 - min($index, 6)),
            ]);
        }

        $ownedPosts = DB::table('posts')
            ->where('author_id', $userId)
            ->where('type', 'help_request')
            ->where('status', 'published')
            ->orderBy('id')
            ->limit(3)
            ->get(['id']);
        $helpers = DB::table('users')
            ->where('id', '!=', $userId)
            ->where('user_type', '!=', 'admin')
            ->orderBy('id')
            ->limit(3)
            ->get(['id', 'phone']);

        foreach ($ownedPosts->values() as $index => $post) {
            $helper = $helpers->get($index);
            if ($helper === null) {
                break;
            }
            $status = ['pending', 'accepted', 'completed'][$index];
            $offerId = PrimaryUserJourneySeeder::id('help-offer:received:'.$status.':'.$post->id);
            $this->upsert('help_offers', ['id' => $offerId], [
                'id' => $offerId,
                'post_id' => (string) $post->id,
                'helper_user_id' => (string) $helper->id,
                'post_owner_id' => $userId,
                'type' => $index === 2 ? 'supplies' : 'service',
                'description' => 'أستطيع المساعدة ويمكننا تنسيق الموعد والتفاصيل المناسبة من خلال المنصة.',
                'status' => $status,
                'contact_method' => 'phone',
                'phone' => $helper->phone ?? null,
                'accepted_at' => in_array($status, ['accepted', 'completed'], true) ? now()->subDays(5) : null,
                'contacted_at' => $status === 'completed' ? now()->subDays(4) : null,
                'agreed_at' => $status === 'completed' ? now()->subDays(3) : null,
                'helper_agreed_at' => $status === 'completed' ? now()->subDays(3) : null,
                'receiver_agreed_at' => $status === 'completed' ? now()->subDays(3) : null,
                'helper_confirmed_at' => $status === 'completed' ? now()->subDays(2) : null,
                'receiver_confirmed_at' => $status === 'completed' ? now()->subDays(2) : null,
                'completed_at' => $status === 'completed' ? now()->subDays(2) : null,
                'created_at' => now()->subDays(8 - $index),
            ]);

            if ($status === 'completed') {
                $this->upsert('posts', ['id' => (string) $post->id], [
                    'selected_help_offer_id' => $offerId,
                    'help_status' => 'fulfilled',
                    'fulfilled_at' => now()->subDays(2),
                ]);
            }
        }
    }

    private function seedDonations(string $userId): void
    {
        if (! Schema::hasTable('donations') || ! Schema::hasTable('campaigns')) {
            return;
        }

        $campaigns = DB::table('campaigns')
            ->where('status', 'active')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->limit(6)
            ->get(['id', 'organization_id', 'title']);
        $statuses = ['pending', 'accepted', 'contacting', 'agreed', 'completed', 'cancelled'];
        $amounts = [250000, 400000, 175000, 600000, 900000, 300000];

        foreach ($campaigns->values() as $index => $campaign) {
            $status = $statuses[$index];
            $reference = 'DON-'.substr(str_replace('-', '', PrimaryUserJourneySeeder::id('donation:'.$campaign->id)), 0, 12);
            $donatedAt = now()->subDays(26 - $index * 3);
            $this->upsert('donations', ['campaign_ref' => $reference], [
                'organization_id' => (string) $campaign->organization_id,
                'campaign_id' => (string) $campaign->id,
                'name' => PrimaryUserJourneySeeder::NAME,
                'email' => PrimaryUserJourneySeeder::EMAIL,
                'phone' => PrimaryUserJourneySeeder::PHONE,
                'campaign_title' => (string) $campaign->title,
                'amount_or_type' => number_format($amounts[$index], 2, '.', ''),
                'confirmed_amount' => $status === 'completed' ? number_format($amounts[$index], 2, '.', '') : null,
                'donated_at' => $donatedAt,
                'city' => 'حلب',
                'source' => 'mobile_app',
                'payment_method' => $index % 2 === 0 ? 'cash' : 'bank_transfer',
                'status' => $status,
                'contact_method' => 'phone',
                'notes' => 'أفضل تنسيق التفاصيل وموعد التسليم من خلال المنصة.',
                'cancel_reason' => $status === 'cancelled' ? 'تغيرت الظروف المالية قبل إتمام التبرع.' : null,
                'accepted_at' => in_array($status, ['accepted', 'contacting', 'agreed', 'completed'], true) ? $donatedAt->copy()->addDay() : null,
                'contacted_at' => in_array($status, ['contacting', 'agreed', 'completed'], true) ? $donatedAt->copy()->addDays(2) : null,
                'agreed_at' => in_array($status, ['agreed', 'completed'], true) ? $donatedAt->copy()->addDays(3) : null,
                'completed_at' => $status === 'completed' ? $donatedAt->copy()->addDays(4) : null,
                'cancelled_at' => $status === 'cancelled' ? $donatedAt->copy()->addDay() : null,
                'campaign_ref' => $reference,
                'created_by' => $userId,
                'is_anonymous' => $index === 1,
                'created_at' => $donatedAt,
            ]);
        }

        $personalCampaignId = PrimaryUserJourneySeeder::id('personal-campaign:active');
        if (! DB::table('campaigns')->where('id', $personalCampaignId)->exists()) {
            return;
        }

        $donors = DB::table('users')
            ->where('id', '!=', $userId)
            ->where('user_type', '!=', 'admin')
            ->orderBy('id')
            ->limit(3)
            ->get(['id', 'name', 'email', 'phone']);
        foreach ($donors->values() as $index => $donor) {
            $status = ['pending', 'agreed', 'completed'][$index];
            $reference = 'REC-'.substr(str_replace('-', '', PrimaryUserJourneySeeder::id('received-donation:'.$donor->id)), 0, 12);
            $amount = [200000, 350000, 500000][$index];
            $donatedAt = now()->subDays(9 - $index * 2);
            $this->upsert('donations', ['campaign_ref' => $reference], [
                'campaign_id' => $personalCampaignId,
                'name' => (string) $donor->name,
                'email' => (string) $donor->email,
                'phone' => $donor->phone ?? null,
                'campaign_title' => 'دعم أجور المواصلات لطلاب جامعيين خلال الفصل الحالي',
                'amount_or_type' => number_format($amount, 2, '.', ''),
                'confirmed_amount' => $status === 'completed' ? number_format($amount, 2, '.', '') : null,
                'donated_at' => $donatedAt,
                'city' => 'حلب',
                'source' => 'mobile_app',
                'payment_method' => 'cash',
                'status' => $status,
                'contact_method' => 'phone',
                'accepted_at' => in_array($status, ['agreed', 'completed'], true) ? $donatedAt->copy()->addDay() : null,
                'contacted_at' => in_array($status, ['agreed', 'completed'], true) ? $donatedAt->copy()->addDays(2) : null,
                'agreed_at' => in_array($status, ['agreed', 'completed'], true) ? $donatedAt->copy()->addDays(3) : null,
                'completed_at' => $status === 'completed' ? $donatedAt->copy()->addDays(4) : null,
                'campaign_ref' => $reference,
                'created_by' => (string) $donor->id,
                'is_anonymous' => false,
                'created_at' => $donatedAt,
            ]);
        }
    }

    private function seedApplications(string $userId): void
    {
        if (! Schema::hasTable('campaign_applications') || ! Schema::hasTable('campaigns') || ! Schema::hasTable('posts')) {
            return;
        }

        $campaigns = DB::table('campaigns')
            ->join('posts', 'posts.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.status', 'active')
            ->where('posts.status', 'published')
            ->where('posts.type', 'volunteer_opportunity')
            ->whereNotNull('campaigns.organization_id')
            ->orderBy('campaigns.id')
            ->limit(6)
            ->get(['campaigns.id', 'campaigns.organization_id', 'campaigns.title']);
        $statuses = ['pending', 'accepted', 'contacting', 'completed', 'rejected', 'withdrawn'];

        foreach ($campaigns->values() as $index => $campaign) {
            $status = $statuses[$index];
            $this->upsert('campaign_applications', ['campaign_id' => (string) $campaign->id, 'created_by' => $userId], [
                'organization_id' => (string) $campaign->organization_id,
                'campaign_id' => (string) $campaign->id,
                'name' => PrimaryUserJourneySeeder::NAME,
                'email' => PrimaryUserJourneySeeder::EMAIL,
                'phone' => PrimaryUserJourneySeeder::PHONE,
                'campaign_title' => (string) $campaign->title,
                'applicant_status' => $status,
                'applied_at' => now()->subDays(18 - $index * 2),
                'city' => 'حلب',
                'source' => 'mobile_app',
                'campaign_ref' => (string) $campaign->id,
                'request_type' => 'volunteer',
                'created_by' => $userId,
                'withdrawal_reason' => $status === 'withdrawn' ? 'تعارض موعد النشاط مع التزامات دراسية خلال الفترة الحالية.' : null,
                'rejection_reason' => $status === 'rejected' ? 'تم اختيار متطوعين لديهم خبرة أقرب لطبيعة النشاط الحالي.' : null,
                'created_at' => now()->subDays(18 - $index * 2),
            ]);
        }
    }

    private function seedReports(string $userId): void
    {
        if (! Schema::hasTable('reports') || ! Schema::hasTable('posts')) {
            return;
        }

        $posts = DB::table('posts')->where('status', 'published')->where('author_id', '!=', $userId)->orderBy('id')->limit(4)->get(['id', 'organization_id']);
        $adminId = DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');
        $rows = [
            ['new', 'other', 'معلومات غير واضحة في المنشور', 'أرسلت البلاغ لأن بعض تفاصيل المنشور تحتاج إلى توضيح قبل الاعتماد عليها.'],
            ['in_progress', 'abuse', 'محتوى غير لائق في المنشور', 'يتضمن المنشور عبارات غير مناسبة وأفضل أن تتم مراجعته من الإدارة.'],
            ['closed', 'fraud', 'تفاصيل تبرع تحتاج إلى تحقق', 'توجد معلومات مالية غير مكتملة وأرغب في أن تتحقق الإدارة منها.'],
            ['resolved', 'other', 'بيانات قديمة في المنشور', 'يبدو أن بعض المعلومات لم تعد محدثة وقد تسبب التباساً للمستخدمين.'],
        ];

        foreach ($posts->values() as $index => $post) {
            [$status, $category, $title, $description] = $rows[$index];
            $id = PrimaryUserJourneySeeder::id('report:'.$status.':'.$post->id);
            $this->upsert('reports', ['id' => $id], [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'status' => $status,
                'severity' => $category === 'fraud' ? 'high' : 'medium',
                'entity_type' => 'post',
                'entity_id' => (string) $post->id,
                'organization_id' => $post->organization_id,
                'reporter_id' => $userId,
                'assignee_id' => $status === 'new' ? null : $adminId,
                'evidence' => json_encode(['source' => 'mobile', 'details' => $description], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'timeline' => json_encode([['action' => 'created', 'actorId' => $userId, 'at' => now()->subDays(8 - $index)->toIso8601String()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'closed_at' => in_array($status, ['closed', 'resolved'], true) ? now()->subDays(2) : null,
                'created_at' => now()->subDays(8 - $index),
            ]);
        }
    }

    private function seedEngagement(string $userId): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        $posts = DB::table('posts')->where('status', 'published')->where('author_id', '!=', $userId)->orderByDesc('published_at')->limit(7)->pluck('id');
        foreach ($posts as $index => $postId) {
            if (Schema::hasTable('post_likes') && $index < 5) {
                $this->upsert('post_likes', ['user_id' => $userId, 'post_id' => (string) $postId], [
                    'id' => PrimaryUserJourneySeeder::id('like:'.$postId),
                    'user_id' => $userId,
                    'post_id' => (string) $postId,
                    'created_at' => now()->subDays(5 - min($index, 4)),
                ]);
            }
            if (Schema::hasTable('saved_posts') && $index < 4) {
                $this->upsert('saved_posts', ['user_id' => $userId, 'post_id' => (string) $postId], [
                    'id' => PrimaryUserJourneySeeder::id('save:'.$postId),
                    'user_id' => $userId,
                    'post_id' => (string) $postId,
                    'created_at' => now()->subDays(4 - min($index, 3)),
                ]);
            }
        }
    }

    private function recalculateCampaigns(): void
    {
        if (! Schema::hasTable('campaigns')) {
            return;
        }

        $campaignIds = collect();
        if (Schema::hasTable('donations')) {
            $campaignIds = $campaignIds->merge(DB::table('donations')->whereNotNull('campaign_id')->pluck('campaign_id'));
        }
        if (Schema::hasTable('campaign_applications')) {
            $campaignIds = $campaignIds->merge(DB::table('campaign_applications')->whereNotNull('campaign_id')->pluck('campaign_id'));
        }

        foreach ($campaignIds->filter()->unique() as $campaignId) {
            $completed = Schema::hasTable('donations')
                ? DB::table('donations')->where('campaign_id', $campaignId)->where('status', 'completed')->get(['amount_or_type', 'confirmed_amount'])
                : collect();
            $raised = $completed->sum(fn ($row) => (float) ($row->confirmed_amount ?? $row->amount_or_type ?? 0));
            $applicants = Schema::hasTable('campaign_applications')
                ? DB::table('campaign_applications')->where('campaign_id', $campaignId)->whereNotIn('applicant_status', ['rejected', 'withdrawn'])->count()
                : 0;
            $this->upsert('campaigns', ['id' => (string) $campaignId], [
                'raised_amount' => $raised,
                'donors_count' => $completed->count(),
                'applicants_count' => $applicants,
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
