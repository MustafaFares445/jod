<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\PlatformSetting;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BadgePolicy;
use App\Policies\CampaignReviewPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\OrganizationRolePolicy;
use App\Policies\OrganizationStaffPolicy;
use App\Policies\PostReviewPolicy;
use App\Policies\ReportPolicy;
use App\Policies\SettingsPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Organization::class => OrganizationPolicy::class,
        Post::class => PostReviewPolicy::class,
        Campaign::class => CampaignReviewPolicy::class,
        Report::class => ReportPolicy::class,
        Notification::class => NotificationPolicy::class,
        Article::class => ArticlePolicy::class,
        Badge::class => BadgePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        PlatformSetting::class => SettingsPolicy::class,
        OrganizationStaff::class => OrganizationStaffPolicy::class,
        OrganizationRole::class => OrganizationRolePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
