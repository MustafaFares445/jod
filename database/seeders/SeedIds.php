<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Support\Str;

final class SeedIds
{
    /**
     * @var array<string, string>
     */
    private static array $ids = [];

    public static function id(string $key): string
    {
        if (self::$ids === []) {
            self::initialize();
        }

        return self::$ids[$key] ?? throw new \InvalidArgumentException("Unknown seed id [$key].");
    }

    private static function initialize(): void
    {
        self::$ids = [
            'organizations.helpFoundation' => (string) Str::uuid(),
            'organizations.educationInitiative' => (string) Str::uuid(),
            'organizations.techForGood' => (string) Str::uuid(),
            'organizations.ammanCommunityGroup' => (string) Str::uuid(),

            'users.johnAdmin' => (string) Str::uuid(),
            'users.sarahAhmed' => (string) Str::uuid(),
            'users.ahmedMohammed' => (string) Str::uuid(),
            'users.fatimaHassan' => (string) Str::uuid(),
            'users.mohammedAli' => (string) Str::uuid(),
            'users.leilaManager' => (string) Str::uuid(),

            'roles.org1.owner' => (string) Str::uuid(),
            'roles.org1.manager' => (string) Str::uuid(),
            'roles.org1.editor' => (string) Str::uuid(),
            'roles.org1.viewer' => (string) Str::uuid(),
            'roles.org2.owner' => (string) Str::uuid(),
            'roles.org2.manager' => (string) Str::uuid(),
            'roles.org2.editor' => (string) Str::uuid(),
            'roles.org2.viewer' => (string) Str::uuid(),
            'roles.org3.owner' => (string) Str::uuid(),
            'roles.org3.manager' => (string) Str::uuid(),
            'roles.org3.editor' => (string) Str::uuid(),
            'roles.org3.viewer' => (string) Str::uuid(),
            'roles.org4.owner' => (string) Str::uuid(),
            'roles.org4.manager' => (string) Str::uuid(),
            'roles.org4.editor' => (string) Str::uuid(),
            'roles.org4.viewer' => (string) Str::uuid(),

            'badges.topDonor' => (string) Str::uuid(),
            'badges.volunteerChampion' => (string) Str::uuid(),
            'badges.organizationLeader' => (string) Str::uuid(),
            'badges.earlySupporter' => (string) Str::uuid(),
            'badges.communityHero' => (string) Str::uuid(),

            'campaigns.emergencyMedicalFund' => (string) Str::uuid(),
            'campaigns.backToSchoolInitiative' => (string) Str::uuid(),
            'campaigns.foodSecurityProgram' => (string) Str::uuid(),
            'campaigns.emergencyRelief2024' => (string) Str::uuid(),
            'campaigns.shelterForHomeless' => (string) Str::uuid(),

            'posts.emergencyFloodRelief' => (string) Str::uuid(),
            'posts.volunteerOpportunityTeacherNeeded' => (string) Str::uuid(),
            'posts.medicalFundUpdate' => (string) Str::uuid(),
            'posts.archivedCampaignAnnouncement' => (string) Str::uuid(),
            'posts.draftPostNotPublished' => (string) Str::uuid(),

            'notifications.newCampaignSubmitted' => (string) Str::uuid(),
            'notifications.postApprovalAlert' => (string) Str::uuid(),
            'notifications.reportSubmitted' => (string) Str::uuid(),
            'notifications.platformMaintenance' => (string) Str::uuid(),
            'notifications.badgeAwarded' => (string) Str::uuid(),

            'reports.suspiciousCampaignActivity' => (string) Str::uuid(),
            'reports.inappropriatePostContent' => (string) Str::uuid(),
            'reports.userImpersonationAttempt' => (string) Str::uuid(),
            'reports.spamPostReported' => (string) Str::uuid(),
            'reports.typoInCampaignDescription' => (string) Str::uuid(),

            'articles.howToStartSuccessfulCampaign' => (string) Str::uuid(),
            'articles.volunteerSafetyGuidelines' => (string) Str::uuid(),
            'articles.maximizingDonationImpact' => (string) Str::uuid(),
            'articles.buildingCommunityTrust' => (string) Str::uuid(),
            'articles.digitalTransformationForNGOs' => (string) Str::uuid(),

            'applicants.leilaMohammed' => (string) Str::uuid(),
            'applicants.noorHassan' => (string) Str::uuid(),
            'applicants.omarSalem' => (string) Str::uuid(),
            'applicants.zainabAhmed' => (string) Str::uuid(),
            'applicants.raniaHassan' => (string) Str::uuid(),

            'donors.ahmedMohammed' => (string) Str::uuid(),
            'donors.fatimaHassan' => (string) Str::uuid(),
            'donors.mohammadHassan' => (string) Str::uuid(),
            'donors.sarahWilliams' => (string) Str::uuid(),
            'donors.aliAbdullah' => (string) Str::uuid(),
        ];
    }
}
