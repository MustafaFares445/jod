<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationEventType: string
{
    case DonationCompleted = 'donation.completed';
    case DonationReceived = 'donation.received';

    case CampaignGoalReached = 'campaign.goal_reached';
    case CampaignClosingSoon = 'campaign.closing_soon';
    case CampaignClosed = 'campaign.closed';

    case ApplicationSubmitted = 'application.submitted';
    case ApplicationAccepted = 'application.accepted';
    case ApplicationRejected = 'application.rejected';
    case ApplicationWithdrawn = 'application.withdrawn';

    case PostSubmitted = 'post.submitted';
    case PostApproved = 'post.approved';
    case PostRejected = 'post.rejected';

    case ReportSubmitted = 'report.submitted';
    case ReportInProgress = 'report.in_progress';
    case ReportInfoRequested = 'report.info_requested';
    case ReportClosed = 'report.closed';

    case OrganizationSubmitted = 'organization.submitted';
    case OrganizationApproved = 'organization.approved';
    case OrganizationRejected = 'organization.rejected';

    case StaffInvited = 'staff.invited';
    case StaffRoleChanged = 'staff.role_changed';
    case StaffRemoved = 'staff.removed';

    case SystemAnnouncement = 'system.announcement';
    case SystemMaintenance = 'system.maintenance';

    public function category(): string
    {
        return match ($this) {
            self::DonationCompleted, self::DonationReceived => 'donation',
            self::CampaignGoalReached, self::CampaignClosingSoon, self::CampaignClosed => 'campaign',
            self::ApplicationSubmitted, self::ApplicationAccepted, self::ApplicationRejected, self::ApplicationWithdrawn => 'applicant',
            self::PostSubmitted, self::PostApproved, self::PostRejected => 'post',
            self::ReportSubmitted, self::ReportInProgress, self::ReportInfoRequested, self::ReportClosed => 'report',
            self::OrganizationSubmitted, self::OrganizationApproved, self::OrganizationRejected => 'account',
            self::StaffInvited, self::StaffRoleChanged, self::StaffRemoved => 'staff',
            self::SystemAnnouncement, self::SystemMaintenance => 'system',
        };
    }
}
