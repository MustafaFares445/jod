<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class SeedDataPresentationSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const TEXT_COLUMNS = [
        'categories' => ['name', 'description'],
        'organizations' => ['name', 'description', 'short_address', 'location'],
        'organization_roles' => ['name', 'description'],
        'organization_staff' => ['name'],
        'users' => ['name', 'bio', 'location', 'address', 'city', 'governorate'],
        'campaigns' => ['title', 'summary', 'content', 'closed_reason', 'rejection_reason', 'location'],
        'posts' => ['title', 'summary', 'content', 'urgency_reason', 'block_reason', 'location'],
        'articles' => ['title', 'excerpt', 'content', 'author_name'],
        'reports' => ['title', 'description'],
        'badges' => ['name', 'description', 'criteria'],
        'groups' => ['name', 'description', 'category', 'location', 'purpose', 'rejection_reason', 'suspension_reason'],
        'group_posts' => ['body'],
        'group_comments' => ['body'],
        'notifications' => ['title', 'body', 'recipient_label', 'reference_label'],
        'media' => ['description', 'original_name'],
        'capabilities' => ['name', 'description'],
    ];

    /** @var list<string> */
    private const FORBIDDEN_MARKERS = [
        'بيانات jod',
        'هذا سجل تجريبي',
        'تجريبي',
        'تجريبية',
        'نوع التوثيق',
        'المصدر المرجعي',
        'demo data',
        'seed data',
        'test data',
        'synthetic',
        'provenance',
        'documented',
        'program_based',
        '@demo.',
        'jod-demo',
        ' demo ',
    ];

    public function run(): void
    {
        $this->normalizeOrganizationOperationalFields();
        $this->normalizeInternalAccounts();
        $this->normalizeMediaStoragePaths();
        $this->replaceArticles();
        $this->replaceReports();
        $this->sanitizeTextColumns();
        $this->sanitizeJsonColumns();
        $this->assertNoPresentationMarkersRemain();
    }

    private function normalizeOrganizationOperationalFields(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        DB::table('organizations')->orderBy('id')->chunk(100, function ($organizations): void {
            foreach ($organizations as $organization) {
                $id = (string) $organization->id;
                $suffix = strtoupper(substr(hash('sha256', $id), 0, 12));
                $attributes = [];

                if (Schema::hasColumn('organizations', 'email') && str_contains(strtolower((string) ($organization->email ?? '')), '@demo.jod.local')) {
                    $attributes['email'] = 'organization-'.strtolower($suffix).'@jod.local';
                }

                if (Schema::hasColumn('organizations', 'organization_number') && str_contains(strtoupper((string) ($organization->organization_number ?? '')), 'DEMO')) {
                    $attributes['organization_number'] = 'JOD-ORG-'.$suffix;
                }

                foreach (['bank_account_number', 'bank_name', 'iban'] as $column) {
                    if (! Schema::hasColumn('organizations', $column)) {
                        continue;
                    }

                    $value = (string) ($organization->{$column} ?? '');
                    if ($value !== '' && preg_match('/demo|jod-demo/i', $value) === 1) {
                        $attributes[$column] = null;
                    }
                }

                if ($attributes !== []) {
                    if (Schema::hasColumn('organizations', 'updated_at')) {
                        $attributes['updated_at'] = now();
                    }
                    DB::table('organizations')->where('id', $id)->update($attributes);
                }
            }
        });
    }

    private function normalizeInternalAccounts(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            DB::table('users')->where('email', 'like', '%@demo.jod.local')->orderBy('id')->chunk(100, function ($users): void {
                foreach ($users as $user) {
                    $suffix = substr(hash('sha256', (string) $user->id), 0, 16);
                    DB::table('users')->where('id', $user->id)->update([
                        'email' => 'account-'.$suffix.'@jod.local',
                        'updated_at' => now(),
                    ]);
                }
            });
        }

        if (Schema::hasTable('organization_staff') && Schema::hasColumn('organization_staff', 'email')) {
            DB::table('organization_staff')->where('email', 'like', '%@demo.jod.local')->orderBy('user_id')->chunk(100, function ($staff): void {
                foreach ($staff as $member) {
                    $suffix = substr(hash('sha256', (string) $member->user_id), 0, 16);
                    $attributes = ['email' => 'account-'.$suffix.'@jod.local'];
                    if (Schema::hasColumn('organization_staff', 'updated_at')) {
                        $attributes['updated_at'] = now();
                    }
                    DB::table('organization_staff')
                        ->where('organization_id', $member->organization_id)
                        ->where('user_id', $member->user_id)
                        ->update($attributes);
                }
            });
        }

        if (Schema::hasTable('organization_roles') && Schema::hasColumn('organization_roles', 'description')) {
            DB::table('organization_roles')
                ->whereRaw('LOWER(description) LIKE ?', ['%demo%'])
                ->update([
                    'description' => 'صلاحيات إدارة الجهة',
                    'updated_at' => now(),
                ]);
        }
    }

    private function normalizeMediaStoragePaths(): void
    {
        if (! Schema::hasTable('media') || ! Schema::hasColumn('media', 'path')) {
            return;
        }

        $disk = Storage::disk('public');
        $rows = DB::table('media')->where('path', 'like', 'demo/syria/%')->get(['id', 'path']);

        foreach ($rows as $row) {
            $oldPath = (string) $row->path;
            $newPath = 'content/syria/'.substr($oldPath, strlen('demo/syria/'));

            if ($disk->exists($oldPath) && ! $disk->exists($newPath)) {
                $read = $disk->readStream($oldPath);
                if (is_resource($read)) {
                    $disk->writeStream($newPath, $read);
                    fclose($read);
                }
            }

            DB::table('media')->where('id', $row->id)->update([
                'path' => $newPath,
                'updated_at' => now(),
            ]);
        }

        if ($rows->isNotEmpty()) {
            $disk->deleteDirectory('demo/syria');
        }
    }

    private function replaceArticles(): void
    {
        if (! Schema::hasTable('articles')) {
            return;
        }

        if (Schema::hasTable('media') && Schema::hasColumn('media', 'model_type')) {
            DB::table('media')->where('model_type', 'article')->delete();
        }

        DB::table('articles')->delete();

        $authorId = Schema::hasTable('users')
            ? DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id')
            : null;

        $rows = [
            [
                'كيف تختار الجهة المناسبة لتقديم المساعدة؟',
                'خطوات عملية تساعدك على توجيه المساعدة إلى الجهة الأقرب للاحتياج والأقدر على المتابعة.',
                'قبل تقديم المساعدة، حدد نوع الاحتياج وموقعه والوقت المناسب للتدخل. راجع وصف الحملة أو الطلب، وتأكد من أن طريقة التواصل واضحة، ثم اختر الجهة التي تعمل في المجال والموقع نفسه. عند تقديم دعم عيني، اتفق مسبقاً على نوع المواد والكميات وطريقة التسليم لتجنب الهدر وتكرار المساعدة.',
                'guide-help-selection',
            ],
            [
                'التطوع المسؤول وحماية خصوصية المستفيدين',
                'مبادئ بسيطة تجعل العمل التطوعي أكثر أماناً واحتراماً لخصوصية الأشخاص الذين يتلقون الدعم.',
                'يحافظ المتطوع المسؤول على سرية بيانات المستفيدين ولا ينشر أسماءهم أو أرقامهم أو صورهم دون موافقة واضحة. كما يلتزم بقنوات التواصل المعتمدة، ويتجنب الوعود التي لا يستطيع تنفيذها، ويوثق ما يحتاجه الفريق للتنسيق من دون جمع معلومات شخصية غير ضرورية.',
                'responsible-volunteering-privacy',
            ],
            [
                'المياه النظيفة والصحة العامة',
                'الوصول إلى مياه آمنة وخدمات إصحاح مناسبة عنصر أساسي في الوقاية من الأمراض وحماية الأسر.',
                'تزداد أهمية المياه النظيفة بعد الكوارث والنزوح وعند تضرر الشبكات. تشمل الاستجابة الفعالة التأكد من مصدر المياه، ونظافة خزانات التخزين، والتخلص الآمن من المياه الملوثة، وتوفير مواد النظافة الأساسية، ونشر إرشادات واضحة حول الاستخدام والتخزين المنزلي.',
                'clean-water-public-health',
            ],
            [
                'دعم الطلاب أثناء الأزمات',
                'أشكال المساعدة التي يمكن أن تحافظ على استمرارية التعليم عندما تتأثر الأسرة أو المدرسة بظروف صعبة.',
                'قد يحتاج الطالب إلى كتب وقرطاسية أو مواصلات أو جهاز يساعده على التعلم أو مساحة هادئة للدراسة. كما يمكن أن تكون جلسات الإرشاد والمنح والدورات القصيرة والدعم النفسي وسائل مهمة للحفاظ على الاستمرارية التعليمية ومنع الانقطاع عن الدراسة.',
                'student-support-during-crises',
            ],
            [
                'السلامة عند الاستجابة للحالات الطارئة',
                'إرشادات تساعد المتطوعين والأفراد على تقديم المساعدة من دون تعريض أنفسهم أو الآخرين لمخاطر إضافية.',
                'في الحالات الطارئة يجب إعطاء الأولوية للسلامة الشخصية واتباع تعليمات الجهات المختصة وعدم دخول المواقع الخطرة من دون تدريب أو تجهيز مناسب. يفضل مشاركة المعلومات المؤكدة فقط، وترك مهام الإسعاف والإنقاذ التخصصية للفرق المؤهلة، وتنظيم التبرعات بما يتوافق مع الاحتياجات الفعلية.',
                'emergency-response-safety',
            ],
            [
                'كيف تدعم الأسرة في مرحلة العودة والتعافي؟',
                'نظرة عملية إلى الاحتياجات التي تظهر عند عودة الأسر إلى مناطقها بعد فترات النزوح أو الضرر.',
                'لا تقتصر العودة على السكن فقط، بل تشمل الوصول إلى المياه والكهرباء والتعليم والرعاية الصحية وفرص العمل. يساعد تقييم الاحتياج على ترتيب الأولويات: السلامة والسكن أولاً، ثم الخدمات الأساسية، وبعدها دعم مصادر الدخل والتعليم والاندماج المجتمعي.',
                'family-return-recovery',
            ],
            [
                'المساعدة العينية: كيف نحدد ما يحتاجه الناس فعلاً؟',
                'التنسيق المسبق وتحديد الأولويات يجعل التبرعات العينية أكثر فائدة وأسهل في التخزين والتوزيع.',
                'قبل جمع المواد العينية، من الأفضل معرفة الكميات والأنواع المطلوبة وحالة التخزين والنقل. المواد الموحدة وسهلة الفرز تقلل وقت التجهيز، كما أن تحديد المقاسات والمواصفات في الملابس والأجهزة والمستلزمات الطبية يمنع وصول مواد غير مناسبة للمستفيدين.',
                'effective-in-kind-assistance',
            ],
            [
                'دور المبادرات المحلية في إعادة تأهيل الخدمات',
                'كيف تسهم الفرق المحلية والمجتمع في تحديد الأولويات وتنفيذ تدخلات صغيرة ذات أثر مباشر.',
                'تملك المبادرات المحلية معرفة دقيقة بالمشكلات اليومية في الأحياء والبلدات. ويمكنها دعم أعمال مثل النظافة والتشجير وصيانة المرافق والمساحات العامة وتنسيق الجهود مع الجهات المختصة. نجاح هذه المبادرات يعتمد على وضوح الهدف وتوزيع الأدوار ومتابعة النتائج بعد انتهاء النشاط.',
                'local-initiatives-service-recovery',
            ],
        ];

        foreach ($rows as $index => [$title, $excerpt, $content, $slug]) {
            $id = $this->id('article:'.$slug);
            DB::table('articles')->insert($this->columns('articles', [
                'id' => $id,
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'content' => $content,
                'status' => 'published',
                'published_at' => now()->subDays(3 + ($index * 4)),
                'author_name' => 'فريق المحتوى',
                'author_id' => $authorId,
                'created_at' => now()->subDays(4 + ($index * 4)),
                'updated_at' => now()->subDays($index % 3),
            ]));
        }
    }

    private function replaceReports(): void
    {
        if (! Schema::hasTable('reports') || ! Schema::hasTable('posts')) {
            return;
        }

        DB::table('reports')->delete();

        $postIds = DB::table('posts')
            ->where('type', 'help_request')
            ->where('status', 'published')
            ->orderBy('id')
            ->limit(8)
            ->pluck('id')
            ->values();

        if ($postIds->isEmpty()) {
            return;
        }

        $reporters = Schema::hasTable('users')
            ? DB::table('users')->whereNull('organization_id')->where(function ($query): void {
                $query->whereNull('user_type')->orWhere('user_type', '!=', 'admin');
            })->orderBy('id')->limit(8)->pluck('id')->values()
            : collect();
        $assigneeId = Schema::hasTable('users')
            ? DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id')
            : null;

        $rows = [
            ['مراجعة بيانات تواصل شخصية', 'يظهر في محتوى الطلب ما يستدعي التأكد من عدم عرض بيانات تواصل شخصية بشكل علني.', 'inappropriate', 'in_progress', 'medium'],
            ['التحقق من ملاءمة التصنيف', 'يرجى مراجعة التصنيف المختار والتأكد من أنه يطابق نوع المساعدة المطلوبة في المنشور.', 'other', 'new', 'low'],
            ['مراجعة صورة مرفقة', 'الصورة المرفقة تحتاج مراجعة للتأكد من أنها مناسبة للمحتوى ولا تكشف بيانات حساسة.', 'inappropriate', 'in_progress', 'medium'],
            ['احتمال وجود محتوى مكرر', 'يوجد تشابه واضح مع طلب آخر منشور ويحتاج الأمر إلى مراجعة قبل إبقاء السجلين نشطين.', 'spam', 'closed', 'low'],
            ['تحديث حالة الطلب', 'يبدو أن حالة الطلب تغيرت ويحتاج المنشور إلى مراجعة الحالة الحالية قبل ظهوره ضمن الطلبات المفتوحة.', 'other', 'new', 'medium'],
            ['التحقق من الموقع الجغرافي', 'الموقع المذكور في النص يحتاج مطابقة مع المحافظة المحددة في بيانات المنشور.', 'other', 'in_progress', 'low'],
            ['مراجعة وصف الاحتياج', 'الوصف الحالي يحتاج مزيداً من الوضوح حول نوع المساعدة المطلوبة لتسهيل مطابقة عروض المساعدة.', 'other', 'new', 'low'],
            ['مراجعة طلب لم يعد نشطاً', 'وردت إشارة إلى أن الحاجة قد تكون انتهت ويجب التأكد قبل إبقاء الطلب متاحاً للمساعدة.', 'other', 'closed', 'medium'],
        ];

        foreach ($rows as $index => [$title, $description, $category, $status, $severity]) {
            $postId = (string) $postIds[$index % $postIds->count()];
            $reporterId = $reporters->isNotEmpty() ? $reporters[$index % $reporters->count()] : null;
            $createdAt = now()->subDays(2 + $index);
            $timeline = [
                ['note' => 'تم استلام البلاغ وإضافته إلى قائمة المراجعة.', 'at' => $createdAt->toIso8601String()],
            ];
            if ($status !== 'new') {
                $timeline[] = ['note' => 'بدأت مراجعة المحتوى والبيانات المرتبطة به.', 'at' => $createdAt->copy()->addHours(6)->toIso8601String()];
            }
            if ($status === 'closed') {
                $timeline[] = ['note' => 'اكتملت المراجعة وتم تحديث حالة البلاغ.', 'at' => $createdAt->copy()->addDay()->toIso8601String()];
            }

            DB::table('reports')->insert($this->columns('reports', [
                'id' => $this->id('report:'.$index.':'.$postId),
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'status' => $status,
                'severity' => $severity,
                'entity_type' => 'post',
                'entity_id' => $postId,
                'organization_id' => null,
                'reporter_id' => $reporterId,
                'assignee_id' => $status === 'new' ? null : $assigneeId,
                'evidence' => json_encode([], JSON_THROW_ON_ERROR),
                'timeline' => json_encode($timeline, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'closed_at' => $status === 'closed' ? $createdAt->copy()->addDay() : null,
                'deleted_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $status === 'new' ? $createdAt : $createdAt->copy()->addHours(6),
            ]));
        }
    }

    private function sanitizeTextColumns(): void
    {
        foreach (self::TEXT_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $available = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn($table, $column)));
            if ($available === []) {
                continue;
            }

            $select = array_values(array_unique(array_merge(['id'], $available)));
            if (! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::table($table)->select($select)->orderBy('id')->chunk(100, function ($rows) use ($table, $available): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($available as $column) {
                        $value = $row->{$column} ?? null;
                        if (! is_string($value) || $value === '') {
                            continue;
                        }
                        $clean = $this->cleanText($value);
                        if ($clean !== $value) {
                            $updates[$column] = $clean !== '' ? $clean : $this->fallbackText($table, $column);
                        }
                    }

                    if ($updates !== []) {
                        if (Schema::hasColumn($table, 'updated_at')) {
                            $updates['updated_at'] = now();
                        }
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }

    private function sanitizeJsonColumns(): void
    {
        $jsonColumns = [
            'groups' => ['rules'],
            'reports' => ['timeline'],
        ];

        foreach ($jsonColumns as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)->select(['id', $column])->orderBy('id')->chunk(100, function ($rows) use ($table, $column): void {
                    foreach ($rows as $row) {
                        $raw = $row->{$column};
                        if (! is_string($raw) || $raw === '') {
                            continue;
                        }
                        $decoded = json_decode($raw, true);
                        if (! is_array($decoded)) {
                            continue;
                        }
                        $cleaned = $this->cleanNested($decoded);
                        DB::table($table)->where('id', $row->id)->update([
                            $column => json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ...(Schema::hasColumn($table, 'updated_at') ? ['updated_at' => now()] : []),
                        ]);
                    }
                });
            }
        }
    }

    private function cleanNested(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->cleanText($value);
        }
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->cleanNested($item);
        }

        return $value;
    }

    private function cleanText(string $text): string
    {
        $patterns = [
            '/هذا المنشور جزء من بيانات JOD[^.]*\.?/ui',
            '/هذا سجل تجريبي[^.]*\.?/ui',
            '/بيانات البنك وحساب الإدارة تجريبية فقط\.?/ui',
            '/نوع التوثيق\s*:\s*[^.\n]+\.?/ui',
            '/المصدر المرجعي\s*:\s*\S+\.?/ui',
            '/اسم الحملة أو المشروع موثق في المصدر المرجعي\.?/ui',
            '/المصدر يوثق التحضير أو الإطلاق ولا يثبت حصيلة نهائية\.?/ui',
            '/سجل JOD مبني على برنامج أو قطاع عمل موثق للجهة\.?/ui',
            '/لا تعامل تلقائياً كمبلغ محصل داخل JOD\.?/ui',
            '/حالة تجريبية حرجة لاختبار المراجعة والترتيب\.?/ui',
            '/\bJOD\s+demo\s+owner\s+role\b/ui',
            '/\b(?:demo data|seed data|test data|synthetic|provenance|documented|program_based)\b/ui',
            '/\bتجريبي(?:ة|اً)?\b/u',
            '/\bبيانات\s+JOD\b/ui',
        ];

        $clean = preg_replace($patterns, '', $text) ?? $text;
        $clean = preg_replace('/\s+([،,.؛:])/u', '$1', $clean) ?? $clean;
        $clean = preg_replace('/\s{2,}/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/(?:\.\s*){2,}/u', '. ', $clean) ?? $clean;

        return trim($clean, " \t\n\r\0\x0B.-؛،:");
    }

    private function fallbackText(string $table, string $column): string
    {
        return match ([$table, $column]) {
            ['organizations', 'description'] => 'جهة تعمل على تقديم خدمات ومبادرات إنسانية وتنموية للمجتمع المحلي.',
            ['campaigns', 'summary'] => 'مبادرة تركز على تلبية الاحتياجات ذات الأولوية ودعم المجتمع المحلي.',
            ['campaigns', 'content'] => 'تعمل المبادرة على تنفيذ أنشطة وخدمات موجهة إلى الاحتياجات الأكثر أولوية ومتابعة أثرها على المستفيدين.',
            ['posts', 'summary'] => 'تفاصيل حول النشاط والخدمات المرتبطة به.',
            ['posts', 'content'] => 'معلومات حول النشاط وطريقة الاستفادة أو المشاركة بحسب طبيعة المنشور.',
            ['media', 'description'] => 'وسائط مرتبطة بالمحتوى.',
            ['organization_roles', 'description'] => 'صلاحيات إدارة الجهة',
            default => 'معلومات محدثة',
        };
    }

    private function assertNoPresentationMarkersRemain(): void
    {
        foreach (self::TEXT_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)->select(['id', $column])->orderBy('id')->chunk(200, function ($rows) use ($table, $column): void {
                    foreach ($rows as $row) {
                        $value = $row->{$column} ?? null;
                        if (! is_string($value) || $value === '') {
                            continue;
                        }
                        if ($this->containsForbiddenMarker($value)) {
                            throw new \RuntimeException("Forbidden presentation marker remains in {$table}.{$column} for {$row->id}");
                        }
                    }
                });
            }
        }

        $identifierChecks = [
            ['organizations', 'email'],
            ['organizations', 'organization_number'],
            ['organizations', 'bank_account_number'],
            ['organizations', 'bank_name'],
            ['organizations', 'iban'],
            ['users', 'email'],
            ['organization_staff', 'email'],
            ['media', 'path'],
        ];

        foreach ($identifierChecks as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $rows = DB::table($table)->whereNotNull($column)->get([$column]);
            foreach ($rows as $row) {
                $value = (string) $row->{$column};
                if (preg_match('/\bdemo\b|jod-demo|@demo\.|demo\/syria\//i', $value) === 1) {
                    throw new \RuntimeException("Forbidden operational marker remains in {$table}.{$column}");
                }
            }
        }
    }

    private function containsForbiddenMarker(string $value): bool
    {
        $normalized = mb_strtolower($value);
        foreach (self::FORBIDDEN_MARKERS as $marker) {
            if (str_contains($normalized, mb_strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    private function columns(string $table, array $attributes): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        return array_intersect_key($attributes, $columns);
    }

    private function id(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-content:'.$key), 0, 32);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
