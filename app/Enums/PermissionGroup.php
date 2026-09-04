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
    case GROUP = 'groups';
    case REPORT = 'reports';
    case NOTIFICATION = 'notifications';
    case BADGE = 'badges';
    case ARTICLE = 'articles';
    case CATEGORY = 'categories';
    case CAPABILITY = 'capabilities';
    case RECOMMENDATION = 'recommendations';
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
    case ORG_AUDIT_LOG = 'org.audit_logs';
    case ORG_SETTINGS = 'org.settings';

    public function definition(): PermissionGroupDefinition
    {
        return match ($this) {
            self::DASHBOARD => new PermissionGroupDefinition(
                label: 'لوحة التحكم',
                module: PermissionModule::CORE,
                description: 'الوصول إلى لوحة التحكم.',
                order: 10,
                actions: [PermissionAction::VIEW],
            ),
            self::USER => new PermissionGroupDefinition(
                label: 'المستخدمون',
                module: PermissionModule::CORE,
                description: 'إدارة مستخدمي التطبيق.',
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
                label: 'المؤسسات',
                module: PermissionModule::ADMIN,
                description: 'إدارة المؤسسات المسجلة في المنصة.',
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
                label: 'مراجعة المنشورات',
                module: PermissionModule::ADMIN,
                description: 'مراجعة المنشورات والإشراف عليها.',
                order: 40,
                sectionLabel: 'المراجعة',
                actions: [PermissionAction::VIEW, PermissionAction::APPROVE, PermissionAction::REJECT],
            ),
            self::GROUP => new PermissionGroupDefinition(
                label: 'الفرق التطوعية',
                module: PermissionModule::ADMIN,
                description: 'مراجعة وإدارة المجموعات والفرق التطوعية العامة.',
                order: 50,
                sectionLabel: 'المراجعة',
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::APPROVE,
                    PermissionAction::REJECT,
                    PermissionAction::DELETE,
                ],
            ),
            self::REPORT => new PermissionGroupDefinition(
                label: 'البلاغات',
                module: PermissionModule::ADMIN,
                description: 'إدارة بلاغات المنصة ومتابعتها.',
                order: 60,
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CLAIM,
                    PermissionAction::CLOSE,
                ],
            ),
            self::NOTIFICATION => new PermissionGroupDefinition(
                label: 'الإشعارات',
                module: PermissionModule::ADMIN,
                description: 'إدارة إشعارات المنصة.',
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
                label: 'الشارات',
                module: PermissionModule::ADMIN,
                description: 'إدارة شارات المكافآت.',
                order: 80,
            ),
            self::ARTICLE => new PermissionGroupDefinition(
                label: 'المقالات',
                module: PermissionModule::ADMIN,
                description: 'إدارة مقالات قاعدة المعرفة.',
                order: 90,
            ),
            self::CATEGORY => new PermissionGroupDefinition(
                label: 'التصنيفات',
                module: PermissionModule::ADMIN,
                description: 'إدارة تصنيفات المحتوى.',
                order: 95,
            ),
            self::CAPABILITY => new PermissionGroupDefinition(
                label: 'طرق المساعدة',
                module: PermissionModule::ADMIN,
                description: 'إدارة طرق المساعدة المستخدمة في التخصيص.',
                order: 97,
                actions: [PermissionAction::VIEW, PermissionAction::CREATE, PermissionAction::UPDATE],
            ),
            self::RECOMMENDATION => new PermissionGroupDefinition(
                label: 'التوصيات والتخصيص',
                module: PermissionModule::ADMIN,
                description: 'عرض تحليلات التوصيات وبيانات التخصيص.',
                order: 98,
                actions: [PermissionAction::VIEW],
            ),
            self::AUDIT_LOG => new PermissionGroupDefinition(
                label: 'سجل التدقيق',
                module: PermissionModule::ADMIN,
                description: 'عرض سجل عمليات المنصة.',
                order: 100,
                actions: [PermissionAction::VIEW],
            ),
            self::PLATFORM_SETTINGS => new PermissionGroupDefinition(
                label: 'إعدادات المنصة',
                module: PermissionModule::ADMIN,
                description: 'إدارة إعدادات المنصة العامة.',
                order: 110,
                actions: [PermissionAction::VIEW, PermissionAction::UPDATE],
            ),
            self::ORG_CAMPAIGN => new PermissionGroupDefinition(
                label: 'الحملات',
                module: PermissionModule::ORGANIZATION,
                description: 'إدارة حملات المؤسسة.',
                order: 210,
                sectionLabel: 'الحملات',
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::CLOSE,
                ],
            ),
            self::ORG_POST => new PermissionGroupDefinition(
                label: 'المنشورات',
                module: PermissionModule::ORGANIZATION,
                description: 'إدارة منشورات المؤسسة.',
                order: 220,
                sectionLabel: 'المنشورات',
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::PUBLISH,
                ],
            ),
            self::ORG_DONOR => new PermissionGroupDefinition(
                label: 'المتبرعون',
                module: PermissionModule::ORGANIZATION,
                description: 'إدارة متبرعي المؤسسة.',
                order: 230,
                sectionLabel: 'المتبرعون',
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::MANAGE,
                ],
            ),
            self::ORG_APPLICANT => new PermissionGroupDefinition(
                label: 'المتقدمون',
                module: PermissionModule::ORGANIZATION,
                description: 'إدارة المتقدمين إلى حملات المؤسسة.',
                order: 240,
                sectionLabel: 'المتقدمون',
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::MANAGE,
                ],
            ),
            self::ORG_STAFF => new PermissionGroupDefinition(
                label: 'فريق العمل',
                module: PermissionModule::ORGANIZATION,
                description: 'إدارة فريق عمل المؤسسة.',
                order: 250,
                sectionLabel: 'فريق العمل',
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::CREATE,
                    PermissionAction::UPDATE,
                    PermissionAction::DELETE,
                    PermissionAction::MANAGE,
                ],
            ),
            self::ORG_ROLE => new PermissionGroupDefinition(
                label: 'الأدوار',
                module: PermissionModule::ORGANIZATION,
                description: 'إدارة أدوار المؤسسة والصلاحيات المرتبطة بها.',
                order: 260,
                sectionLabel: 'الأدوار',
            ),
            self::ORG_NOTIFICATION => new PermissionGroupDefinition(
                label: 'الإشعارات',
                module: PermissionModule::ORGANIZATION,
                description: 'إدارة إشعارات المؤسسة.',
                order: 270,
                sectionLabel: 'الإشعارات',
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
                label: 'التقارير',
                module: PermissionModule::ORGANIZATION,
                description: 'عرض تقارير المؤسسة وتحديث حالتها.',
                order: 280,
                sectionLabel: 'التقارير',
                actions: [
                    PermissionAction::VIEW,
                    PermissionAction::UPDATE,
                    PermissionAction::CLAIM,
                    PermissionAction::CLOSE,
                ],
            ),
            self::ORG_AUDIT_LOG => new PermissionGroupDefinition(
                label: 'سجل نشاط المؤسسة',
                module: PermissionModule::ORGANIZATION,
                description: 'عرض سجل نشاط المؤسسة.',
                order: 285,
                sectionLabel: 'سجل النشاط',
                actions: [PermissionAction::VIEW],
            ),
            self::ORG_SETTINGS => new PermissionGroupDefinition(
                label: 'الإعدادات',
                module: PermissionModule::ORGANIZATION,
                description: 'إدارة إعدادات المؤسسة.',
                order: 290,
                sectionLabel: 'الإعدادات',
                actions: [PermissionAction::VIEW, PermissionAction::UPDATE],
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

    /** @return list<PermissionAction> */
    public function actions(): array
    {
        return $this->definition()->actions ?? PermissionAction::crud();
    }

    public function depth(): int
    {
        return substr_count($this->value, '.') + 1;
    }
}
