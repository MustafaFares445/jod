<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PermissionsController extends Controller
{
    private const ORGANIZATION_PERMISSIONS = [
        'Campaigns' => [
            'org.campaigns.view' => 'View Campaigns',
            'org.campaigns.create' => 'Create Campaigns',
            'org.campaigns.update' => 'Update Campaigns',
            'org.campaigns.close' => 'Close Campaigns',
            'org.campaigns.delete' => 'Delete Campaigns',
        ],
        'Posts' => [
            'org.posts.view' => 'View Posts',
            'org.posts.create' => 'Create Posts',
            'org.posts.update' => 'Update Posts',
            'org.posts.publish' => 'Publish Posts',
            'org.posts.archive' => 'Archive Posts',
            'org.posts.delete' => 'Delete Posts',
        ],
        'Donors' => [
            'org.donors.view' => 'View Donors',
            'org.donors.create' => 'Create Donor Records',
            'org.donors.update' => 'Update Donor Records',
            'org.donors.delete' => 'Delete Donor Records',
        ],
        'Applicants' => [
            'org.applicants.view' => 'View Applicants',
            'org.applicants.create' => 'Create Applicant Records',
            'org.applicants.update' => 'Update Applicant Records',
            'org.applicants.delete' => 'Delete Applicant Records',
        ],
        'Staff' => [
            'org.staff.view' => 'View Staff',
            'org.staff.create' => 'Invite Staff',
            'org.staff.update' => 'Update Staff',
            'org.staff.delete' => 'Remove Staff',
        ],
        'Roles' => [
            'org.roles.view' => 'View Roles',
            'org.roles.create' => 'Create Roles',
            'org.roles.update' => 'Update Roles',
            'org.roles.delete' => 'Delete Roles',
        ],
        'Notifications' => [
            'org.notifications.view' => 'View Notifications',
            'org.notifications.create' => 'Send Notifications',
            'org.notifications.update' => 'Update Notifications',
            'org.notifications.delete' => 'Delete Notifications',
        ],
        'Reports' => [
            'org.reports.view' => 'View Reports',
        ],
        'Settings' => [
            'org.settings.view' => 'View Settings',
            'org.settings.update' => 'Update Settings',
        ],
    ];

    public function __invoke(): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = [];
        foreach (self::ORGANIZATION_PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $id => $name) {
                $data[] = [
                    'id' => $id,
                    'name' => $name,
                    'group' => $group,
                ];
            }
        }

        return response()->json(['data' => $data]);
    }
}
