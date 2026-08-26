<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationEventType: string
{
    case DonationIntentCreated = 'donation.intent_created';
    case DonationContactStarted = 'donation.contact_started';
    case DonationAgreed = 'donation.agreed';
    case DonationCompleted = 'donation.completed';
    case DonationCancelled = 'donation.cancelled';
    case DonationReceived = 'donation.received';

    case HelpOfferCreated = 'help_offer.created';
    case HelpOfferAccepted = 'help_offer.accepted';
    case HelpOfferRejected = 'help_offer.rejected';
    case HelpOfferContactStarted = 'help_offer.contact_started';
    case HelpOfferAgreed = 'help_offer.agreed';
    case HelpOfferHelperConfirmed = 'help_offer.helper_confirmed';
    case HelpOfferReceiverConfirmed = 'help_offer.receiver_confirmed';
    case HelpOfferCompleted = 'help_offer.completed';
    case HelpOfferCancelled = 'help_offer.cancelled';
    case HelpRequestFulfilled = 'help_request.fulfilled';
    case HelpRequestReopened = 'help_request.reopened';

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
            self::DonationIntentCreated, self::DonationContactStarted, self::DonationAgreed,
            self::DonationCompleted, self::DonationCancelled, self::DonationReceived => 'donation',
            self::HelpOfferCreated, self::HelpOfferAccepted, self::HelpOfferRejected,
            self::HelpOfferContactStarted, self::HelpOfferAgreed, self::HelpOfferHelperConfirmed,
            self::HelpOfferReceiverConfirmed, self::HelpOfferCompleted, self::HelpOfferCancelled,
            self::HelpRequestFulfilled, self::HelpRequestReopened => 'help',
            self::CampaignGoalReached, self::CampaignClosingSoon, self::CampaignClosed => 'campaign',
            self::ApplicationSubmitted, self::ApplicationAccepted, self::ApplicationRejected, self::ApplicationWithdrawn => 'applicant',
            self::PostSubmitted, self::PostApproved, self::PostRejected => 'post',
            self::ReportSubmitted, self::ReportInProgress, self::ReportClosed => 'report',
            self::OrganizationSubmitted, self::OrganizationApproved, self::OrganizationRejected => 'account',
            self::StaffInvited, self::StaffRoleChanged, self::StaffRemoved => 'staff',
            self::SystemAnnouncement, self::SystemMaintenance => 'system',
        };
    }
}
