# Seeder Data Reference for Postman

Complete mapping of Laravel seed data to Postman collection endpoints.

---

## SEEDER CLASSES CREATED

All seeder classes are located in: `database/seeders/`

### 1. **UserSeeder.php**
Creates 6 users with different roles:
- **user-123**: John Admin (admin user)
- **user-456**: Sarah Owner (organization owner)
- **user-789**: Ahmed Mohammed (volunteer)
- **user-999**: Fatima Hassan (donor)
- **user-1001**: Mohammed Ali (job seeker)
- **staff-001**: Leila Manager (organization staff)

**Postman Endpoints**:
```
GET /api/v1/me → returns user-123
GET /api/v1/admin/users → returns all 6 users
GET /api/v1/admin/users/{userId} → returns specific user
POST /api/v1/admin/users → creates new user
```

---

### 2. **OrganizationSeeder.php**
Creates 4 organizations:
- **org-001**: Help Foundation (NGO, verified, active)
- **org-002**: Education Initiative (Charity, verified, active)
- **org-003**: Tech for Good (Social Enterprise, unverified, active)
- **org-004**: Amman Community Group (pending verification)

**Sample Data**:
```json
{
  "id": "org-001",
  "owner_id": "user-456",
  "name": "Help Foundation",
  "email": "contact@helpfoundation.org",
  "phone": "+962796543210",
  "location": "Amman, Jordan",
  "organization_type": "ngo",
  "status": "active",
  "verification_status": "verified",
  "created_at": "2023-06-15T08:00:00Z",
  "last_active_at": "2025-05-25T13:45:00Z"
}
```

**Postman Endpoints**:
```
GET /api/v1/admin/organizations → all organizations
GET /api/v1/admin/organizations/{organizationId} → organization details
POST /api/v1/admin/organizations → create organization
PATCH /api/v1/admin/organizations/{organizationId} → update
PATCH /api/v1/admin/organizations/{organizationId}/status → change status
PATCH /api/v1/admin/organizations/{organizationId}/verification → verify
POST /api/v1/admin/organizations/{organizationId}/accept → accept org
```

---

### 3. **PostSeeder.php**
Creates 5 posts with different statuses:
- **post-001**: Published help request (Help Foundation)
- **post-002**: Pending job opportunity (Education Initiative)
- **post-003**: Published campaign update (Help Foundation)
- **post-004**: Archived campaign teaser (Education Initiative)
- **post-005**: Draft awareness post (Tech for Good)

**Sample Data**:
```json
{
  "id": "post-001",
  "organization_id": "org-001",
  "author_id": "user-456",
  "title": "Emergency flood relief needed",
  "summary": "Our area has been hit by severe flooding",
  "type": "help_request",
  "status": "published",
  "location": "Amman",
  "views_count": 1245,
  "reactions_count": 87,
  "published_at": "2025-05-18T10:15:00Z",
  "created_at": "2025-05-18T10:00:00Z"
}
```

**Postman Endpoints**:
```
GET /api/v1/admin/review/posts → pending posts
GET /api/v1/admin/review/posts/{postId} → post details
POST /api/v1/admin/review/posts/{postId}/approve → approve post
POST /api/v1/admin/review/posts/{postId}/reject → reject post
GET /api/v1/org/posts → organization posts
POST /api/v1/org/posts → create post
POST /api/v1/org/posts/{postId}/publish → publish post
POST /api/v1/org/posts/{postId}/archive → archive post
```

---

### 4. **CampaignSeeder.php**
Creates 5 campaigns with different statuses:
- **campaign-001**: Active health campaign (Help Foundation)
- **campaign-002**: Active education campaign (Education Initiative)
- **campaign-003**: Draft food security campaign
- **campaign-004**: Closed emergency campaign (Tech for Good)
- **campaign-005**: Pending shelter campaign

**Sample Data**:
```json
{
  "id": "campaign-001",
  "organization_id": "org-001",
  "title": "Emergency Medical Fund",
  "category": "health",
  "status": "active",
  "location": "Amman",
  "goal_amount": 50000,
  "raised_amount": 35000,
  "beneficiaries_count": 150,
  "donors_count": 234,
  "start_date": "2025-04-25",
  "end_date": "2025-07-25",
  "created_at": "2025-05-20T10:00:00Z"
}
```

**Postman Endpoints**:
```
GET /api/v1/admin/review/campaigns → campaigns for review
GET /api/v1/admin/review/campaigns/{campaignId} → campaign details
POST /api/v1/admin/review/campaigns/{campaignId}/approve → approve
POST /api/v1/admin/review/campaigns/{campaignId}/reject → reject
GET /api/v1/org/campaigns → organization campaigns
POST /api/v1/org/campaigns → create campaign
GET /api/v1/org/campaigns/{campaignId} → campaign details
PATCH /api/v1/org/campaigns/{campaignId} → update campaign
POST /api/v1/org/campaigns/{campaignId}/close → close campaign
```

