<?php

declare(strict_types=1);

namespace App\Enums;

enum PersonalizationEventType: string
{
    case PostView = 'post_view';
    case PostOpen = 'post_open';
    case PostLike = 'post_like';
    case PostSave = 'post_save';
    case PostShare = 'post_share';
    case PublisherFollow = 'publisher_follow';
    case PublisherUnfollow = 'publisher_unfollow';
    case Search = 'search';
    case CampaignOpen = 'campaign_open';
    case CampaignDonation = 'campaign_donation';
    case HelpOffer = 'help_offer';
    case VolunteerApplication = 'volunteer_application';
    case ContactAction = 'contact_action';
    case ReelWatch = 'reel_watch';
    case ReelComplete = 'reel_complete';
    case ReelReplay = 'reel_replay';
    case NotInterested = 'not_interested';
    case HidePost = 'hide_post';
    case HidePublisher = 'hide_publisher';
}
