# JOD Dashboard API - Comprehensive Seed Data Reference

This document provides complete example data for all API endpoints. Use this as reference for Postman, testing, and documentation.

---

## ENUMS & CONSTANTS

### User Types
- `admin` - Platform administrator
- `general` - Regular user
- `volunteer` - Volunteer user
- `job_seeker` - Job seeking user
- `donor` - Donor user

### Status Values
- `active` - Active/enabled
- `inactive` - Inactive/disabled
- `pending` - Pending approval
- `rejected` - Rejected

### Verification Status
- `verified` - Verified organization
- `unverified` - Unverified organization

### Post Status
- `draft` - Draft post (not published)
- `published` - Published post
- `archived` - Archived post

### Post Types
- `general` - General post
- `job_opportunity` - Job opportunity post
- `campaign_teaser` - Campaign teaser
- `campaign_update` - Campaign update
- `campaign_summary` - Campaign summary
- `help_request` - Help request
- `awareness` - Awareness post

### Campaign Status
- `draft` - Draft campaign
- `active` - Active campaign
- `closed` - Closed campaign

### Campaign Categories
- `health` - Health & Medical
- `education` - Education
- `shelter` - Shelter & Housing
- `food` - Food & Nutrition
- `employment` - Employment
- `emergency` - Emergency Relief
- `other` - Other

### Report Status
- `new` - New report
- `in_progress` - In progress investigation
- `waiting_response` - Waiting for response
- `closed` - Closed report

### Report Severity
- `low` - Low severity
- `medium` - Medium severity
- `high` - High severity
- `critical` - Critical severity

### Report Entity Types
- `post` - Report on post
- `campaign` - Report on campaign
- `user` - Report on user
- `organization` - Report on organization

### Notification Status
- `unread` - Unread notification
- `read` - Read notification
- `sent` - Sent notification

### Notification Mailbox
- `inbox` - Inbox mailbox
- `sent` - Sent mailbox

### Notification Category
- `campaign` - Campaign related
- `post` - Post related
- `account` - Account related
- `report` - Report related
- `system` - System notification

### Notification Priority
- `normal` - Normal priority
- `high` - High priority

### Recipient Scope
- `all` - All users and organizations
- `users` - Users only
- `organizations` - Organizations only

### Organization Type
- `ngo` - Non-Governmental Organization
- `charity` - Charity Organization
- `social_enterprise` - Social Enterprise
- `community_group` - Community Group
- `government` - Government Organization

### Staff Roles
- `owner` - Organization owner
- `manager` - Organization manager
- `editor` - Editor
- `viewer` - Viewer only

### Date Ranges for Analytics
- `7d` - Last 7 days
- `30d` - Last 30 days
- `90d` - Last 90 days
- `12m` - Last 12 months

---

## SEED DATA EXAMPLES

### 1. BOOTSTRAP & PROFILE

#### GET /api/v1/me - Admin User
```json
{
  "data": {
    "id": "user-123",
    "name": "John Admin",
    "email": "admin@jod.com",
    "phone": "+962791234567",
    "userType": "admin",
    "status": "active",
    "createdAt": "2024-01-15T10:30:00Z",
    "lastActiveAt": "2025-05-25T14:22:00Z"
  },
  "message": "User profile retrieved successfully"
}
```

#### GET /api/v1/me - Organization Owner
```json
{
  "data": {
    "id": "user-456",
    "name": "Sarah Owner",
    "email": "owner@orgname.com",
    "phone": "+962791234568",
    "userType": "general",
    "organizationId": "org-001",
    "organizationName": "Help Foundation",
    "status": "active",
    "createdAt": "2024-03-20T08:15:00Z",
    "lastActiveAt": "2025-05-25T13:45:00Z"
  },
  "message": "User profile retrieved successfully"
}
```

