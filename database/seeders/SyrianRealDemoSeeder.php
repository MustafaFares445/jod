<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SyrianRealDemoSeeder extends Seeder
{
    private array $categories = [];
    private array $organizations = [];
    private array $owners = [];
    private array $campaigns = [];
    private array $media = [];

    public function run(): void
    {
        $data = json_decode(
            (string) file_get_contents(database_path('data/syrian_real_seed.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        DB::transaction(function () use ($data): void {
            $this->purgeVisibleDemo();
            $this->seedCategories($data['categories']);
            $this->seedOrganizations($data['organizations']);
            $this->seedCampaigns($data['organizations']);
            $this->seedSpecialPosts($data['special_posts']);
            $this->seedHelpRequests($data['synthetic_help_requests']);
            $this->refreshCounters();
        });

        $this->seedMedia();
    }

    private function purgeVisibleDemo(): void
    {
        foreach ([
            'post_feedback', 'recommendation_impressions', 'user_interactions', 'post_capabilities',
            'post_likes', 'saved_posts', 'help_offers', 'campaign_likes', 'campaign_applications',
            'donations', 'media', 'posts', 'campaigns', 'organization_staff', 'organization_roles',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (Schema::hasTable('publisher_follows') && Schema::hasColumn('publisher_follows', 'target_type')) {
            DB::table('publisher_follows')->where('target_type', 'organization')->delete();
        }
        if (Schema::hasTable('hidden_publishers') && Schema::hasColumn('hidden_publishers', 'publisher_type')) {
            DB::table('hidden_publishers')->where('publisher_type', 'organization')->delete();
        }
        if (Schema::hasTable('organizations')) {
            DB::table('organizations')->delete();
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'organization_id')) {
            DB::table('users')->whereNotNull('organization_id')->update([
                'organization_id' => null,
                'updated_at' => now(),
            ]);
        }
    }

    private function seedCategories(array $rows): void
    {
        foreach ($rows as [$key, $name, $description]) {
            $id = $this->id('category:'.$key);
            $this->categories[$key] = $id;
            $this->upsert('categories', ['id' => $id], [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'status' => 'active',
                'usage_count' => 0,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedOrganizations(array $rows): void
    {
        foreach ($rows as $org) {
            $key = (string) $org['key'];
            $id = $this->id($key);
            $ownerId = $this->id('owner:'.$key);
            $code = strtoupper(substr(hash('sha256', $key), 0, 12));
            $this->organizations[$key] = $id;
            $this->owners[$key] = $ownerId;

            $this->upsert('organizations', ['id' => $id], [
                'id' => $id,
                'name' => $org['name'],
                'email' => $org['email'] ?? strtolower($code).'@demo.jod.local',
                'phone' => $org['phone'] ?? null,
                'organization_number' => 'JOD-DEMO-'.$code,
                'organization_type' => $org['type'],
                'registration_number' => $org['registration_number'] ?? null,
                'bank_account_number' => 'JOD-DEMO-ACCOUNT-'.$code,
                'establishment_date' => $org['established'] ?? null,
                'short_address' => $org['location'],
                'description' => 'جهة حقيقية ضمن بيانات JOD، والمصدر المرجعي محفوظ في social_media.source. بيانات البنك وحساب الإدارة تجريبية فقط.',
                'location' => $org['location'],
                'website' => $org['website'] ?? null,
                'social_media' => json_encode(['source' => $org['source']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'bank_name' => 'JOD Demo Bank',
                'iban' => 'JOD-DEMO-'.$code,
                'status' => 'active',
                'verification_status' => 'verified',
                'accepted_at' => now()->subMonths(8),
                'campaigns_count' => 0,
                'posts_count' => 0,
                'active_volunteers_count' => 1,
                'activity_score' => 8.5,
                'last_active_at' => now()->subHours(2),
                'deleted_at' => null,
                'created_at' => now()->subMonths(8),
                'updated_at' => now(),
            ]);

            $ownerEmail = 'owner+'.strtolower($code).'@demo.jod.local';
            $this->upsert('users', ['id' => $ownerId], [
                'id' => $ownerId,
                'name' => 'حساب إدارة '.$org['name'],
                'email' => $ownerEmail,
                'user_type' => 'general',
                'organization_id' => $id,
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => Hash::make('demo-password'),
                'last_active_at' => now()->subHours(3),
                'created_at' => now()->subMonths(8),
                'updated_at' => now(),
            ]);

            $roleId = $this->id('role:'.$key.':owner');
            $this->upsert('organization_roles', ['id' => $roleId], [
                'id' => $roleId,
                'organization_id' => $id,
                'name' => 'Owner',
                'description' => 'JOD demo owner role',
                'permissions' => json_encode([], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'is_system' => true,
                'members_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->upsert('organization_staff', ['organization_id' => $id, 'user_id' => $ownerId], [
                'organization_id' => $id,
                'user_id' => $ownerId,
                'organization_role_id' => $roleId,
                'name' => 'حساب إدارة '.$org['name'],
                'email' => $ownerEmail,
                'status' => 'active',
                'invited_at' => now(),
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->queueMedia('organization', $key, $id, 'organization', null);
        }
    }

    private function seedCampaigns(array $organizations): void
    {
        foreach ($organizations as $org) {
            $orgKey = (string) $org['key'];
            foreach ($org['campaigns'] as $index => $row) {
                $row = array_pad($row, 10, null);
                [$title, $category, $location, $status, $goal, $raised, $provenance, $source, $image, $reportedPledge] = $row;
                $source ??= $org['source'];

                if (! isset($this->categories[$category])) {
                    throw new \RuntimeException("Unknown Syrian seed category: {$category}");
                }

                $campaignId = $this->id('campaign:'.$orgKey.':'.$index.':'.$title);
                $this->campaigns[$orgKey.'|'.$title] = $campaignId;
                $details = match ($provenance) {
                    'documented' => 'اسم الحملة أو المشروع موثق في المصدر المرجعي.',
                    'documented_preparation' => 'المصدر يوثق التحضير أو الإطلاق ولا يثبت حصيلة نهائية.',
                    default => 'سجل JOD مبني على برنامج أو قطاع عمل موثق للجهة.',
                };
                if ($reportedPledge !== null) {
                    $details .= "\nالقيمة المنشورة للتبرعات/التعهدات: ".number_format((float) $reportedPledge, 0).' دولار، ولا تعامل تلقائياً كمبلغ محصل داخل JOD.';
                }

                $this->upsert('campaigns', ['id' => $campaignId], [
                    'id' => $campaignId,
                    'title' => $title,
                    'summary' => $this->summary($category, $location),
                    'content' => $details."\nالمصدر المرجعي: ".$source,
                    'category_id' => $this->categories[$category],
                    'audience' => in_array($category, ['education', 'employment'], true) && $index % 5 === 0 ? 'student' : 'general',
                    'status' => $status,
                    'location' => $location,
                    'images' => json_encode([], JSON_THROW_ON_ERROR),
                    'organization_id' => $this->organizations[$orgKey],
                    'creator_id' => $this->owners[$orgKey],
                    'goal_amount' => $goal ?? 0,
                    'raised_amount' => $raised ?? 0,
                    'beneficiaries_count' => 0,
                    'donors_count' => 0,
                    'applicants_count' => 0,
                    'submitted_at' => now()->subDays(60 + $index),
                    'closed_at' => $status === 'closed' ? now()->subDays(5 + ($index % 25)) : null,
                    'closed_reason' => $status === 'closed' ? 'انتهت المرحلة الموثقة من الحملة أو المشروع.' : null,
                    'deleted_at' => null,
                    'created_at' => now()->subDays(90 + $index),
                    'updated_at' => now()->subDays($index % 8),
                ]);

                $this->queueMedia('campaign', $orgKey.':'.$index, $campaignId, $category, $image);
                $this->seedCampaignPost($orgKey, $campaignId, $title, $category, $location, 'campaign_teaser', $source, $image, $index * 3);
                $this->seedCampaignPost($orgKey, $campaignId, $title, $category, $location, 'campaign_update', $source, $image, ($index * 3) + 1);
                if ($status === 'closed') {
                    $this->seedCampaignPost($orgKey, $campaignId, $title, $category, $location, 'campaign_summary', $source, $image, ($index * 3) + 2);
                }
            }
        }
    }

    private function seedCampaignPost(string $orgKey, string $campaignId, string $campaignTitle, string $category, string $location, string $type, string $source, ?string $image, int $offset): void
    {
        $postId = $this->id('post:'.$orgKey.':'.$campaignId.':'.$type.':'.$offset);
        $prefix = $type === 'campaign_summary' ? 'ملخص' : ($type === 'campaign_teaser' ? 'تعرفوا على' : 'تحديث من');
        $this->upsert('posts', ['id' => $postId], [
            'id' => $postId,
            'title' => $prefix.' '.$campaignTitle,
            'summary' => 'منشور مرتبط بالحملة أو المشروع اعتماداً على المصدر المرجعي للجهة.',
            'content' => 'هذا المنشور جزء من بيانات JOD الواقعية المرتبطة بمشروع موثق. المصدر المرجعي: '.$source,
            'type' => $type,
            'audience' => in_array($category, ['education', 'employment'], true) && $offset % 7 === 0 ? 'student' : 'general',
            'status' => 'published',
            'location' => $location,
            'organization_id' => $this->organizations[$orgKey],
            'campaign_id' => $campaignId,
            'category_id' => $this->categories[$category],
            'author_id' => $this->owners[$orgKey],
            'views_count' => 120 + (($offset * 37) % 4200),
            'reactions_count' => 8 + (($offset * 11) % 340),
            'applications_count' => 0,
            'published_at' => now()->subDays(30 + ($offset % 100)),
            'deleted_at' => null,
            'created_at' => now()->subDays(35 + ($offset % 100)),
            'updated_at' => now()->subDays($offset % 8),
        ]);
        $this->queueMedia('post', 'post:'.$postId, $postId, $category, $image);
    }

    private function seedSpecialPosts(array $rows): void
    {
        foreach ($rows as $index => $row) {
            [$orgKey, $campaignTitle, $type, $category, $location, $title, $summary, $provenance] = $row;
            if (! isset($this->organizations[$orgKey], $this->categories[$category])) {
                throw new \RuntimeException("Invalid special post reference: {$orgKey}/{$category}");
            }
            $campaignId = $campaignTitle ? ($this->campaigns[$orgKey.'|'.$campaignTitle] ?? null) : null;
            if ($campaignTitle && $campaignId === null) {
                throw new \RuntimeException("Unknown special post campaign: {$orgKey}/{$campaignTitle}");
            }
            $postId = $this->id('special:'.$orgKey.':'.$index);
            $this->upsert('posts', ['id' => $postId], [
                'id' => $postId,
                'title' => $title,
                'summary' => $summary,
                'content' => $summary.' نوع التوثيق: '.$provenance.'.',
                'type' => $type,
                'audience' => in_array($category, ['education', 'employment'], true) ? 'student' : 'general',
                'status' => 'published',
                'location' => $location,
                'organization_id' => $this->organizations[$orgKey],
                'campaign_id' => $campaignId,
                'category_id' => $this->categories[$category],
                'author_id' => $this->owners[$orgKey],
                'views_count' => 450 + ($index * 83),
                'reactions_count' => 25 + ($index * 13),
                'applications_count' => in_array($type, ['job_opportunity', 'volunteer_opportunity'], true) ? 12 + $index : 0,
                'published_at' => now()->subDays(5 + $index),
                'deleted_at' => null,
                'created_at' => now()->subDays(6 + $index),
                'updated_at' => now(),
            ]);
            $this->queueMedia('post', 'special:'.$postId, $postId, $category, null);
        }
    }

    private function seedHelpRequests(array $rows): void
    {
        $users = DB::table('users')->whereNull('organization_id')->where('user_type', '!=', 'admin')->orderBy('id')->limit(12)->pluck('id');
        if ($users->isEmpty()) {
            return;
        }

        $urgencies = ['normal', 'important', 'urgent', 'critical', 'important', 'urgent', 'normal', 'important'];
        $statuses = ['open', 'in_progress', 'partially_fulfilled', 'fulfilled', 'open', 'expired', 'open', 'not_fulfilled'];
        foreach ($rows as $index => [$category, $location, $title, $summary]) {
            if (! isset($this->categories[$category])) {
                throw new \RuntimeException("Unknown help request category: {$category}");
            }
            $postId = $this->id('help:'.$index.':'.$title);
            $urgency = $urgencies[$index % count($urgencies)];
            $helpStatus = $statuses[$index % count($statuses)];
            $this->upsert('posts', ['id' => $postId], [
                'id' => $postId,
                'title' => $title,
                'summary' => $summary,
                'content' => $summary.' هذا سجل تجريبي لا يمثل شخصاً حقيقياً أو حالة منشورة خارج JOD.',
                'type' => 'help_request',
                'audience' => $category === 'education' ? 'student' : 'general',
                'status' => 'published',
                'help_status' => $helpStatus,
                'urgency' => $urgency,
                'urgency_reason' => $urgency === 'critical' ? 'حالة تجريبية حرجة لاختبار الترتيب والمراجعة.' : null,
                'location' => $location,
                'organization_id' => null,
                'campaign_id' => null,
                'category_id' => $this->categories[$category],
                'author_id' => $users[$index % $users->count()],
                'views_count' => 70 + ($index * 31),
                'reactions_count' => 3 + ($index * 4),
                'applications_count' => 1 + ($index % 5),
                'published_at' => now()->subDays(2 + $index),
                'expires_at' => $helpStatus === 'expired' ? now()->subDay() : now()->addDays(10 + $index),
                'fulfilled_at' => in_array($helpStatus, ['fulfilled', 'partially_fulfilled'], true) ? now()->subDay() : null,
                'deleted_at' => null,
                'created_at' => now()->subDays(3 + $index),
                'updated_at' => now(),
            ]);
            $this->queueMedia('post', 'help:'.$postId, $postId, $category, null);
        }
    }

    private function refreshCounters(): void
    {
        foreach ($this->categories as $id) {
            DB::table('categories')->where('id', $id)->update([
                'usage_count' => DB::table('campaigns')->where('category_id', $id)->count()
                    + DB::table('posts')->where('category_id', $id)->count(),
            ]);
        }
        foreach ($this->organizations as $id) {
            DB::table('organizations')->where('id', $id)->update([
                'campaigns_count' => DB::table('campaigns')->where('organization_id', $id)->count(),
                'posts_count' => DB::table('posts')->where('organization_id', $id)->count(),
            ]);
        }
    }

    private function queueMedia(string $type, string $key, string $id, string $category, ?string $source): void
    {
        $this->media[] = compact('type', 'key', 'id', 'category', 'source');
    }

    private function seedMedia(): void
    {
        foreach ($this->media as $row) {
            $disk = Storage::disk('public');
            $base = 'demo/syria/'.$row['type'].'/'.substr(hash('sha256', $row['key']), 0, 24);
            $path = $base.'.jpg';
            $mime = 'image/jpeg';
            $official = false;

            if ($row['source']) {
                try {
                    $response = Http::timeout(15)->retry(1, 200)->get($row['source']);
                    $contentType = strtolower((string) $response->header('Content-Type'));
                    if ($response->successful() && str_starts_with($contentType, 'image/')) {
                        $ext = str_contains($contentType, 'png') ? 'png' : (str_contains($contentType, 'webp') ? 'webp' : 'jpg');
                        $path = $base.'.'.$ext;
                        $mime = explode(';', $contentType)[0];
                        $disk->put($path, $response->body());
                        $official = true;
                    }
                } catch (Throwable) {
                    $this->command?->warn('Official image unavailable: '.$row['key']);
                }
            }

            if (! $official) {
                $fallback = $this->fallbackKey($row['category']);
                $path = 'demo/syria/fallback/'.$fallback.'.svg';
                $mime = 'image/svg+xml';
                if (! $disk->exists($path)) {
                    $disk->put($path, $this->fallbackSvg($fallback));
                }
            }

            $this->upsert('media', ['id' => $this->id('media:'.$row['type'].':'.$row['key'])], [
                'id' => $this->id('media:'.$row['type'].':'.$row['key']),
                'model_type' => $row['type'],
                'model_id' => $row['id'],
                'post_id' => $row['type'] === 'post' ? $row['id'] : null,
                'prop' => $row['type'] === 'organization' ? 'logo' : 'images',
                'disk' => 'public',
                'path' => $path,
                'original_name' => basename($path),
                'description' => $official ? 'صورة من المصدر الرسمي.' : 'صورة افتراضية ثابتة مرتبطة بطبيعة المحتوى.',
                'mime_type' => $mime,
                'size' => $disk->exists($path) ? $disk->size($path) : 0,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function summary(string $category, string $location): string
    {
        $labels = [
            'health' => 'مشروع صحي', 'education' => 'مشروع تعليمي', 'food' => 'مشروع للأمن الغذائي',
            'emergency' => 'استجابة طارئة', 'shelter' => 'مشروع إيواء', 'employment' => 'مشروع للتدريب وسبل العيش',
            'volunteering' => 'مبادرة تطوعية', 'protection' => 'مشروع حماية', 'wash' => 'مشروع مياه وإصحاح',
            'children' => 'مشروع لرعاية الأطفال', 'disability' => 'مشروع لدعم ذوي الإعاقة',
            'reconstruction' => 'مشروع تعافٍ وإعادة إعمار',
        ];
        return ($labels[$category] ?? 'مشروع إنساني وتنموي').' مرتبط بـ'.$location.'.';
    }

    private function fallbackKey(string $category): string
    {
        $allowed = ['health', 'education', 'food', 'emergency', 'shelter', 'employment', 'wash', 'reconstruction', 'children', 'disability', 'protection', 'volunteering'];
        return in_array($category, $allowed, true) ? $category : 'organization';
    }

    private function fallbackSvg(string $key): string
    {
        $labels = [
            'health' => 'الصحة', 'education' => 'التعليم', 'food' => 'الغذاء', 'emergency' => 'الطوارئ',
            'shelter' => 'الإيواء', 'employment' => 'العمل والتدريب', 'wash' => 'المياه والإصحاح',
            'reconstruction' => 'إعادة الإعمار', 'children' => 'الأطفال', 'disability' => 'ذوي الإعاقة',
            'protection' => 'الحماية', 'volunteering' => 'التطوع', 'organization' => 'منظمة سورية',
        ];
        $label = htmlspecialchars($labels[$key] ?? 'JOD', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675"><rect width="1200" height="675" fill="#f5f6f8"/><rect x="70" y="70" width="1060" height="535" rx="48" fill="#fff" stroke="#d8dde6" stroke-width="4"/><text x="600" y="340" text-anchor="middle" direction="rtl" font-family="Arial" font-size="64" font-weight="700" fill="#1f2937">'.$label.'</text><text x="600" y="420" text-anchor="middle" direction="rtl" font-family="Arial" font-size="34" fill="#6b7280">صورة افتراضية ثابتة لبيانات JOD</text></svg>';
    }

    private function upsert(string $table, array $where, array $attributes): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $columns = array_flip(Schema::getColumnListing($table));
        $where = array_intersect_key($where, $columns);
        $attributes = array_intersect_key($attributes, $columns);
        if ($where !== []) {
            DB::table($table)->updateOrInsert($where, $attributes);
        }
    }

    private function id(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-syria-real:'.$key), 0, 32);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
