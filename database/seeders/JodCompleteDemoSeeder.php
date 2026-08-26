<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Database\Seeders\Permissions\PermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class JodCompleteDemoSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private array $columns = [];

    /** @var array<string, int|string> */
    private array $logicalIds = [];

    public function run(): void
    {
        $this->call(PermissionsSeeder::class);
        $data = $this->data();

        DB::transaction(function () use ($data): void {
            $this->seedOrganizations($data['organizations']);
            $this->seedUsers($data);
            $this->seedOrganizationRolesAndMemberships($data);
            $this->seedSimpleEntities($data);
            $this->seedLikesAndSaves($data);
        });

        $this->seedMedia($data['image_media'], false);
        $this->seedMedia($data['video_media'], true);
        $this->recalculateDerivedFields();
        $this->grantAdminPermissions($data['admins']);
    }

    private function data(): array
    {
        $encoded = '';
        foreach (range(1, 4) as $part) {
            $path = database_path(sprintf('data/jod_complete_demo_%02d.b64', $part));
            $encoded .= trim((string) file_get_contents($path));
        }

        $compressed = base64_decode($encoded, true);
        $json = $compressed === false ? false : gzdecode($compressed);

        if ($json === false) {
            throw new \RuntimeException('Unable to decode JOD complete demo seed dataset.');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function seedOrganizations(array $rows): void
    {
        foreach ($rows as $row) {
            $key = $row['key'];
            $attributes = $this->snakeRow($row, ['key']);
            $attributes['id'] = $this->id($key);

            if (isset($attributes['social_media'])) {
                $attributes['social_media'] = json_encode($attributes['social_media'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $this->upsert('organizations', ['id' => $attributes['id']], $attributes);
        }
    }

    private function seedUsers(array $data): void
    {
        $groups = ['admins', 'organization_owners', 'organization_staff', 'users'];

        foreach ($groups as $group) {
            foreach ($data[$group] as $row) {
                $attributes = $this->snakeRow($row, ['key', 'organizationKey', 'passwordPlain', 'emailVerified', 'membershipStatus', 'roleTemplate', 'permissionSource']);
                $attributes['id'] = $this->id($row['key']);
                $attributes['organization_id'] = isset($row['organizationKey']) && $row['organizationKey'] !== null
                    ? $this->id($row['organizationKey'])
                    : null;
                $attributes['password'] = Hash::make($row['passwordPlain']);
                $attributes['email_verified_at'] = ($row['emailVerified'] ?? false) ? now() : null;

                $this->upsert('users', ['id' => $attributes['id']], $attributes);
            }
        }
    }

    private function seedOrganizationRolesAndMemberships(array $data): void
    {
        $catalog = PermissionCatalog::names();
        $templates = collect($data['role_templates'])->keyBy(fn (array $row) => Str::after($row['key'], 'role_'));

        foreach ($data['organizations'] as $organization) {
            foreach ($templates as $templateKey => $template) {
                $roleId = $this->id('role:'.$organization['key'].':'.$templateKey);
                $this->upsert('organization_roles', ['id' => $roleId], [
                    'id' => $roleId,
                    'organization_id' => $this->id($organization['key']),
                    'name' => $template['name'],
                    'description' => $template['permissions'],
                    'permissions' => json_encode($this->permissionsForTemplate((string) $templateKey, $catalog), JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'is_system' => $templateKey === 'owner',
                    'members_count' => 0,
                ]);
            }
        }

        foreach (array_merge($data['organization_owners'], $data['organization_staff']) as $row) {
            $organizationId = $this->id($row['organizationKey']);
            $userId = $this->id($row['key']);
            $roleId = $this->id('role:'.$row['organizationKey'].':'.$row['roleTemplate']);

            $this->upsert('organization_staff', ['user_id' => $userId, 'organization_id' => $organizationId], [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'organization_role_id' => $roleId,
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'] ?? null,
                'status' => $row['membershipStatus'] ?? 'active',
                'invited_at' => now(),
                'accepted_at' => ($row['membershipStatus'] ?? 'active') === 'active' ? now() : null,
                'invitation_token' => null,
            ]);
        }

        if (Schema::hasColumn('organization_roles', 'members_count')) {
            DB::table('organization_roles')->get(['id'])->each(function ($role): void {
                DB::table('organization_roles')->where('id', $role->id)->update([
                    'members_count' => DB::table('organization_staff')->where('organization_role_id', $role->id)->count(),
                ]);
            });
        }
    }

    private function permissionsForTemplate(string $template, array $catalog): array
    {
        if ($template === 'owner') {
            return $catalog;
        }

        $needles = match ($template) {
            'manager' => ['organization', 'dashboard', 'staff', 'campaign', 'post', 'article', 'media', 'donation', 'applicant'],
            'campaign_manager' => ['campaign', 'applicant', 'post'],
            'content_manager' => ['post', 'article', 'media', 'video'],
            'donations_manager' => ['donation', 'donor', 'campaign'],
            default => [],
        };

        return array_values(array_filter($catalog, static function (string $permission) use ($needles): bool {
            foreach ($needles as $needle) {
                if (str_contains($permission, $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function seedSimpleEntities(array $data): void
    {
        foreach ($data['categories'] as $row) {
            $this->upsert('categories', ['id' => $this->id($row['key'])], [
                'id' => $this->id($row['key']),
                'name' => $row['name'],
                'description' => $row['description'],
                'status' => $row['status'],
                'usage_count' => 0,
            ]);
        }

        foreach ($data['campaigns'] as $row) {
            $attrs = $this->snakeRow($row, ['key', 'organizationKey', 'creatorKey', 'categoryKey', 'reviewedByKey']);
            $attrs += [
                'id' => $this->id($row['key']),
                'organization_id' => $this->id($row['organizationKey']),
                'creator_id' => $this->id($row['creatorKey']),
                'category_id' => $this->id($row['categoryKey']),
            ];

            $attrs['status'] = match ($attrs['status'] ?? 'draft') {
                'pending', 'approved' => 'active',
                'rejected' => 'draft',
                default => $attrs['status'] ?? 'draft',
            };
            $attrs['reviewed_by'] = null;
            $attrs['rejection_reason'] = null;

            $this->upsert('campaigns', ['id' => $attrs['id']], $attrs);
        }

        foreach ($data['posts'] as $row) {
            $attrs = $this->snakeRow($row, ['key', 'organizationKey', 'campaignKey', 'categoryKey', 'authorKey', 'reviewedByKey']);
            $attrs += [
                'id' => $this->id($row['key']),
                'organization_id' => $row['organizationKey'] ? $this->id($row['organizationKey']) : null,
                'campaign_id' => $row['campaignKey'] ? $this->id($row['campaignKey']) : null,
                'category_id' => $this->id($row['categoryKey']),
                'author_id' => $this->id($row['authorKey']),
            ];

            if ($attrs['organization_id'] !== null) {
                $attrs['status'] = match ($attrs['status'] ?? 'draft') {
                    'pending', 'approved' => 'published',
                    'rejected' => 'draft',
                    default => $attrs['status'] ?? 'draft',
                };
                $attrs['reviewed_by'] = null;
                $attrs['reviewed_at'] = null;
                $attrs['approved_by'] = null;
                $attrs['approved_at'] = null;
                $attrs['rejected_by'] = null;
                $attrs['rejected_at'] = null;
                $attrs['rejection_reason'] = null;

                if ($attrs['status'] === 'published' && empty($attrs['published_at'])) {
                    $attrs['published_at'] = now();
                }
            } else {
                $attrs['reviewed_by'] = $row['reviewedByKey'] ? $this->id($row['reviewedByKey']) : null;
            }

            $this->upsert('posts', ['id' => $attrs['id']], $attrs);
        }

        foreach ($data['articles'] as $row) {
            $attrs = $this->snakeRow($row, ['key', 'authorKey', 'hasVideo']);
            $attrs += [
                'id' => $this->id($row['key']),
                'author_id' => $this->id($row['authorKey']),
            ];
            $attrs['slug'] = $this->seedSlug('articles', (string) $attrs['title'], (string) $attrs['id']);

            $this->upsert('articles', ['id' => $attrs['id']], $attrs);
        }

        foreach ($data['help_offers'] as $row) {
            $attrs = $this->snakeRow($row, ['key', 'postKey', 'helperUserKey', 'postOwnerKey']);
            $attrs += [
                'id' => $this->id($row['key']),
                'post_id' => $this->id($row['postKey']),
                'helper_user_id' => $this->id($row['helperUserKey']),
                'post_owner_id' => $this->id($row['postOwnerKey']),
            ];
            $this->upsert('help_offers', ['id' => $attrs['id']], $attrs);
        }

        foreach ($data['donations'] as $row) {
            $attrs = $this->snakeRow($row, ['key', 'organizationKey', 'campaignKey', 'createdByUserKey', 'confirmedByUserKey']);
            $attrs += [
                'organization_id' => $this->id($row['organizationKey']),
                'campaign_id' => $this->id($row['campaignKey']),
                'created_by' => $this->id($row['createdByUserKey']),
                'confirmed_by' => $row['confirmedByUserKey'] ? $this->id($row['confirmedByUserKey']) : null,
            ];
            $this->upsert('donations', ['campaign_ref' => $row['campaignRef']], $attrs);
            $this->logicalIds[$row['key']] = (int) DB::table('donations')->where('campaign_ref', $row['campaignRef'])->value('id');
        }

        foreach ($data['campaign_applications'] as $row) {
            $attrs = $this->snakeRow($row, ['key', 'organizationKey', 'campaignKey', 'createdByUserKey', 'assignedToUserKey']);
            $attrs += [
                'organization_id' => $this->id($row['organizationKey']),
                'campaign_id' => $this->id($row['campaignKey']),
                'created_by' => $this->id($row['createdByUserKey']),
                'assigned_to' => $row['assignedToUserKey'] ? $this->id($row['assignedToUserKey']) : null,
            ];
            $this->upsert('campaign_applications', ['campaign_id' => $attrs['campaign_id'], 'created_by' => $attrs['created_by']], $attrs);
            $this->logicalIds[$row['key']] = (int) DB::table('campaign_applications')
                ->where('campaign_id', $attrs['campaign_id'])
                ->where('created_by', $attrs['created_by'])
                ->value('id');
        }

        foreach ($data['notifications'] as $row) {
            $attrs = $this->snakeRow($row, ['key', 'recipientUserKey', 'creatorUserKey', 'referenceKey']);
            $attrs += [
                'id' => $this->id($row['key']),
                'recipient_id' => $this->id($row['recipientUserKey']),
                'creator_id' => $this->id($row['creatorUserKey']),
                'reference_label' => $row['referenceKey'],
                'reference_path' => $this->referencePath($row['referenceKey']),
            ];
            $attrs['recipient_scope'] = $this->normalizeNotificationRecipientScope($attrs['recipient_scope'] ?? null);
            $this->upsert('notifications', ['id' => $attrs['id']], $attrs);
        }

        foreach ($data['reports'] as $row) {
            $attrs = $this->snakeRow($row, ['key', 'reporterUserKey', 'assigneeUserKey', 'entityKey']);
            $attrs += [
                'id' => $this->id($row['key']),
                'reporter_id' => $this->id($row['reporterUserKey']),
                'assignee_id' => $row['assigneeUserKey'] ? $this->id($row['assigneeUserKey']) : null,
                'entity_id' => $this->id($row['entityKey']),
            ];
            $attrs['status'] = ($attrs['status'] ?? 'new') === 'waiting_response'
                ? 'in_progress'
                : ($attrs['status'] ?? 'new');
            $attrs['evidence'] = json_encode([], JSON_THROW_ON_ERROR);
            $attrs['timeline'] = json_encode([['note' => $row['timelineNote']]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $attrs['category'] = match ($attrs['category']) {
                'abusive' => 'abuse',
                'fraud', 'impersonation' => 'fraud',
                'misleading' => 'other',
                default => $attrs['category'],
            };
            unset($attrs['timeline_note']);
            $this->upsert('reports', ['id' => $attrs['id']], $attrs);
        }

        foreach ($data['badges'] as $row) {
            $attrs = $this->snakeRow($row, ['key']);
            $attrs['id'] = $this->id($row['key']);
            $this->upsert('badges', ['id' => $attrs['id']], $attrs);
        }
    }

    private function seedMedia(array $rows, bool $video): void
    {
        foreach ($rows as $row) {
            if ($row['entityType'] === 'post' && $video && ! $this->postAllowsVideo($row['entityKey'])) {
                continue;
            }

            $source = $row['sourceUrl'];
            $extension = $video ? 'mp4' : 'jpg';
            $path = 'demo/'.$row['entityType'].'/'.$row['entityKey'].'/'.$row['key'].'.'.$extension;
            $size = 0;
            $mime = $row['mimeType'] ?? $row['mimeTypeExpected'] ?? ($video ? 'video/mp4' : 'image/jpeg');

            try {
                if (! Storage::disk('public')->exists($path)) {
                    $response = Http::timeout(30)->retry(2, 250)->get($source);
                    if ($response->successful()) {
                        Storage::disk('public')->put($path, $response->body());
                    }
                }

                if (Storage::disk('public')->exists($path)) {
                    $size = Storage::disk('public')->size($path);
                }
            } catch (Throwable $e) {
                $this->command?->warn('Demo media download failed for '.$row['key'].': '.$e->getMessage());
                continue;
            }

            $modelId = $this->id($row['entityKey']);
            $this->upsert('media', ['id' => $this->id($row['key'])], [
                'id' => $this->id($row['key']),
                'model_type' => $row['entityType'],
                'model_id' => $modelId,
                'post_id' => $row['entityType'] === 'post' ? $modelId : null,
                'prop' => $row['prop'],
                'disk' => 'public',
                'path' => $path,
                'original_name' => basename($path),
                'description' => $row['altText'] ?? $row['semanticLabel'] ?? null,
                'mime_type' => $mime,
                'size' => $size,
                'position' => $row['position'],
            ]);
        }
    }

    private function postAllowsVideo(string $postKey): bool
    {
        return in_array($postKey, ['post_laptop_repair_offer', 'post_donate_heart_campaign', 'post_update_heart_60'], true);
    }

    private function seedLikesAndSaves(array $data): void
    {
        foreach ($data['post_likes'] as $row) {
            $this->upsert('post_likes', ['user_id' => $this->id($row['userKey']), 'post_id' => $this->id($row['postKey'])], [
                'id' => $this->id($row['key']),
                'user_id' => $this->id($row['userKey']),
                'post_id' => $this->id($row['postKey']),
            ]);
        }

        foreach ($data['saved_posts'] as $row) {
            $this->upsert('saved_posts', ['user_id' => $this->id($row['userKey']), 'post_id' => $this->id($row['postKey'])], [
                'id' => $this->id($row['key']),
                'user_id' => $this->id($row['userKey']),
                'post_id' => $this->id($row['postKey']),
            ]);
        }
    }

    private function recalculateDerivedFields(): void
    {
        foreach (DB::table('categories')->pluck('id') as $categoryId) {
            $usage = DB::table('posts')->where('category_id', $categoryId)->count()
                + DB::table('campaigns')->where('category_id', $categoryId)->count();
            DB::table('categories')->where('id', $categoryId)->update(['usage_count' => $usage]);
        }

        foreach (DB::table('campaigns')->pluck('id') as $campaignId) {
            $completed = DB::table('donations')->where('campaign_id', $campaignId)->where('status', 'completed')->get(['amount_or_type']);
            $raised = $completed->sum(fn ($row) => is_numeric($row->amount_or_type) ? (float) $row->amount_or_type : 0.0);
            $applicants = DB::table('campaign_applications')
                ->where('campaign_id', $campaignId)
                ->whereNotIn('applicant_status', ['rejected', 'withdrawn'])
                ->count();

            DB::table('campaigns')->where('id', $campaignId)->update([
                'raised_amount' => $raised,
                'donors_count' => $completed->count(),
                'applicants_count' => $applicants,
            ]);
        }
    }

    private function grantAdminPermissions(array $admins): void
    {
        foreach ($admins as $row) {
            User::query()->find($this->id($row['key']))?->syncPermissions(PermissionCatalog::names());
        }
    }

    private function referencePath(string $key): string
    {
        $id = $key === 'system' ? null : ($this->logicalIds[$key] ?? $this->id($key));

        return match (true) {
            str_starts_with($key, 'post_') => '/api/mobile/posts/'.$id,
            str_starts_with($key, 'campaign_') => '/api/mobile/campaigns/'.$id,
            str_starts_with($key, 'don_') => '/api/mobile/me/donations/'.$id,
            str_starts_with($key, 'app_') => '/api/mobile/me/campaign-applications/'.$id,
            str_starts_with($key, 'offer_') => '/api/mobile/me/help-offers/'.$id,
            str_starts_with($key, 'org_') => '/api/mobile/organizations/'.$id,
            default => '/api/mobile',
        };
    }

    private function normalizeNotificationRecipientScope(mixed $scope): string
    {
        return match ($scope) {
            'user' => 'users',
            'all', 'users', 'organizations' => $scope,
            default => throw new \UnexpectedValueException('Unsupported notification recipient scope in demo dataset: '.(string) $scope),
        };
    }

    private function seedSlug(string $table, string $source, string $id): string
    {
        $slug = Str::slug($source);

        if ($slug === '') {
            $slug = 'seed-'.substr(str_replace('-', '', $id), 0, 12);
        }

        if (
            Schema::hasTable($table)
            && DB::table($table)->where('slug', $slug)->where('id', '!=', $id)->exists()
        ) {
            $slug .= '-'.substr(str_replace('-', '', $id), 0, 8);
        }

        return $slug;
    }

    private function upsert(string $table, array $where, array $attributes): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = $this->columns[$table] ??= Schema::getColumnListing($table);
        $attributes = array_intersect_key($attributes, array_flip($columns));
        $where = array_intersect_key($where, array_flip($columns));

        if ($where === []) {
            throw new \RuntimeException('No usable idempotency key for '.$table);
        }

        $now = now();
        if (in_array('updated_at', $columns, true)) {
            $attributes['updated_at'] = $now;
        }
        if (in_array('created_at', $columns, true) && ! DB::table($table)->where($where)->exists()) {
            $attributes['created_at'] = $now;
        }

        DB::table($table)->updateOrInsert($where, $attributes);
    }

    private function snakeRow(array $row, array $exclude = []): array
    {
        $out = [];

        foreach ($row as $key => $value) {
            if (in_array($key, $exclude, true)) {
                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value) === 1) {
                $value = (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
            }

            $out[Str::snake($key)] = $value;
        }

        return $out;
    }

    private function id(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-demo:'.$key), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