#### GET /api/v1/me/permissions - Admin
```json
{
  "data": {
    "modules": [
      {
        "name": "Dashboard",
        "permissions": ["dashboard.view"]
      },
      {
        "name": "Users Management",
        "permissions": ["users.view", "users.create", "users.update", "users.delete", "users.reset_password"]
      },
      {
        "name": "Organizations",
        "permissions": ["organizations.view", "organizations.update", "organizations.delete", "organizations.verify", "organizations.accept"]
      },
      {
        "name": "Content Moderation",
        "permissions": ["posts.review.view", "posts.review.approve", "posts.review.reject", "campaigns.review.view", "campaigns.review.approve", "campaigns.review.reject"]
      },
      {
        "name": "Reports",
        "permissions": ["reports.view", "reports.claim", "reports.request_info", "reports.close"]
      },
      {
        "name": "Notifications",
        "permissions": ["notifications.view", "notifications.create", "notifications.update", "notifications.delete", "notifications.resend"]
      },
      {
        "name": "Badges",
        "permissions": ["badges.view", "badges.create", "badges.update", "badges.delete"]
      },
      {
        "name": "Articles",
        "permissions": ["articles.view", "articles.create", "articles.update", "articles.delete", "articles.publish"]
      },
      {
        "name": "Analytics",
        "permissions": ["analytics.view"]
      },
      {
        "name": "Audit Logs",
        "permissions": ["audit_logs.view"]
      },
      {
        "name": "Platform Settings",
        "permissions": ["platform_settings.view", "platform_settings.update"]
      }
    ],
    "flat": {
      "dashboard.view": true,
      "users.view": true,
      "users.create": true,
      "users.update": true,
      "users.delete": true,
      "users.reset_password": true,
      "organizations.view": true,
      "organizations.update": true,
      "organizations.delete": true,
      "organizations.verify": true,
      "organizations.accept": true,
      "posts.review.view": true,
      "posts.review.approve": true,
      "posts.review.reject": true,
      "campaigns.review.view": true,
      "campaigns.review.approve": true,
      "campaigns.review.reject": true,
      "reports.view": true,
      "reports.claim": true,
      "reports.request_info": true,
      "reports.close": true,
      "notifications.view": true,
      "notifications.create": true,
      "notifications.update": true,
      "notifications.delete": true,
      "notifications.resend": true,
      "badges.view": true,
      "badges.create": true,
      "badges.update": true,
      "badges.delete": true,
      "articles.view": true,
      "articles.create": true,
      "articles.update": true,
      "articles.delete": true,
      "articles.publish": true,
      "analytics.view": true,
      "audit_logs.view": true,
      "platform_settings.view": true,
      "platform_settings.update": true
    },
    "granted": [
      "dashboard.view",
      "users.view",
      "users.create",
      "users.update",
      "users.delete",
      "users.reset_password",
      "organizations.view",
      "organizations.update",
      "organizations.delete",
      "organizations.verify",
      "organizations.accept",
      "posts.review.view",
      "posts.review.approve",
      "posts.review.reject",
      "campaigns.review.view",
      "campaigns.review.approve",
      "campaigns.review.reject",
      "reports.view",
      "reports.claim",
      "reports.request_info",
      "reports.close",
      "notifications.view",
      "notifications.create",
      "notifications.update",
      "notifications.delete",
      "notifications.resend",
      "badges.view",
      "badges.create",
      "badges.update",
      "badges.delete",
      "articles.view",
      "articles.create",
      "articles.update",
      "articles.delete",
      "articles.publish",
      "analytics.view",
      "audit_logs.view",
      "platform_settings.view",
      "platform_settings.update"
    ]
  },
  "message": "Permissions retrieved successfully"
}
```

#### GET /api/v1/me/dashboard-context
```json
{
  "data": {
    "profile": {
      "id": "user-123",
      "name": "John Admin",
      "email": "admin@jod.com",
      "phone": "+962791234567",
      "userType": "admin",
      "status": "active"
    },
    "permissions": {
      "dashboard.view": true,
      "users.view": true,
      "users.create": true,
      "organizations.view": true,
      "posts.review.view": true,
      "campaigns.review.view": true,
      "reports.view": true,
      "analytics.view": true
    },
    "counters": {
      "unreadNotifications": 5,
      "pendingReviews": 12,
      "openReports": 3
    }
  },
  "message": "Dashboard context loaded"
}
```

---

### 2. ADMIN OVERVIEW & ANALYTICS

#### GET /api/v1/admin/overview
```json
{
  "data": {
    "stats": [
      {
        "id": "total-users",
        "label": "Total Users",
        "value": 15420,
        "subLabel": "+234 this week",
        "icon": "users"
      },
      {
        "id": "total-organizations",
        "label": "Organizations",
        "value": 342,
        "subLabel": "+12 this week",
        "icon": "building"
      },
      {
        "id": "active-campaigns",
        "label": "Active Campaigns",
        "value": 87,
        "subLabel": "28% funded on average",
        "icon": "flag"
      },
      {
        "id": "total-donations",
        "label": "Total Donations",
        "value": 524340,
        "subLabel": "+$12,500 this week",
        "icon": "heart"
      }
    ],
    "activity": [
      {
        "id": "activity-1",
        "title": "New organization registered",
        "detail": "Tech for Good Foundation started their profile",
        "at": "2025-05-25T14:22:00Z"
      },
      {
        "id": "activity-2",
        "title": "Campaign milestone reached",
        "detail": "Education Initiative reached 50% of funding goal",
        "at": "2025-05-25T13:15:00Z"
      },
      {
        "id": "activity-3",
        "title": "Post approved",
        "detail": "Help Foundation's awareness post was approved",
        "at": "2025-05-25T11:30:00Z"
      },
      {
        "id": "activity-4",
        "title": "User joined",
        "detail": "Ahmed Mohammed registered as volunteer",
        "at": "2025-05-25T10:45:00Z"
      },
      {
        "id": "activity-5",
        "title": "Report submitted",
        "detail": "New report submitted about suspicious campaign",
        "at": "2025-05-25T09:20:00Z"
      }
    ]
  },
  "message": "Overview data retrieved"
}
```

#### GET /api/v1/admin/analytics/kpis?range=30d
```json
{
  "data": {
    "kpis": [
      {
        "id": "new-users",
        "label": "New Users",
        "value": 1245,
        "changeVsLastMonth": "+18%"
      },
      {
        "id": "active-users",
        "label": "Active Users",
        "value": 8932,
        "changeVsLastMonth": "+12%"
      },
      {
        "id": "total-donations",
        "label": "Total Donations",
        "value": 145000,
        "changeVsLastMonth": "+25%"
      },
      {
        "id": "campaign-completions",
        "label": "Campaigns Completed",
        "value": 34,
        "changeVsLastMonth": "+8%"
      },
      {
        "id": "engagement-rate",
        "label": "Engagement Rate",
        "value": "42.5%",
        "changeVsLastMonth": "+5%"
      }
    ]
  },
  "message": "KPI data retrieved"
}
```

