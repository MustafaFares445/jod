<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class StudentAssistanceSeedRefinementSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('posts') || ! Schema::hasTable('campaigns')) {
            return;
        }

        $this->normalizeStudentAudiences();
        $this->seedStudentPosts();
        $this->ensureStudentRasterFallbacks();
    }

    private function normalizeStudentAudiences(): void
    {
        if (Schema::hasColumn('posts', 'audience')) {
            DB::table('posts')->update(['audience' => 'general']);
        }
        if (Schema::hasColumn('campaigns', 'audience')) {
            DB::table('campaigns')->update(['audience' => 'general']);
        }

        $studentCampaignTitles = [
            'الكفالة التعليمية الجزئية',
            'الكفالة التعليمية الشاملة',
            'دعم تعليم الأطفال في الرعاية البديلة',
            'سند طالب يتيم',
            'أكاديمية ملهم',
            'مشروع فرص التعليم في مخيمات الشمال السوري',
            'معهد عطاء المهني',
            'مدرسة التمريض والقبالة - تل أبيض',
            'الخيام التعليمية في شمال سوريا',
            'Safe and Equitable Access to Education',
            'Emergency Access to Education Services',
            'Al-Hikma School - Atmeh',
            'GIZ Vocational Training',
            'بداية جديدة - دعم الفتيات بالتعليم',
            'Scholarship Program',
            '100 Syrian Women, 10,000 Syrian Lives',
            'Hardship Fund',
            'High School Scholarships',
            'Jusoor-UWC International Baccalaureate Scholarships',
            'Girls Mentorship Initiative',
            'Coursera Scholarships',
            'Support for High School Students Inside Syria',
            'Karam Scholars',
            'Back to School with Karam',
            'Freelancer Hub',
            'RFG Youth',
            'Techno Lab',
            'Launch Up',
            'Work-Based Learning مع ILO',
        ];

        $studentCampaignIds = DB::table('campaigns')
            ->whereIn('title', $studentCampaignTitles)
            ->pluck('id');

        if ($studentCampaignIds->isNotEmpty()) {
            DB::table('campaigns')->whereIn('id', $studentCampaignIds)->update(['audience' => 'student']);
            DB::table('posts')->whereIn('campaign_id', $studentCampaignIds)->update(['audience' => 'student']);
        }

        // فرص العمل العامة لا تظهر تلقائياً في قسم المساعدات الطلابية.
        DB::table('posts')
            ->where('type', 'job_opportunity')
            ->where('title', 'انضم إلى فريق التواصل في سند الشباب')
            ->update(['audience' => 'general']);

        // طلبات المساعدة التعليمية الفردية مخصصة لقسم الطلاب.
        DB::table('posts')
            ->where('type', 'help_request')
            ->whereIn('title', [
                'طالب جامعي يحتاج كتباً ومراجع دراسية',
                'طالب مدرسة يحتاج قرطاسية وحقيبة',
            ])
            ->update(['audience' => 'student']);
    }

    private function seedStudentPosts(): void
    {
        $education = $this->categoryId('التعليم');
        $employment = $this->categoryId('التوظيف');
        $health = $this->categoryId('الصحة');
        $shelter = $this->categoryId('الإيواء');

        if ($education === null) {
            return;
        }

        $users = DB::table('users')
            ->whereNull('organization_id')
            ->where('user_type', '!=', 'admin')
            ->orderBy('id')
            ->limit(12)
            ->pluck('id')
            ->values();

        $helpRows = [
            ['طالب جامعي يحتاج حاسوباً محمولاً للدراسة', 'أحتاج حاسوباً محمولاً مناسباً للمحاضرات والبرامج الجامعية، ويمكن أن يكون مستعملاً بحالة جيدة.', 'حلب', $education, 'important', 'open'],
            ['طالبة تحتاج مساعدة في رسوم المواصلات الجامعية', 'أبحث عن مساعدة تغطي جزءاً من تكاليف المواصلات إلى الجامعة خلال الفصل الحالي.', 'حمص', $education, 'normal', 'open'],
            ['طالب طب يحتاج سماعة طبية وكتباً أساسية', 'أحتاج سماعة طبية وبعض المراجع الأساسية المطلوبة للتدريب السريري هذا الفصل.', 'دمشق', $health ?? $education, 'important', 'in_progress'],
            ['طالبة جامعية تبحث عن سكن مؤقت قريب من الجامعة', 'أبحث عن غرفة أو سكن مؤقت وآمن قريب من الجامعة لمدة الفصل الدراسي الحالي.', 'اللاذقية', $shelter ?? $education, 'urgent', 'open'],
            ['طالب ثانوي يحتاج قرطاسية وكتباً تحضيرية', 'أحتاج مجموعة قرطاسية وكتباً تحضيرية تساعدني على متابعة الدراسة والاستعداد للامتحانات.', 'حماة', $education, 'normal', 'open'],
            ['طالبة تحتاج رسوم تسجيل لدورة تحضيرية', 'أحتاج مساعدة جزئية لتغطية رسوم دورة تحضيرية مرتبطة بمتطلبات الدراسة الجامعية.', 'درعا', $education, 'important', 'open'],
        ];

        if ($users->isNotEmpty()) {
            foreach ($helpRows as $index => [$title, $summary, $location, $categoryId, $urgency, $helpStatus]) {
                $postId = $this->id('student-help:'.$index.':'.$title);
                $this->upsertPost([
                    'id' => $postId,
                    'title' => $title,
                    'summary' => $summary,
                    'content' => $summary.' أرجو ممن يستطيع المساعدة التواصل عبر المنصة لتنسيق التفاصيل وطريقة الاستلام المناسبة.',
                    'type' => 'help_request',
                    'audience' => 'student',
                    'status' => 'published',
                    'help_status' => $helpStatus,
                    'urgency' => $urgency,
                    'location' => $location,
                    'organization_id' => null,
                    'campaign_id' => null,
                    'category_id' => $categoryId,
                    'author_id' => $users[$index % $users->count()],
                    'views_count' => 90 + ($index * 37),
                    'reactions_count' => 4 + ($index * 3),
                    'applications_count' => 1 + ($index % 3),
                    'published_at' => now()->subDays(1 + $index),
                    'expires_at' => now()->addDays(14 + $index),
                    'created_at' => now()->subDays(2 + $index),
                    'updated_at' => now(),
                ]);
            }
        }

        $organizationRows = [
            ['منظمة جسور', 'service_offer', 'إرشاد للتقديم على المنح الجامعية', 'جلسات إرشادية تساعد الطلاب على تجهيز ملف التقديم للمنح وترتيب الوثائق وكتابة السيرة ورسالة الدافع.', 'سوريا', $education],
            ['مؤسسة سند الشباب التنموية', 'service_offer', 'جلسات توجيه مهني للطلاب والخريجين', 'جلسات تساعد الطلاب والخريجين الجدد على تحديد المسار المهني وتطوير السيرة الذاتية والاستعداد لفرص التدريب والعمل.', 'دمشق', $employment ?? $education],
            ['فريق ملهم التطوعي', 'donation_campaign', 'ساهم في سند طالب يتيم', 'دعم الطلاب الأيتام يساعدهم على متابعة تعليمهم وتغطية جزء من احتياجات الدراسة الأساسية.', 'سوريا', $education],
            ['منظمة جسور', 'awareness', 'كيف تجهز ملفاً قوياً للتقديم على منحة؟', 'ابدأ مبكراً بجمع الوثائق، راجع شروط المنحة بدقة، وخصص رسالة الدافع لتشرح أهدافك الأكاديمية وخطتك بوضوح.', 'سوريا', $education],
            ['Karam Foundation - Beit Karam', 'volunteer_opportunity', 'مرشدون متطوعون لدعم الطلاب', 'فرصة للمتخصصين والخريجين للمساهمة بجلسات إرشاد أكاديمي ومهني تساعد الطلاب على اتخاذ قرارات أفضل حول الدراسة والمسار المهني.', 'سوريا', $education],
            ['منظمة جسور', 'general', 'منح Coursera للشباب السوري', 'فرص تعلم عبر الإنترنت تساعد الطلاب والشباب على تطوير مهارات عملية في مجالات متعددة إلى جانب الدراسة الأكاديمية.', 'سوريا', $education],
        ];

        foreach ($organizationRows as $index => [$organizationName, $type, $title, $summary, $location, $categoryId]) {
            $organization = DB::table('organizations')->where('name', $organizationName)->first();
            if ($organization === null) {
                continue;
            }
            $authorId = DB::table('users')->where('organization_id', $organization->id)->orderBy('id')->value('id');
            if ($authorId === null) {
                continue;
            }

            $campaignId = null;
            if ($organizationName === 'فريق ملهم التطوعي') {
                $campaignId = DB::table('campaigns')->where('organization_id', $organization->id)->where('title', 'سند طالب يتيم')->value('id');
            }
            if ($organizationName === 'منظمة جسور' && $title === 'منح Coursera للشباب السوري') {
                $campaignId = DB::table('campaigns')->where('organization_id', $organization->id)->where('title', 'Coursera Scholarships')->value('id');
            }

            $postId = $this->id('student-org:'.$index.':'.$title);
            $this->upsertPost([
                'id' => $postId,
                'title' => $title,
                'summary' => $summary,
                'content' => $summary.' يمكن متابعة التفاصيل والتقديم أو طلب الاستفادة من خلال الجهة صاحبة المبادرة.',
                'type' => $type,
                'audience' => 'student',
                'status' => 'published',
                'location' => $location,
                'organization_id' => $organization->id,
                'campaign_id' => $campaignId,
                'category_id' => $categoryId,
                'author_id' => $authorId,
                'views_count' => 260 + ($index * 89),
                'reactions_count' => 18 + ($index * 7),
                'applications_count' => in_array($type, ['service_offer', 'volunteer_opportunity'], true) ? 6 + $index : 0,
                'published_at' => now()->subDays(2 + $index),
                'created_at' => now()->subDays(3 + $index),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureStudentRasterFallbacks(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $source = database_path('assets/syrian-fallback/student-assistance.png');
        if (! is_file($source)) {
            return;
        }

        $path = 'demo/syria/fallback/student-assistance.png';
        $bytes = (string) file_get_contents($source);
        Storage::disk('public')->put($path, $bytes);
        $size = strlen($bytes);

        $studentPostIds = DB::table('posts')->where('audience', 'student')->pluck('id');
        $studentCampaignIds = DB::table('campaigns')->where('audience', 'student')->pluck('id');

        if ($studentPostIds->isNotEmpty()) {
            DB::table('media')
                ->whereIn('model_id', $studentPostIds)
                ->where(function ($query): void {
                    $query->where('mime_type', 'image/svg+xml')->orWhere('path', 'like', '%.svg');
                })
                ->update([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => 'student-assistance.png',
                    'mime_type' => 'image/png',
                    'size' => $size,
                    'description' => 'صورة توضيحية للمساعدات والفرص الطلابية.',
                    'updated_at' => now(),
                ]);

            foreach ($studentPostIds as $postId) {
                if (! DB::table('media')->where('model_type', 'post')->where('model_id', $postId)->exists()) {
                    DB::table('media')->insert([
                        'id' => $this->id('student-media:post:'.$postId),
                        'model_type' => 'post',
                        'model_id' => $postId,
                        'post_id' => $postId,
                        'prop' => 'images',
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => 'student-assistance.png',
                        'description' => 'صورة توضيحية للمساعدات والفرص الطلابية.',
                        'mime_type' => 'image/png',
                        'size' => $size,
                        'position' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if ($studentCampaignIds->isNotEmpty()) {
            DB::table('media')
                ->whereIn('model_id', $studentCampaignIds)
                ->where(function ($query): void {
                    $query->where('mime_type', 'image/svg+xml')->orWhere('path', 'like', '%.svg');
                })
                ->update([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => 'student-assistance.png',
                    'mime_type' => 'image/png',
                    'size' => $size,
                    'description' => 'صورة توضيحية للمساعدات والفرص الطلابية.',
                    'updated_at' => now(),
                ]);
        }
    }

    private function categoryId(string $name): ?string
    {
        if (! Schema::hasTable('categories')) {
            return null;
        }

        $id = DB::table('categories')->where('name', $name)->value('id');
        return $id === null ? null : (string) $id;
    }

    private function upsertPost(array $attributes): void
    {
        $columns = array_flip(Schema::getColumnListing('posts'));
        $attributes = array_intersect_key($attributes, $columns);
        DB::table('posts')->updateOrInsert(['id' => $attributes['id']], $attributes);
    }

    private function id(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-student-assistance:'.$key), 0, 32);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