---

### 5. **ReportSeeder.php**
Creates 5 reports with different severities:
- **report-001**: New high severity fraud report (campaign)
- **report-002**: In-progress high severity inappropriate content report
- **report-003**: Waiting response critical severity user impersonation
- **report-004**: Closed medium severity spam report
- **report-005**: New low severity other category report

**Sample Data**:
```json
{
  "id": "report-001",
  "reporter_id": "user-789",
  "assignee_id": null,
  "title": "Suspicious campaign activity",
  "description": "Campaign claims are not matching actual activities",
  "category": "fraud",
  "severity": "high",
  "entity_type": "campaign",
  "entity_id": "campaign-789",
  "status": "new",
  "created_at": "2025-05-24T14:30:00Z"
}
```

**Postman Endpoints**:
```
GET /api/v1/admin/reports → all reports
GET /api/v1/admin/reports/{reportId} → report details
POST /api/v1/admin/reports/{reportId}/claim → claim report
POST /api/v1/admin/reports/{reportId}/request-info → request info
POST /api/v1/admin/reports/{reportId}/close → close report
```

---

### 6. **NotificationSeeder.php**
Creates 5 notifications with different categories:
- **notification-001**: Campaign review sent notification
- **notification-002**: Post approval unread notification
- **notification-003**: Report submitted read notification
- **notification-004**: Platform maintenance system notification
- **notification-005**: Badge award notification

**Sample Data**:
```json
{
  "id": "notification-001",
  "creator_id": "user-123",
  "title": "New Campaign Submitted for Review",
  "body": "A new campaign has been submitted for your review",
  "category": "campaign",
  "mailbox": "sent",
  "priority": "normal",
  "status": "sent",
  "sent_at": "2025-05-24T10:05:00Z"
}
```

**Postman Endpoints**:
```
GET /api/v1/admin/notifications → admin notifications
GET /api/v1/admin/notifications/{notificationId} → notification details
POST /api/v1/admin/notifications → send notification
PATCH /api/v1/admin/notifications/{id}/read-state → mark read/unread
POST /api/v1/admin/notifications/{id}/resend → resend notification
DELETE /api/v1/admin/notifications/{id} → delete notification
```

---

### 7. **BadgeSeeder.php**
Creates 5 badges:
- **badge-001**: Top Donor (total_donations >= 1000)
- **badge-002**: Volunteer Champion (volunteer_hours >= 50)
- **badge-003**: Organization Leader (successful_campaigns >= 5)
- **badge-004**: Early Supporter (joined_in_first_month = true)
- **badge-005**: Community Hero (community_score >= 100)

**Sample Data**:
```json
{
  "id": "badge-001",
  "name": "Top Donor",
  "description": "Given to users who have donated over $1000",
  "criteria": "total_donations >= 1000",
  "icon_name": "star",
  "is_active": true,
  "created_at": "2024-11-25T10:00:00Z"
}
```

**Postman Endpoints**:
```
GET /api/v1/admin/badges → all badges
POST /api/v1/admin/badges → create badge
GET /api/v1/admin/badges/{badgeId} → badge details
PATCH /api/v1/admin/badges/{badgeId} → update badge
PATCH /api/v1/admin/badges/{badgeId}/status → activate/deactivate
DELETE /api/v1/admin/badges/{badgeId} → delete badge
```

---

### 8. **ArticleSeeder.php**
Creates 5 articles:
- **article-001**: How to Start a Successful Campaign (published)
- **article-002**: Volunteer Safety Guidelines (published)
- **article-003**: Maximizing Donation Impact (published)
- **article-004**: Building Community Trust (draft)
- **article-005**: Digital Transformation for NGOs (published)

**Sample Data**:
```json
{
  "id": "article-001",
  "title": "How to Start a Successful Campaign",
  "slug": "how-to-start-successful-campaign",
  "excerpt": "Tips and best practices for launching your fundraising campaign",
  "author_id": "user-123",
  "status": "published",
  "published_at": "2025-05-20T10:00:00Z",
  "created_at": "2025-05-18T14:30:00Z"
}
```

**Postman Endpoints**:
```
GET /api/v1/admin/articles → all articles
POST /api/v1/admin/articles → create article
GET /api/v1/admin/articles/{articleId} → article details
PATCH /api/v1/admin/articles/{articleId} → update article
DELETE /api/v1/admin/articles/{articleId} → delete article
```

---