#### GET /api/v1/admin/analytics/weekly?range=30d
```json
{
  "data": {
    "rows": [
      {
        "weekLabel": "May 18-24",
        "visits": 45230,
        "newUsers": 312,
        "donations": 28500
      },
      {
        "weekLabel": "May 11-17",
        "visits": 42100,
        "newUsers": 289,
        "donations": 25300
      },
      {
        "weekLabel": "May 4-10",
        "visits": 38900,
        "newUsers": 256,
        "donations": 22100
      },
      {
        "weekLabel": "Apr 27-May 3",
        "visits": 35600,
        "newUsers": 234,
        "donations": 19200
      }
    ]
  },
  "message": "Weekly analytics retrieved"
}
```

#### GET /api/v1/admin/audit-logs?page=1&perPage=20
```json
{
  "data": [
    {
      "id": "log-1001",
      "action": "user_created",
      "user": {
        "id": "user-123",
        "name": "John Admin",
        "email": "admin@jod.com"
      },
      "at": "2025-05-25T14:30:00Z",
      "entityType": "User",
      "entityId": "user-456",
      "metadata": {
        "email": "newuser@example.com",
        "role": "general"
      }
    },
    {
      "id": "log-1002",
      "action": "organization_verified",
      "user": {
        "id": "user-123",
        "name": "John Admin",
        "email": "admin@jod.com"
      },
      "at": "2025-05-25T13:45:00Z",
      "entityType": "Organization",
      "entityId": "org-001",
      "metadata": {
        "organizationName": "Help Foundation",
        "status": "verified"
      }
    },
    {
      "id": "log-1003",
      "action": "post_approved",
      "user": {
        "id": "user-123",
        "name": "John Admin",
        "email": "admin@jod.com"
      },
      "at": "2025-05-25T12:20:00Z",
      "entityType": "Post",
      "entityId": "post-789",
      "metadata": {
        "title": "Help needed for flood relief",
        "organizationName": "Help Foundation"
      }
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 1542,
    "lastPage": 78
  },
  "message": "Audit logs retrieved"
}
```

---

### 3. ADMIN USERS

#### GET /api/v1/admin/users?page=1&perPage=20&filter.status=active&sort=-createdAt
```json
{
  "data": [
    {
      "id": "user-123",
      "name": "John Admin",
      "email": "admin@jod.com",
      "phone": "+962791234567",
      "role": "admin",
      "status": "active",
      "postsCount": 0,
      "reportsCount": 5,
      "createdAt": "2024-01-15T10:30:00Z",
      "lastActiveAt": "2025-05-25T14:22:00Z"
    },
    {
      "id": "user-456",
      "name": "Ahmed Mohammed",
      "email": "ahmed@example.com",
      "phone": "+962791234568",
      "role": "volunteer",
      "status": "active",
      "postsCount": 8,
      "reportsCount": 2,
      "createdAt": "2025-02-10T09:15:00Z",
      "lastActiveAt": "2025-05-24T16:45:00Z"
    },
    {
      "id": "user-789",
      "name": "Fatima Hassan",
      "email": "fatima@example.com",
      "phone": "+962791234569",
      "role": "donor",
      "status": "active",
      "postsCount": 0,
      "reportsCount": 0,
      "createdAt": "2025-03-05T14:20:00Z",
      "lastActiveAt": "2025-05-25T10:30:00Z"
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 15420,
    "lastPage": 771
  },
  "message": "Users retrieved successfully"
}
```

#### POST /api/v1/admin/users - Request Body
```json
{
  "name": "New User",
  "email": "newuser@example.com",
  "phone": "+962791234570",
  "role": "volunteer",
  "status": "active",
  "password": "SecurePassword123!"
}
```

#### POST /api/v1/admin/users - Response
```json
{
  "data": {
    "id": "user-999",
    "name": "New User",
    "email": "newuser@example.com",
    "phone": "+962791234570",
    "role": "volunteer",
    "status": "active",
    "createdAt": "2025-05-25T15:00:00Z"
  },
  "message": "User created successfully"
}
```

#### GET /api/v1/admin/users/{userId}
```json
{
  "data": {
    "id": "user-123",
    "name": "John Admin",
    "email": "admin@jod.com",
    "phone": "+962791234567",
    "role": "admin",
    "status": "active",
    "postsCount": 0,
    "reportsCount": 5,
    "createdAt": "2024-01-15T10:30:00Z",
    "lastActiveAt": "2025-05-25T14:22:00Z"
  },
  "message": "User retrieved successfully"
}
```

#### PATCH /api/v1/admin/users/{userId} - Request Body
```json
{
  "name": "John Updated Admin",
  "email": "admin@jod.com",
  "phone": "+962791234567",
  "status": "active"
}
```

#### PATCH /api/v1/admin/users/{userId}/status - Request Body
```json
{
  "status": "inactive"
}
```

#### PATCH /api/v1/admin/users/{userId}/password - Request Body
```json
{
  "newPassword": "NewSecurePassword456!",
  "confirmPassword": "NewSecurePassword456!"
}
```

---

### 4. ADMIN ORGANIZATIONS

