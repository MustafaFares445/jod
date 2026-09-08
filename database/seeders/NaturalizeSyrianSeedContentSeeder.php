<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class NaturalizeSyrianSeedContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->naturalizeOrganizations();
        $this->naturalizeCampaigns();
        $this->naturalizePosts();
        $this->naturalizeMediaDescriptions();
    }

    private function naturalizeOrganizations(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        DB::table('organizations')->orderBy('id')->chunk(100, function ($organizations): void {
            foreach ($organizations as $organization) {
                $type = (string) ($organization->organization_type ?? 'ngo');
                $location = (string) ($organization->location ?: 'سوريا');

                $description = match ($type) {
                    'government' => 'جهة حكومية محلية تعمل على إدارة الخدمات والمبادرات التنموية في '.$location.' والتنسيق مع الجهات الشريكة لتحسين البنية والخدمات العامة.',
                    'community_group' => 'فريق مجتمعي يعمل على تنفيذ مبادرات إنسانية وتطوعية ودعم الفئات المحتاجة في '.$location.' من خلال حملات ومشاريع مجتمعية متنوعة.',
                    'charity' => 'جهة خيرية وإنسانية تعمل على دعم الأسر والفئات الأكثر احتياجاً في '.$location.' عبر برامج ومشاريع تستجيب للاحتياجات الأساسية وتدعم الاستقرار المجتمعي.',
                    default => 'منظمة غير حكومية تعمل على تنفيذ برامج ومبادرات إنسانية وتنموية في '.$location.'، وتدعم المجتمعات المحلية عبر مشاريع متخصصة وشراكات ميدانية.',
                };

                DB::table('organizations')->where('id', $organization->id)->update([
                    'description' => $description,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function naturalizeCampaigns(): void
    {
        if (! Schema::hasTable('campaigns')) {
            return;
        }

        $categories = $this->categoryMap();

        DB::table('campaigns')->orderBy('id')->chunk(100, function ($campaigns) use ($categories): void {
            foreach ($campaigns as $campaign) {
                $title = (string) ($campaign->title ?: 'المبادرة');
                $location = (string) ($campaign->location ?: 'سوريا');
                $category = $categories[(string) $campaign->category_id] ?? 'other';
                $focus = $this->focus($category);

                $summary = $title.' مبادرة في '.$location.' تركز على '.$focus.'.';
                $content = 'تعمل '.$title.' في '.$location.' على '.$focus.'. وتركز الأنشطة على توجيه الدعم نحو الاحتياجات الأكثر أولوية، وتحسين الوصول إلى الخدمات، وتعزيز قدرة المجتمع على التعافي والاستقرار.';

                $pledge = $this->extractPublishedPledge((string) ($campaign->content ?? ''));
                if ($pledge !== null) {
                    $content .= ' وقد أُعلن ضمن الحملة عن تبرعات وتعهدات بقيمة '.$pledge.' دولار لدعم المشاريع والأولويات المحددة.';
                }

                DB::table('campaigns')->where('id', $campaign->id)->update([
                    'summary' => $summary,
                    'content' => $content,
                    'closed_reason' => ($campaign->status ?? null) === 'closed' ? 'انتهت المرحلة الحالية من الحملة أو المشروع.' : null,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function naturalizePosts(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        $categories = $this->categoryMap();
        $campaignTitles = Schema::hasTable('campaigns')
            ? DB::table('campaigns')->pluck('title', 'id')->map(fn ($value) => (string) $value)->all()
            : [];

        DB::table('posts')->orderBy('id')->chunk(100, function ($posts) use ($categories, $campaignTitles): void {
            foreach ($posts as $post) {
                $type = (string) ($post->type ?: 'general');
                $category = $categories[(string) $post->category_id] ?? 'other';
                $location = (string) ($post->location ?: 'سوريا');
                $focus = $this->focus($category);
                $campaignTitle = isset($post->campaign_id) ? ($campaignTitles[(string) $post->campaign_id] ?? null) : null;

                [$title, $summary, $content] = $this->postCopy(
                    $type,
                    (string) ($post->title ?: ''),
                    (string) ($post->summary ?: ''),
                    $campaignTitle,
                    $focus,
                    $location,
                );

                $attributes = [
                    'title' => $title,
                    'summary' => $summary,
                    'content' => $content,
                    'updated_at' => now(),
                ];

                if ($type === 'help_request' && Schema::hasColumn('posts', 'urgency_reason') && ($post->urgency ?? null) === 'critical') {
                    $attributes['urgency_reason'] = 'الحاجة عاجلة ولا يمكن تأجيل تقديم الدعم لفترة طويلة.';
                }

                DB::table('posts')->where('id', $post->id)->update($attributes);
            }
        });
    }

    private function naturalizeMediaDescriptions(): void
    {
        if (! Schema::hasTable('media') || ! Schema::hasColumn('media', 'description')) {
            return;
        }

        DB::table('media')->where('description', 'like', '%المصدر الرسمي%')->update([
            'description' => 'صورة مرتبطة بالنشاط أو المشروع.',
            'updated_at' => now(),
        ]);
        DB::table('media')->where('description', 'like', '%افتراضية%')->update([
            'description' => 'صورة توضيحية مرتبطة بطبيعة المحتوى.',
            'updated_at' => now(),
        ]);
    }

    private function postCopy(string $type, string $existingTitle, string $existingSummary, ?string $campaignTitle, string $focus, string $location): array
    {
        $campaignTitle ??= $this->stripMetaTitle($existingTitle) ?: 'المبادرة';

        return match ($type) {
            'campaign_teaser' => [
                'تعرفوا على '.$campaignTitle,
                $campaignTitle.' مبادرة في '.$location.' تركز على '.$focus.'.',
                'من خلال '.$campaignTitle.' يجري العمل على '.$focus.'، مع توجيه الأنشطة إلى الاحتياجات الأكثر أولوية في '.$location.' ودعم المستفيدين بالخدمات المناسبة.',
            ],
            'campaign_update' => [
                'تحديث: '.$campaignTitle,
                'تتواصل أنشطة '.$campaignTitle.' في '.$location.' ضمن جهود '.$focus.'.',
                'تواصل الفرق تنفيذ الأنشطة المرتبطة بـ'.$campaignTitle.' مع التركيز على '.$focus.'، ومتابعة الاحتياجات الميدانية وتوجيه الموارد والخدمات إلى الفئات والمناطق ذات الأولوية.',
            ],
            'campaign_summary' => [
                'حصاد '.$campaignTitle,
                'أبرز ما ركزت عليه '.$campaignTitle.' في '.$location.': '.$focus.'.',
                'اختتمت المرحلة الحالية من '.$campaignTitle.' بعد تنفيذ أنشطة ركزت على '.$focus.'. ويستمر تقييم الاحتياجات والنتائج لتحديد الخطوات التالية ودعم استدامة الأثر في '.$location.'.',
            ],
            'job_opportunity' => [
                $existingTitle,
                $this->cleanMeta($existingSummary),
                $this->cleanMeta($existingSummary).' تستهدف الفرصة المهتمين بالعمل في هذا المجال، ويمكن متابعة شروط التقديم والمواعيد عبر قنوات الجهة المعنية.',
            ],
            'volunteer_opportunity' => [
                $existingTitle,
                $this->cleanMeta($existingSummary),
                $this->cleanMeta($existingSummary).' تتيح المبادرة للراغبين بالمشاركة المجتمعية فرصة المساهمة بالوقت والخبرة ودعم الأنشطة الميدانية في '.$location.'.',
            ],
            'service_offer' => [
                $existingTitle,
                $this->cleanMeta($existingSummary),
                $this->cleanMeta($existingSummary).' تهدف الخدمة إلى تسهيل وصول المستفيدين إلى الدعم المناسب وتحسين الاستجابة للاحتياجات المرتبطة بـ'.$focus.'.',
            ],
            'donation_campaign' => [
                $existingTitle,
                $this->cleanMeta($existingSummary),
                $this->cleanMeta($existingSummary).' تساهم التبرعات في توسيع نطاق الدعم وتلبية الاحتياجات المرتبطة بـ'.$focus.' بحسب أولويات الحملة.',
            ],
            'awareness' => [
                $existingTitle,
                $this->cleanMeta($existingSummary),
                $this->cleanMeta($existingSummary).' يهدف هذا المحتوى إلى رفع الوعي وتقديم معلومات عملية تساعد الأفراد والأسر على التعامل بشكل أفضل مع القضايا المرتبطة بـ'.$focus.'.',
            ],
            'help_request' => [
                $existingTitle,
                $this->cleanMeta($existingSummary),
                $this->cleanMeta($existingSummary).' نبحث عن جهة أو شخص قادر على تقديم المساعدة المناسبة في '.$location.'، مع إمكانية التنسيق لتحديد الاحتياج والتفاصيل اللازمة بما يضمن وصول الدعم بالشكل الأنسب.',
            ],
            default => [
                $existingTitle,
                $this->cleanMeta($existingSummary),
                $this->cleanMeta($existingSummary).' يأتي هذا النشاط ضمن الجهود المستمرة في '.$location.' لدعم '.$focus.'.',
            ],
        };
    }

    private function categoryMap(): array
    {
        if (! Schema::hasTable('categories')) {
            return [];
        }

        $map = [];
        foreach (DB::table('categories')->get(['id', 'name']) as $category) {
            $map[(string) $category->id] = match ((string) $category->name) {
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
                default => 'other',
            };
        }

        return $map;
    }

    private function focus(string $category): string
    {
        return match ($category) {
            'health' => 'توسيع الوصول إلى الرعاية الصحية والخدمات الطبية',
            'education' => 'دعم التعليم وتحسين فرص التعلم والتدريب',
            'food' => 'تحسين الوصول إلى الغذاء وتعزيز الأمن الغذائي للأسر',
            'emergency' => 'الاستجابة للاحتياجات العاجلة ودعم المتضررين',
            'shelter' => 'تحسين ظروف السكن والإيواء ودعم الأسر المتضررة',
            'employment' => 'بناء المهارات وخلق فرص للتدريب والعمل وسبل العيش',
            'volunteering' => 'تعزيز المشاركة المجتمعية والعمل التطوعي',
            'protection' => 'تعزيز الحماية والدعم النفسي والاجتماعي للفئات الأكثر عرضة للمخاطر',
            'wash' => 'تحسين خدمات المياه والصرف الصحي والنظافة',
            'children' => 'رعاية الأطفال ودعم احتياجاتهم التعليمية والاجتماعية',
            'disability' => 'تحسين الوصول إلى خدمات التأهيل والدعم للأشخاص ذوي الإعاقة',
            'reconstruction' => 'إعادة تأهيل الخدمات والبنية التحتية ودعم التعافي المحلي',
            default => 'دعم الاحتياجات الإنسانية والتنموية للمجتمع',
        };
    }

    private function cleanMeta(string $text): string
    {
        $text = preg_replace('/\s*(هذا المنشور جزء من بيانات JOD[^.]*\.?|المصدر المرجعي\s*:[^\n]*|نوع التوثيق\s*:[^.]*\.?|هذا سجل تجريبي[^.]*\.?)/u', '', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $text !== '' ? $text : 'تفاصيل ومعلومات حول النشاط والخدمات المقدمة للمستفيدين.';
    }

    private function stripMetaTitle(string $title): string
    {
        return trim(preg_replace('/^(تعرفوا على|تحديث من|تحديث:|ملخص|حصاد)\s+/u', '', $title) ?? $title);
    }

    private function extractPublishedPledge(string $content): ?string
    {
        if (preg_match('/القيمة المنشورة للتبرعات\/التعهدات:\s*([0-9,]+)\s*دولار/u', $content, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
