<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Permissions\PermissionGroupDefinition;

enum PermissionGroup: string
{
    case DASHBOARD = 'dashboard';
    case USER = 'users';
    case ORGANIZATION = 'organizations';
    case POST_REVIEW = 'posts.review';
    case CAMPAIGN_REVIEW = 'campaigns.review';
    case REPORT = 'reports';
    case NOTIFICATION = 'notifications';
    case BADGE = 'badges';
    case ARTICLE = 'articles';
    case CATEGORY = 'categories';
    case AUDIT_LOG = 'audit_logs';
    case PLATFORM_SETTINGS = 'platform_settings';
    case ORG_CAMPAIGN = 'org.campaigns';
    case ORG_POST = 'org.posts';
    case ORG_DONOR = 'org.donors';
    case ORG_APPLICANT = 'org.applicants';
    case ORG_STAFF = 'org.staff';
    case ORG_ROLE = 'org.roles';
    case ORG_NOTIFICATION = 'org.notifications';
    case ORG_REPORT = 'org.reports';
    case ORG_SETTINGS = 'org.settings';

    public function definition(): PermissionGroupDefinition
    {
        return match ($this) {
            self::DASHBOARD => new PermissionGroupDefinition(
                label: 'Dashboard',
                module: PermissionModule::CORE,
                description: 'Access the dashboard.',
                order: 10,
                actions: [
                    PermissionAction::VIEW,
                ],
            ),

            self::USER => new PermissionGroupDefinition(
                label: 'Users',
                module: PermissionModule::CORE,
                description: 'Manage application users.',
                order: 20,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::RESET_PASSWORD,
                ],
            ),

            self::ORGANIZATION => new PermissionGroupDefinition(
                label: 'Organizations',
                module: PermissionModule::ADMIN,
                description: 'Manage organizations.',
                order: 30,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::VERIFY,
                    PermissionAction::ACCEPT,
                ],
            ),

            self::POST_REVIEW => new PermissionGroupDefinition(
                label: 'Post Review',
                module: PermissionModule::ADMIN,
                description: 'Review and moderate posts.',
                order: 40,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::APPROVE,
                    PermissionAction::REJECT,
                ],
            ),

            self::CAMPAIGN_REVIEW => new PermissionGroupDefinition(
                label: 'Campaign Review',
                module: PermissionModule::ADMIN,
                description: 'Review and moderate campaigns.',
                order: 50,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::APPROVE,
                    PermissionAction::REJECT,
                ],
            ),

            self::REPORT => new PermissionGroupDefinition(
                label: 'Reports',
                module: PermissionModule::ADMIN,
                description: 'Manage platform reports.',
                order: 60,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CLAIM,
                    PermissionAction::REQUEST_INFO,
                    PermissionAction::CLOSE,
                ],
            ),

            self::NOTIFICATION => new PermissionGroupDefinition(
                label: 'Notifications',
                module: PermissionModule::ADMIN,
                description: 'Manage notifications.',
                order: 70,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::RESEND,
                ],
            ),

            self::BADGE => new PermissionGroupDefinition(
                label: 'Badges',
                module: PermissionModule::ADMIN,
                description: 'Manage reward badges.',
                order: 80,
            ),

            self::ARTICLE => new PermissionGroupDefinition(
                label: 'Articles',
                module: PermissionModule::ADMIN,
                description: 'Manage knowledge base articles.',
                order: 90,
            ),

            self::CATEGORY => new PermissionGroupDefinition(
                label: 'Categories',
                module: PermissionModule::ADMIN,
                description: 'Manage content categories.',
                order: 95,
            ),

            self::AUDIT_LOG => new PermissionGroupDefinition(
                label: 'Audit Logs',
                module: PermissionModule::ADMIN,
                description: 'View platform audit logs.',
                order: 100,
                actions: [
                    PermissionAction::VIEW,
                ],
            ),

            self::PLATFORM_SETTINGS => new PermissionGroupDefinition(
                label: 'Platform Settings',
                module: PermissionModule::ADMIN,
                description: 'Manage platform configuration.',
                order: 110,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::UPDATE,
                ],
            ),

            self::ORG_CAMPAIGN => new PermissionGroupDefinition(
                label: 'Campaigns',
                module: PermissionModule::ORGANIZATION,
                description: 'Manage organization campaigns.',
                order: 210,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::CLOSE,
                ],
            ),

            self::ORG_POST => new PermissionGroupDefinition(
                label: 'Posts',
                module: PermissionModule::ORGANIZATION,
                description: 'Manage organization posts.',
                order: 220,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::PUBLISH,
                    PermissionAction::ARCHIVE,
                    PermissionAction::RESTORE,
                ],
            ),

            self::ORG_DONOR => new PermissionGroupDefinition(
                label: 'Donors',
                module: PermissionModule::ORGANIZATION,
                description: 'Manage organization donors.',
                order: 230,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::MANAGE,
                ],
            ),

            self::ORG_APPLICANT => new PermissionGroupDefinition(
                label: 'Applicants',
                module: PermissionModule::ORGANIZATION,
                description: 'Manage organization applicants.',
                order: 240,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::MANAGE,
                ],
            ),

            self::ORG_STAFF => new PermissionGroupDefinition(
                label: 'Staff',
                module: PermissionModule::ORGANIZATION,
                description: 'Manage organization staff.',
                order: 250,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::MANAGE,
                ],
            ),

            self::ORG_ROLE => new PermissionGroupDefinition(
                label: 'Roles',
                module: PermissionModule::ORGANIZATION,
                description: 'Manage organization roles.',
                order: 260,
            ),

            self::ORG_NOTIFICATION => new PermissionGroupDefinition(
                label: 'Notifications',
                module: PermissionModule::ORGANIZATION,
                description: 'Manage organization notifications.',
                order: 270,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::SEND,
                    PermissionAction::RESEND,
                ],
            ),

            self::ORG_REPORT => new PermissionGroupDefinition(
                label: 'Reports',
                module: PermissionModule::ORGANIZATION,
                description: 'View organization reports.',
                order: 280,
                actions: [
                    PermissionAction::VIEW,
                ],
            ),

            self::ORG_SETTINGS => new PermissionGroupDefinition(
                label: 'Settings',
                module: PermissionModule::ORGANIZATION,
                description: 'Manage organization settings.',
                order: 290,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::UPDATE,
                ],
            ),
        };
    }

    public function label(): string
    {
        return $this->definition()->label;
    }

    public function module(): PermissionModule
    {
        return $this->definition()->module;
    }

    public function moduleKey(): string
    {
        return $this->module()->value;
    }

    public function moduleLabel(): string
    {
        return $this->module()->label();
    }

    public function sectionKey(): ?string
    {
        $segments = explode('.', $this->value);

        return count($segments) === 2 ? $segments[1] : null;
    }

    public function sectionLabel(): ?string
    {
        return $this->definition()->sectionLabel;
    }

    public function description(): string
    {
        return $this->definition()->description;
    }

    public function order(): int
    {
        return $this->definition()->order;
    }

    /**
     * @return list<PermissionAction>
     */
    public function actions(): array
    {
        return $this->definition()->actions ?? PermissionAction::crud();
    }

    public function depth(): int
    {
        return substr_count($this->value, '.') + 1;
    }
}