#### GET /api/v1/admin/organizations?page=1&perPage=20&filter.status=active
```json
{
  "data": [
    {
      "id": "org-001",
      "name": "Help Foundation",
      "email": "contact@helpfoundation.org",
      "phone": "+962796543210",
      "location": "Amman, Jordan",
      "verificationStatus": "verified",
      "status": "active",
      "campaignsCount": 12,
      "postsCount": 45,
      "activeVolunteersCount": 23,
      "activityScore": 8.5,
      "createdAt": "2023-06-15T08:00:00Z",
      "lastActiveAt": "2025-05-25T13:45:00Z"
    },
    {
      "id": "org-002",
      "name": "Education Initiative",
      "email": "info@educationinitiative.org",
      "phone": "+962796543211",
      "location": "Zarqa, Jordan",
      "verificationStatus": "verified",
      "status": "active",
      "campaignsCount": 8,
      "postsCount": 32,
      "activeVolunteersCount": 15,
      "activityScore": 7.8,
      "createdAt": "2023-09-20T10:30:00Z",
      "lastActiveAt": "2025-05-24T14:20:00Z"
    },
    {
      "id": "org-003",
      "name": "Tech for Good",
      "email": "hello@techforgood.org",
      "phone": "+962796543212",
      "location": "Irbid, Jordan",
      "verificationStatus": "unverified",
      "status": "active",
      "campaignsCount": 3,
      "postsCount": 8,
      "activeVolunteersCount": 5,
      "activityScore": 5.2,
      "createdAt": "2025-01-10T11:15:00Z",
      "lastActiveAt": "2025-05-22T09:30:00Z"
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 342,
    "lastPage": 18
  },
  "message": "Organizations retrieved successfully"
}
```

#### GET /api/v1/admin/organizations/{organizationId}
```json
{
  "data": {
    "id": "org-001",
    "name": "Help Foundation",
    "email": "contact@helpfoundation.org",
    "phone": "+962796543210",
    "location": "Amman, Jordan",
    "verificationStatus": "verified",
    "status": "active",
    "campaignsCount": 12,
    "postsCount": 45,
    "activeVolunteersCount": 23,
    "activityScore": 8.5,
    "createdAt": "2023-06-15T08:00:00Z",
    "lastActiveAt": "2025-05-25T13:45:00Z",
    "organizationType": "ngo",
    "registrationNumber": "NGO-2023-001",
    "establishmentDate": "2020-03-15",
    "shortAddress": "123 Help Street, Amman",
    "description": "Dedicated to providing humanitarian aid and support to those in need",
    "licenseDocumentName": "license_2023.pdf",
    "delegationDocumentName": "delegation_2023.pdf",
    "ownerFullName": "Sarah Ahmed",
    "ownerEmail": "sarah@helpfoundation.org",
    "ownerPhone": "+962791234567",
    "website": "https://helpfoundation.org",
    "socialMedia": {
      "facebook": "facebook.com/helpfoundation",
      "twitter": "@helpfoundation",
      "instagram": "helpfoundation"
    },
    "acceptedAt": "2023-06-20T12:00:00Z"
  },
  "message": "Organization retrieved successfully"
}
```

#### PATCH /api/v1/admin/organizations/{organizationId} - Request Body
```json
{
  "name": "Help Foundation Updated",
  "email": "newemail@helpfoundation.org",
  "phone": "+962796543210",
  "location": "Amman, Jordan",
  "shortAddress": "456 Help Street, Amman",
  "description": "Updated description of the organization"
}
```

#### PATCH /api/v1/admin/organizations/{organizationId}/status - Request Body
```json
{
  "status": "inactive"
}
```

#### PATCH /api/v1/admin/organizations/{organizationId}/verification - Request Body
```json
{
  "verificationStatus": "verified"
}
```

#### POST /api/v1/admin/organizations/{organizationId}/accept - Response
```json
{
  "data": {
    "id": "org-003",
    "name": "Tech for Good",
    "status": "active",
    "verificationStatus": "verified",
    "acceptedAt": "2025-05-25T15:30:00Z"
  },
  "message": "Organization accepted successfully"
}
```

---

### 5. ADMIN POST MODERATION

#### GET /api/v1/admin/review/posts?page=1&perPage=20&filter.status=pending
```json
{
  "data": [
    {
      "id": "post-001",
      "title": "Emergency flood relief needed",
      "summary": "Our area has been hit by severe flooding. We urgently need supplies and volunteers.",
      "organizationName": "Help Foundation",
      "authorName": "Ahmed Hassan",
      "location": "Amman",
      "submittedAt": "2025-05-25T14:00:00Z",
      "publishedAt": null,
      "status": "pending",
      "type": "help_request",
      "reviewedBy": null,
      "rejectionReason": null
    },
    {
      "id": "post-002",
      "title": "Volunteer opportunity: Teacher needed",
      "summary": "We are looking for volunteer teachers for summer program",
      "organizationName": "Education Initiative",
      "authorName": "Fatima Ahmed",
      "location": "Zarqa",
      "submittedAt": "2025-05-25T13:30:00Z",
      "publishedAt": null,
      "status": "pending",
      "type": "job_opportunity",
      "reviewedBy": null,
      "rejectionReason": null
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 45,
    "lastPage": 3
  },
  "message": "Posts retrieved for review"
}
```

