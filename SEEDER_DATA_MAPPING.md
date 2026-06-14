# Seeder Data Mapping for Frontend

This document lists the seeded records the frontend and Postman collection actually use.
It intentionally excludes backend-only seed details that do not affect UI development.

See [`AUTH_USERS_AND_PERMISSIONS.md`](AUTH_USERS_AND_PERMISSIONS.md) for the seeded auth users and access notes.

## What Frontend Devs Need

- Which seeders exist
- Which endpoints can be exercised with seeded data
- Which IDs are stable
- Which values should be used in UI mocks and Postman samples

## Stable Seeded Records

### Users

`database/seeders/UserSeeder.php` creates 6 users with UUID primary keys.

Use email addresses, not IDs, to identify them in docs and manual testing:

- `admin@jod.com` - John Admin
- `owner@helpfoundation.org` - Sarah Owner
- `ahmed@example.com` - Ahmed Mohammed
- `fatima@example.com` - Fatima Hassan
- `mohammed@example.com` - Mohammed Ali
- `manager@helpfoundation.org` - Leila Manager

Key note:
- User IDs are UUIDs generated at seed time.
- Do not rely on fixed IDs for these users.

### Organizations

`database/seeders/OrganizationSeeder.php` creates 4 stable organizations:

- `org-001` - Help Foundation
- `org-002` - Education Initiative
- `org-003` - Tech for Good
- `org-004` - Amman Community Group

These IDs are stable and safe to use in docs and Postman examples.

### Campaigns

`database/seeders/CampaignSeeder.php` creates 5 stable campaigns:

- `campaign-001` - Emergency Medical Fund
- `campaign-002` - Back to School Initiative
- `campaign-003` - Food Security Program
- `campaign-004` - Emergency Relief 2024
- `campaign-005` - Shelter for Homeless

Use these for:
- Admin review screens
- Org campaigns screens
- Dashboard counters and charts

### Posts

`database/seeders/PostSeeder.php` creates 5 stable posts:

- `post-001` - Emergency flood relief needed
- `post-002` - Volunteer opportunity: Teacher needed
- `post-003` - Medical Fund Update
- `post-004` - Archived campaign announcement
- `post-005` - Draft post not yet published

Use these for:
- Admin post review
- Org posts list
- Publish, archive, and restore workflow samples

### Reports

`database/seeders/ReportSeeder.php` creates 5 stable reports:

- `report-001` - New high severity fraud report
- `report-002` - In-progress content report
- `report-003` - Waiting response impersonation report
- `report-004` - Closed spam report
- `report-005` - New low severity other report

Use these for:
- Admin reports list
- Claim, request info, and close workflow examples

### Notifications

`database/seeders/NotificationSeeder.php` creates 5 stable notifications:

- `notification-001` - Campaign review sent notification
- `notification-002` - Post approval unread notification
- `notification-003` - Report submitted read notification
- `notification-004` - Platform maintenance system notification
- `notification-005` - Badge award notification

Use these for:
- Admin notifications list
- Org notifications list
- Read-state and resend workflows

### Badges

`database/seeders/BadgeSeeder.php` creates 5 badges:

- `badge-001` - Top Donor
- `badge-002` - Volunteer Champion
- `badge-003` - Organization Leader
- `badge-004` - Early Supporter
- `badge-005` - Community Hero

### Articles

`database/seeders/ArticleSeeder.php` creates 5 articles:

- `article-001` - How to Start a Successful Campaign
- `article-002` - Volunteer Safety Guidelines
- `article-003` - Maximizing Donation Impact
- `article-004` - Building Community Trust
- `article-005` - Digital Transformation for NGOs

### Donors

`database/seeders/DonorSeeder.php` creates 5 donor records.
Use them for:

- Org donors list
- Add, edit, delete donor flows

### Applicants

`database/seeders/ApplicantSeeder.php` creates 5 applicant records.
Use them for:

- Org applicants list
- Add, edit, delete applicant flows

### Staff

`database/seeders/StaffSeeder.php` creates 7 staff records.

Important:
- The current `user_id` links in this seeder are placeholder labels.
- They do not resolve cleanly to the UUID users created by `UserSeeder`.

Use staff records for:
- Org staff list
- Invite, update role, and remove staff flows

### Roles

`database/seeders/RoleSeeder.php` creates default organization roles for each organization:

- Owner
- Manager
- Editor
- Viewer

These roles are catalog-backed and are used by:

- Org roles list
- Create/update/delete role forms
- Permission catalog selection UI

## Frontend Endpoint Map

### Bootstrap

- `GET /api/v1/me`
- `GET /api/v1/me/permissions`
- `GET /api/v1/me/dashboard-context`

### Admin

- `GET /api/v1/admin/overview`
- `GET /api/v1/admin/analytics/kpis`
- `GET /api/v1/admin/analytics/weekly`
- `GET /api/v1/admin/users`
- `GET /api/v1/admin/organizations`
- `GET /api/v1/admin/review/posts`
- `GET /api/v1/admin/review/campaigns`
- `GET /api/v1/admin/reports`
- `GET /api/v1/admin/notifications`
- `GET /api/v1/admin/badges`
- `GET /api/v1/admin/categories`
- `GET /api/v1/admin/articles`
- `GET /api/v1/admin/audit-logs`
- `GET /api/v1/admin/platform-settings`

### Organization

- `GET /api/v1/org/overview`
- `GET /api/v1/org/campaigns`
- `GET /api/v1/org/posts`
- `GET /api/v1/org/donors`
- `GET /api/v1/org/applicants`
- `GET /api/v1/org/staff`
- `GET /api/v1/org/roles`
- `GET /api/v1/org/permissions/catalog`
- `GET /api/v1/org/notifications`
- `GET /api/v1/org/reports`
- `GET /api/v1/org/settings/profile`
- `GET /api/v1/org/settings/bank-account`

## Practical Notes

- For seeded user lookup in the frontend, use email-based references.
- For seeded business objects, use the stable IDs listed above.
- For role and permission UI, use the shared permission catalog, not hard-coded strings.
- For dashboard bootstrap, use `/me/dashboard-context` when you want profile, permissions, and counters in one request.