### 9. **DonorSeeder.php**
Creates 5 donors:
- **donor-001**: Ahmed Mohammed ($500 donation)
- **donor-002**: Fatima Hassan ($1000 donation - VIP)
- **donor-003**: Mohammad Hassan ($250 donation)
- **donor-004**: Sarah Williams ($2000 donation)
- **donor-005**: Ali Abdullah ($500 cash donation)

**Sample Data**:
```json
{
  "id": "donor-001",
  "organization_id": "org-001",
  "campaign_id": "campaign-001",
  "name": "Ahmed Mohammed",
  "email": "ahmed@example.com",
  "phone": "+962791234567",
  "campaign_title": "Emergency Medical Fund",
  "amount_or_type": "500.00",
  "donated_at": "2025-05-20T10:00:00Z",
  "city": "Amman",
  "source": "website",
  "payment_method": "credit_card",
  "campaign_ref": "REF-2025-001"
}
```

**Postman Endpoints**:
```
GET /api/v1/org/donors → organization donors
POST /api/v1/org/donors → add donor
PATCH /api/v1/org/donors/{donorId} → update donor
DELETE /api/v1/org/donors/{donorId} → delete donor
```

---

### 10. **ApplicantSeeder.php**
Creates 5 applicants with different statuses:
- **applicant-001**: Leila Mohammed (Approved)
- **applicant-002**: Noor Hassan (Pending)
- **applicant-003**: Omar Salem (Approved)
- **applicant-004**: Zainab Ahmed (Rejected)
- **applicant-005**: Rania Hassan (Pending)

**Sample Data**:
```json
{
  "id": "applicant-001",
  "organization_id": "org-002",
  "campaign_id": "campaign-002",
  "name": "Leila Mohammed",
  "email": "leila@example.com",
  "campaign_title": "Back to School",
  "amount_or_type": "Approved",
  "applied_at": "2025-05-15T10:00:00Z",
  "city": "Zarqa",
  "source": "internal",
  "campaign_ref": "APP-2025-001"
}
```

**Postman Endpoints**:
```
GET /api/v1/org/applicants → organization applicants
POST /api/v1/org/applicants → add applicant
PATCH /api/v1/org/applicants/{applicantId} → update applicant
DELETE /api/v1/org/applicants/{applicantId} → delete applicant
```

---

### 11. **StaffSeeder.php**
Creates 7 staff members:
- **staff-001**: Sarah Ahmed (Owner - org-001)
- **staff-002**: Leila Manager (Manager - org-001)
- **staff-003**: Ahmed Hassan (Editor - org-001)
- **staff-004**: Noor Khalil (Viewer - org-001)
- **staff-005**: Fatima Mohammed (Owner - org-002)
- **staff-006**: Rania Salem (Manager - org-002)
- **staff-007**: Hassan Ahmed (Owner - org-003)

**Sample Data**:
```json
{
  "id": "staff-001",
  "organization_id": "org-001",
  "user_id": "user-456",
  "name": "Sarah Ahmed",
  "email": "sarah@helpfoundation.org",
  "role": "owner",
  "invited_at": "2023-09-25T10:00:00Z"
}
```

**Postman Endpoints**:
```
GET /api/v1/org/staff → organization staff
POST /api/v1/org/staff → invite staff member
PATCH /api/v1/org/staff/{staffId} → update staff
DELETE /api/v1/org/staff/{staffId} → remove staff
```

---

### 12. **RoleSeeder.php**
Creates 7 roles:
- **role-001**: Owner (system) - full access
- **role-002**: Manager - can manage content & staff
- **role-003**: Editor - can create & edit content
- **role-004**: Viewer (system) - read-only
- **role-005**: Contributor - can submit posts
- **role-006**: Owner (org-002, system)
- **role-007**: Viewer (org-002, system)

**Sample Data**:
```json
{
  "id": "role-001",
  "organization_id": "org-001",
  "name": "Owner",
  "description": "Full access to organization",
  "permissions": [
    "org.campaigns.view",
    "org.campaigns.create",
    "org.campaigns.update",
    "org.campaigns.delete",
    "org.posts.view",
    "org.posts.create"
  ],
  "is_active": true,
  "is_system": true,
  "members_count": 1
}
```

**Postman Endpoints**:
```
GET /api/v1/org/roles → organization roles
POST /api/v1/org/roles → create role
PATCH /api/v1/org/roles/{roleId} → update role
DELETE /api/v1/org/roles/{roleId} → delete role
GET /api/v1/org/permissions/catalog → available permissions
```

---

## HOW TO USE THE SEEDERS

### 1. **Run All Seeders**
```bash
php artisan db:seed
```