#### POST /api/v1/admin/review/posts/{postId}/approve - Request Body
```json
{
  "note": "Approved - content meets guidelines"
}
```

#### POST /api/v1/admin/review/posts/{postId}/reject - Request Body
```json
{
  "reason": "Content violates community guidelines regarding sensitive topics"
}
```

---

### 6. ADMIN CAMPAIGN MODERATION

#### GET /api/v1/admin/review/campaigns?page=1&perPage=20&filter.status=pending
```json
{
  "data": [
    {
      "id": "campaign-001",
      "title": "Emergency Medical Fund",
      "summary": "Raising funds for emergency medical treatment for underprivileged children",
      "organizationName": "Help Foundation",
      "managerName": "Sarah Ahmed",
      "location": "Amman",
      "category": "health",
      "goalAmount": 50000,
      "raisedAmount": 0,
      "beneficiariesCount": 150,
      "startDate": "2025-06-01",
      "endDate": "2025-08-31",
      "submittedAt": "2025-05-25T12:00:00Z",
      "status": "pending",
      "reviewedBy": null,
      "rejectionReason": null
    },
    {
      "id": "campaign-002",
      "title": "Back to School Initiative",
      "summary": "Providing school supplies and uniforms for 500 students",
      "organizationName": "Education Initiative",
      "managerName": "Fatima Mohammed",
      "location": "Zarqa",
      "category": "education",
      "goalAmount": 30000,
      "raisedAmount": 0,
      "beneficiariesCount": 500,
      "startDate": "2025-07-01",
      "endDate": "2025-08-15",
      "submittedAt": "2025-05-24T15:30:00Z",
      "status": "pending",
      "reviewedBy": null,
      "rejectionReason": null
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 23,
    "lastPage": 2
  },
  "message": "Campaigns retrieved for review"
}
```

---

### 7. ADMIN REPORTS

#### GET /api/v1/admin/reports?page=1&perPage=20&filter.status=new
```json
{
  "data": [
    {
      "id": "report-001",
      "title": "Suspicious campaign activity",
      "description": "Campaign claims are not matching actual activities",
      "status": "new",
      "severity": "high",
      "entityType": "campaign",
      "entityId": "campaign-789",
      "organizationName": "Unknown Organization",
      "reporterName": "Anonymous User",
      "createdAt": "2025-05-25T14:30:00Z",
      "assignee": null,
      "timeline": [
        {
          "status": "new",
          "timestamp": "2025-05-25T14:30:00Z",
          "note": "Report submitted"
        }
      ],
      "evidence": [
        {
          "type": "url",
          "content": "https://example.com/campaign-update"
        },
        {
          "type": "text",
          "content": "Campaign shows donations but no activity updates"
        }
      ]
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 3,
    "lastPage": 1
  },
  "message": "Reports retrieved successfully"
}
```

#### POST /api/v1/admin/reports/{reportId}/claim - Request Body
```json
{
  "assigneeId": "user-123"
}
```

#### POST /api/v1/admin/reports/{reportId}/request-info - Request Body
```json
{
  "note": "Can you provide more details about the timeline of events?"
}
```

#### POST /api/v1/admin/reports/{reportId}/close - Request Body
```json
{
  "note": "Report investigated. No violations found after reviewing all evidence."
}
```

---

### 8. ADMIN NOTIFICATIONS

#### GET /api/v1/admin/notifications?page=1&perPage=20
```json
{
  "data": [
    {
      "id": "notification-001",
      "mailbox": "sent",
      "title": "New Campaign Notification",
      "body": "A new campaign has been submitted for review",
      "category": "campaign",
      "recipientScope": "all",
      "recipientLabel": "All users",
      "priority": "normal",
      "status": "sent",
      "createdAt": "2025-05-25T10:00:00Z",
      "sentAt": "2025-05-25T10:05:00Z",
      "readAt": null,
      "referenceLabel": "Emergency Medical Fund",
      "referencePath": "/admin/campaigns/campaign-001",
      "createdBy": "John Admin"
    },
    {
      "id": "notification-002",
      "mailbox": "inbox",
      "title": "Post Approval Alert",
      "body": "Your post has been approved and published",
      "category": "post",
      "recipientScope": "organizations",
      "recipientLabel": "Organization staff",
      "priority": "high",
      "status": "unread",
      "createdAt": "2025-05-25T09:30:00Z",
      "sentAt": "2025-05-25T09:35:00Z",
      "readAt": null,
      "referenceLabel": "Help Request Post",
      "referencePath": "/posts/post-001",
      "createdBy": "System"
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 156,
    "lastPage": 8
  },
  "message": "Notifications retrieved"
}
```

#### POST /api/v1/admin/notifications - Request Body
```json
{
  "title": "Platform Maintenance Scheduled",
  "body": "The platform will undergo maintenance on Friday at 10 PM for 2 hours.",
  "category": "system",
  "recipientScope": "all",
  "recipientLabel": "All users and organizations",
  "priority": "high"
}
```

#### PATCH /api/v1/admin/notifications/{notificationId}/read-state - Request Body
```json
{
  "status": "read"
}
```

---

### 9. ADMIN BADGES

#### GET /api/v1/admin/badges?page=1&perPage=20
```json
{
  "data": [
    {
      "id": "badge-001",
      "name": "Top Donor",
      "description": "Given to users who have donated over $1000",
      "criteria": "total_donations >= 1000",
      "iconName": "star",
      "isActive": true,
      "createdAt": "2024-01-15T10:00:00Z"
    },
    {
      "id": "badge-002",
      "name": "Volunteer Champion",
      "description": "Given to volunteers with 50+ hours",
      "criteria": "volunteer_hours >= 50",
      "iconName": "heart",
      "isActive": true,
      "createdAt": "2024-02-20T14:30:00Z"
    },
    {
      "id": "badge-003",
      "name": "Organization Leader",
      "description": "For organizations with 5+ successful campaigns",
      "criteria": "successful_campaigns >= 5",
      "iconName": "medal",
      "isActive": true,
      "createdAt": "2024-03-10T09:15:00Z"
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 12,
    "lastPage": 1
  },
  "message": "Badges retrieved successfully"
}
```

#### POST /api/v1/admin/badges - Request Body
```json
{
  "name": "New Badge",
  "description": "Badge description",
  "criteria": "specific_criteria",
  "iconName": "award",
  "isActive": true
}
```

---

### 10. ADMIN ARTICLES

#### GET /api/v1/admin/articles?page=1&perPage=20&filter.status=published
```json
{
  "data": [
    {
      "id": "article-001",
      "title": "How to Start a Successful Campaign",
      "slug": "how-to-start-successful-campaign",
      "excerpt": "Tips and best practices for launching your fundraising campaign",
      "status": "published",
      "publishedAt": "2025-05-20T10:00:00Z",
      "createdAt": "2025-05-18T14:30:00Z",
      "authorName": "John Admin"
    },
    {
      "id": "article-002",
      "title": "Volunteer Safety Guidelines",
      "slug": "volunteer-safety-guidelines",
      "excerpt": "Important safety protocols every volunteer should follow",
      "status": "published",
      "publishedAt": "2025-05-15T09:00:00Z",
      "createdAt": "2025-05-13T11:20:00Z",
      "authorName": "Sarah Admin"
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 8,
    "lastPage": 1
  },
  "message": "Articles retrieved successfully"
}
```

#### POST /api/v1/admin/articles - Request Body
```json
{
  "title": "New Article Title",
  "slug": "new-article-slug",
  "excerpt": "Short description of the article",
  "authorName": "Author Name",
  "status": "draft"
}
```

---

### 11. ADMIN PLATFORM SETTINGS

#### GET /api/v1/admin/platform-settings
```json
{
  "data": {
    "siteName": "JOD - Jordan Opportunities & Donations",
    "allowNewPosts": true,
    "requirePostReview": true
  },
  "message": "Platform settings retrieved"
}
```

#### PATCH /api/v1/admin/platform-settings - Request Body
```json
{
  "siteName": "JOD - Jordan Opportunities & Donations",
  "allowNewPosts": true,
  "requirePostReview": true
}
```

---

### 12. ORGANIZATION OVERVIEW

#### GET /api/v1/org/overview?view=owner
```json
{
  "data": {
    "stats": [
      {
        "id": "total-campaigns",
        "label": "Active Campaigns",
        "value": 5,
        "hint": "2 reaching goal"
      },
      {
        "id": "total-posts",
        "label": "Published Posts",
        "value": 23,
        "hint": "8 this month"
      },
      {
        "id": "total-donors",
        "label": "Donors",
        "value": 342,
        "hint": "52 new this month"
      },
      {
        "id": "total-raised",
        "label": "Funds Raised",
        "value": 125000,
        "hint": "+$23,000 this month"
      }
    ],
    "activity": [
      {
        "id": "activity-1",
        "title": "New donation received",
        "detail": "Ahmed Mohammed donated $500 to Medical Fund",
        "at": "2025-05-25T14:15:00Z"
      },
      {
        "id": "activity-2",
        "title": "Post published",
        "detail": "Update post published to Back to School campaign",
        "at": "2025-05-25T10:30:00Z"
      }
    ]
  },
  "message": "Organization overview retrieved"
}
```

---

### 13. ORGANIZATION CAMPAIGNS

#### GET /api/v1/org/campaigns?page=1&perPage=20&filter.status=active
```json
{
  "data": [
    {
      "id": "campaign-001",
      "title": "Emergency Medical Fund",
      "summary": "Raising funds for emergency medical treatment",
      "category": "health",
      "status": "active",
      "location": "Amman",
      "goalAmount": 50000,
      "raisedAmount": 35000,
      "beneficiariesCount": 150,
      "donorsCount": 234,
      "applicantsCount": 45,
      "startDate": "2025-06-01",
      "endDate": "2025-08-31",
      "createdAt": "2025-05-20T10:00:00Z",
      "updatedAt": "2025-05-25T14:30:00Z",
      "closedAt": null,
      "closedReason": null
    },
    {
      "id": "campaign-002",
      "title": "Back to School Initiative",
      "summary": "Providing school supplies and uniforms",
      "category": "education",
      "status": "active",
      "location": "Zarqa",
      "goalAmount": 30000,
      "raisedAmount": 18500,
      "beneficiariesCount": 500,
      "donorsCount": 167,
      "applicantsCount": 78,
      "startDate": "2025-07-01",
      "endDate": "2025-08-15",
      "createdAt": "2025-05-15T09:00:00Z",
      "updatedAt": "2025-05-24T11:45:00Z",
      "closedAt": null,
      "closedReason": null
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 12,
    "lastPage": 1
  },
  "message": "Campaigns retrieved successfully"
}
```