### 2. **Run Specific Seeder**
```bash
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=OrganizationSeeder
php artisan db:seed --class=PostSeeder
php artisan db:seed --class=CampaignSeeder
php artisan db:seed --class=ReportSeeder
php artisan db:seed --class=NotificationSeeder
php artisan db:seed --class=BadgeSeeder
php artisan db:seed --class=ArticleSeeder
php artisan db:seed --class=DonorSeeder
php artisan db:seed --class=ApplicantSeeder
php artisan db:seed --class=StaffSeeder
php artisan db:seed --class=RoleSeeder
```

### 3. **Fresh Seed (Reset Database)**
```bash
php artisan migrate:fresh --seed
```

---

## TESTING WITH POSTMAN

### Setup
1. Import `JOD_Dashboard_API.postman_collection.json`
2. Set variables:
   - `base_url`: `http://localhost/api/v1`
   - `token`: Admin/user token from login

### Test Flow
1. Run seeders to populate database
2. Get token via login endpoint
3. Set `token` variable in Postman
4. Test endpoints using seed data:
   - User ID: `user-123`, `user-456`, `user-789`, etc.
   - Organization ID: `org-001`, `org-002`, `org-003`
   - Campaign ID: `campaign-001`, `campaign-002`, etc.
   - Post ID: `post-001`, `post-002`, etc.

---

## DATABASE RELATIONSHIPS

```
Users
├── organization_id → Organizations
└── roles

Organizations  
├── owner_id → Users
├── campaigns
├── posts
└── staff

Campaigns
├── organization_id → Organizations
├── posts
├── donors
└── applicants

Posts
├── organization_id → Organizations
└── campaign_id → Campaigns (optional)

Reports
├── reporter_id → Users
├── assignee_id → Users
└── entity_id (polymorphic)

Notifications
├── creator_id → Users (nullable)
└── related to any entity

Staff
├── organization_id → Organizations
├── user_id → Users (nullable)
└── role

Roles
├── organization_id → Organizations
└── permissions (JSON)

Donors
├── organization_id → Organizations
└── campaign_id → Campaigns

Applicants
├── organization_id → Organizations
└── campaign_id → Campaigns
```

---

## SEEDER DATA STATISTICS

| Table | Records | Notes |
|-------|---------|-------|
| Users | 6 | Various roles: admin, owner, volunteer, donor, job_seeker |
| Organizations | 4 | Mix of verified/unverified, active/pending |
| Posts | 5 | Different statuses: published, pending, draft, archived |
| Campaigns | 5 | Different statuses: active, draft, closed, pending |
| Reports | 5 | Different severity: high, critical, medium, low |
| Notifications | 5 | Different categories: campaign, post, report, system, badge |
| Badges | 5 | Different criteria and icons |
| Articles | 5 | Mix of published and draft |
| Donors | 5 | Different payment methods and sources |
| Applicants | 5 | Different approval statuses |
| Staff | 7 | Different roles: owner, manager, editor, viewer |
| Roles | 7 | System and custom roles |

---

## POSTMAN COLLECTION UPDATES

The Postman collection has been updated with:
- ✅ Admin users list response (6 users from UserSeeder)
- ✅ Organizations list response (4 organizations from OrganizationSeeder)
- ✅ Request bodies for POST endpoints
- ✅ Sample response data for all endpoints

**Additional manual updates needed** for these endpoints:
- Posts for review list → use PostSeeder data
- Campaigns for review list → use CampaignSeeder data
- Reports list → use ReportSeeder data
- Notifications list → use NotificationSeeder data
- Badges list → use BadgeSeeder data
- Articles list → use ArticleSeeder data
- Donors list → use DonorSeeder data
- Applicants list → use ApplicantSeeder data
- Staff list → use StaffSeeder data
- Roles list → use RoleSeeder data

---

## QUICK REFERENCE IDs

**Users**
```
Admin:        user-123
Org Owner:    user-456
Volunteer:    user-789
Donor:        user-999
Job Seeker:   user-1001
Manager:      staff-001
```

**Organizations**
```
Help Foundation:        org-001 (verified)
Education Initiative:   org-002 (verified)
Tech for Good:          org-003 (unverified)
Amman Community Group:   org-004 (pending)
```

**Campaigns**
```
Medical Fund:       campaign-001 (active, health)
Back to School:     campaign-002 (active, education)
Food Security:      campaign-003 (draft)
Emergency Relief:   campaign-004 (closed)
Shelter:            campaign-005 (pending)
```

**Posts**
```
Flood Relief:       post-001 (published)
Teacher Job:        post-002 (pending)
Medical Update:     post-003 (published)
Archived Post:      post-004 (archived)
Draft Post:         post-005 (draft)
```