#### POST /api/v1/org/campaigns - Request Body
```json
{
  "title": "New Campaign",
  "summary": "Campaign description",
  "category": "health",
  "status": "draft",
  "location": "Amman",
  "goalAmount": 50000,
  "beneficiariesCount": 200,
  "startDate": "2025-06-15",
  "endDate": "2025-08-31"
}
```

#### POST /api/v1/org/campaigns/{campaignId}/close - Request Body
```json
{
  "reason": "Campaign goal has been reached and all funds distributed"
}
```

---

### 14. ORGANIZATION POSTS

#### GET /api/v1/org/posts?page=1&perPage=20&filter.status=published
```json
{
  "data": [
    {
      "id": "post-001",
      "title": "Medical Fund Update",
      "summary": "Update on how funds are being used",
      "type": "campaign_update",
      "status": "published",
      "authorName": "Sarah Ahmed",
      "location": "Amman",
      "campaignTitle": "Emergency Medical Fund",
      "createdAt": "2025-05-20T10:00:00Z",
      "updatedAt": "2025-05-25T14:30:00Z",
      "publishedAt": "2025-05-20T10:15:00Z",
      "viewsCount": 1245,
      "reactionsCount": 87,
      "applicationsCount": 12
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 23,
    "lastPage": 2
  },
  "message": "Posts retrieved successfully"
}
```

#### POST /api/v1/org/posts - Request Body
```json
{
  "title": "Campaign Update",
  "summary": "Latest updates on our campaign",
  "type": "campaign_update",
  "status": "draft",
  "authorName": "Author Name",
  "location": "Amman",
  "campaignTitle": "Campaign Name"
}
```

#### POST /api/v1/org/posts/{postId}/publish - Response
```json
{
  "data": {
    "id": "post-001",
    "title": "Medical Fund Update",
    "status": "published",
    "publishedAt": "2025-05-25T15:00:00Z"
  },
  "message": "Post published successfully"
}
```

---

### 15. ORGANIZATION DONORS

#### GET /api/v1/org/donors?page=1&perPage=20&filter.campaignId=campaign-001
```json
{
  "data": [
    {
      "id": "donor-001",
      "name": "Ahmed Mohammed",
      "email": "ahmed@example.com",
      "phone": "+962791234567",
      "campaignTitle": "Emergency Medical Fund",
      "amountOrType": "$500",
      "donatedAt": "2025-05-25T14:30:00Z",
      "city": "Amman",
      "source": "website",
      "paymentMethod": "credit_card",
      "campaignRef": "REF-2025-001",
      "assignedTo": "Sarah Ahmed",
      "internalNotes": "VIP donor - send thank you gift"
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 234,
    "lastPage": 12
  },
  "message": "Donors retrieved successfully"
}
```

#### POST /api/v1/org/donors - Request Body
```json
{
  "name": "Donor Name",
  "email": "donor@example.com",
  "phone": "+962791234567",
  "campaignTitle": "Campaign Name",
  "amountOrType": "$1000",
  "city": "Amman"
}
```

---

### 16. ORGANIZATION APPLICANTS

#### GET /api/v1/org/applicants?page=1&perPage=20
```json
{
  "data": [
    {
      "id": "applicant-001",
      "name": "Fatima Hassan",
      "email": "fatima@example.com",
      "phone": "+962791234568",
      "campaignTitle": "Back to School",
      "amountOrType": "Approved",
      "donatedAt": "2025-05-22T10:00:00Z",
      "city": "Zarqa",
      "source": "internal",
      "paymentMethod": null,
      "campaignRef": "APP-2025-001",
      "assignedTo": "Manager Name",
      "internalNotes": "Pending documents verification"
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 78,
    "lastPage": 4
  },
  "message": "Applicants retrieved successfully"
}
```

---

### 17. ORGANIZATION STAFF

#### GET /api/v1/org/staff?page=1&perPage=20&filter.role=manager
```json
{
  "data": [
    {
      "id": "staff-001",
      "name": "Sarah Ahmed",
      "email": "sarah@helpfoundation.org",
      "role": "manager",
      "invitedAt": "2025-03-15T10:00:00Z"
    },
    {
      "id": "staff-002",
      "name": "Ahmed Hassan",
      "email": "ahmed@helpfoundation.org",
      "role": "editor",
      "invitedAt": "2025-04-20T14:30:00Z"
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 8,
    "lastPage": 1
  },
  "message": "Staff members retrieved successfully"
}
```

#### POST /api/v1/org/staff - Request Body
```json
{
  "name": "New Staff Member",
  "email": "newstaff@helpfoundation.org",
  "role": "editor"
}
```

---

### 18. ORGANIZATION ROLES

#### GET /api/v1/org/roles?page=1&perPage=20
```json
{
  "data": [
    {
      "id": "role-001",
      "role": "Editor",
      "description": "Can create and edit campaigns and posts",
      "permissions": [
        "org.campaigns.view",
        "org.campaigns.create",
        "org.campaigns.update",
        "org.posts.view",
        "org.posts.create",
        "org.posts.update",
        "org.posts.publish"
      ],
      "updatedAt": "2025-05-20T10:00:00Z",
      "isActive": true,
      "isSystem": false,
      "membersCount": 3
    },
    {
      "id": "role-002",
      "role": "Viewer",
      "description": "Can only view content",
      "permissions": [
        "org.campaigns.view",
        "org.posts.view",
        "org.reports.view"
      ],
      "updatedAt": "2024-01-15T10:00:00Z",
      "isActive": true,
      "isSystem": true,
      "membersCount": 2
    }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 4,
    "lastPage": 1
  },
  "message": "Roles retrieved successfully"
}
```

#### POST /api/v1/org/roles - Request Body
```json
{
  "role": "New Role",
  "description": "Role description",
  "permissions": [
    "org.campaigns.view",
    "org.posts.view"
  ],
  "isActive": true
}
```

---

### 19. ORGANIZATION PERMISSIONS CATALOG

#### GET /api/v1/org/permissions/catalog
```json
{
  "data": [
    {
      "group": "Campaigns",
      "permissions": [
        {
          "key": "org.campaigns.view",
          "label": "View Campaigns",
          "description": "Can view organization campaigns"
        },
        {
          "key": "org.campaigns.create",
          "label": "Create Campaigns",
          "description": "Can create new campaigns"
        },
        {
          "key": "org.campaigns.update",
          "label": "Edit Campaigns",
          "description": "Can edit existing campaigns"
        },
        {
          "key": "org.campaigns.delete",
          "label": "Delete Campaigns",
          "description": "Can delete campaigns"
        }
      ]
    },
    {
      "group": "Posts",
      "permissions": [
        {
          "key": "org.posts.view",
          "label": "View Posts",
          "description": "Can view organization posts"
        },
        {
          "key": "org.posts.create",
          "label": "Create Posts",
          "description": "Can create new posts"
        },
        {
          "key": "org.posts.update",
          "label": "Edit Posts",
          "description": "Can edit existing posts"
        },
        {
          "key": "org.posts.publish",
          "label": "Publish Posts",
          "description": "Can publish posts"
        },
        {
          "key": "org.posts.delete",
          "label": "Delete Posts",
          "description": "Can delete posts"
        }
      ]
    },
    {
      "group": "Staff",
      "permissions": [
        {
          "key": "org.staff.view",
          "label": "View Staff",
          "description": "Can view organization staff"
        },
        {
          "key": "org.staff.manage",
          "label": "Manage Staff",
          "description": "Can invite, remove, and manage staff"
        }
      ]
    },
    {
      "group": "Donors & Applicants",
      "permissions": [
        {
          "key": "org.donors.view",
          "label": "View Donors",
          "description": "Can view donor information"
        },
        {
          "key": "org.donors.manage",
          "label": "Manage Donors",
          "description": "Can manage donor records"
        },
        {
          "key": "org.applicants.view",
          "label": "View Applicants",
          "description": "Can view applicant information"
        },
        {
          "key": "org.applicants.manage",
          "label": "Manage Applicants",
          "description": "Can manage applicant records"
        }
      ]
    },
    {
      "group": "Reports",
      "permissions": [
        {
          "key": "org.reports.view",
          "label": "View Reports",
          "description": "Can view organization reports"
        }
      ]
    },
    {
      "group": "Notifications",
      "permissions": [
        {
          "key": "org.notifications.view",
          "label": "View Notifications",
          "description": "Can view notifications"
        },
        {
          "key": "org.notifications.send",
          "label": "Send Notifications",
          "description": "Can send notifications to staff"
        }
      ]
    },
    {
      "group": "Settings",
      "permissions": [
        {
          "key": "org.settings.view",
          "label": "View Settings",
          "description": "Can view organization settings"
        },
        {
          "key": "org.settings.update",
          "label": "Update Settings",
          "description": "Can update organization settings"
        }
      ]
    }
  ],
  "message": "Permissions catalog retrieved"
}
```

---

## QUERY PARAMETER EXAMPLES

### Pagination
- `?page=1&perPage=20` - Page 1, 20 items per page
- `?page=2&perPage=50` - Page 2, 50 items per page
- `?page=1&perPage=100` - Page 1, max 100 items

### Filters
- `?filter.status=active` - Filter by status
- `?filter.search=campaign` - Search filter
- `?filter.category=health&filter.status=active` - Multiple filters
- `?filter.date=today` - Date range filter
- `?filter.location=Amman` - Location filter

### Sorting
- `?sort=name` - Sort ascending by name
- `?sort=-createdAt` - Sort descending by creation date
- `?sort=updatedAt` - Sort ascending by update date
- `?sort=-status` - Sort descending by status

### Complex Query Example
```
GET /api/v1/admin/organizations?page=1&perPage=20&filter.status=active&filter.location=Amman&sort=-createdAt
```

---

## ERROR RESPONSE EXAMPLES

### 400 Bad Request
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required"],
    "password": ["The password must be at least 8 characters"]
  }
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "message": "You do not have permission to perform this action"
}
```

### 404 Not Found
```json
{
  "message": "Resource not found"
}
```

### 422 Unprocessable Entity
```json
{
  "message": "Cannot transition from draft to archived status"
}
```

---

## AUTHENTICATION

All endpoints require:
```
Authorization: Bearer {token}
Accept: application/json
```

Get token from login endpoint (not part of this documentation).

