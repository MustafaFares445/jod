# JOD Dashboard API Contract Guide

**Version:** 1.0  
**Base URL:** `http://localhost/api/v1`  
**Authentication:** Bearer token (Sanctum)  
**Last Updated:** May 25, 2026

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication & Authorization](#authentication--authorization)
3. [Response Contract](#response-contract)
4. [Error Handling](#error-handling)
5. [User Flow Mappings](#user-flow-mappings)
6. [Detailed Endpoint Documentation](#detailed-endpoint-documentation)

---

## Getting Started

### Base Setup
- All requests require `Authorization: Bearer {token}` header
- All endpoints are prefixed with `/api/v1`
- Content-Type should be `application/json` for POST/PATCH requests
- Accept header should be `application/json`

### Environment Configuration
Frontend should configure these variables in environment setup:
```
API_BASE_URL = http://localhost/api/v1
AUTH_HEADER_NAME = Authorization
AUTH_SCHEME = Bearer
```

---

## Authentication & Authorization

### Login Flow (Assumed)
The token obtained from authentication endpoint should be stored and included in all subsequent requests.

**Store token in:**
- Local storage (with expiration handling)
- Session storage (for single-session use)
- Memory (cleared on refresh)

**Token Refresh Strategy:**
- Check token expiration before each request
- Implement 401 intercept to refresh or redirect to login
- Add retry logic for failed requests due to expired token

### Permission-Based Access
After authentication, frontend fetches permissions to gate features:
- **GET `/me/permissions`** - Get permission set for current user
- Use `permissions.flat` object to check: `userPermissions['dashboard.view']`
- Render menu items, buttons, and features based on granted permissions

---

## Response Contract

### Success Response - Collection (Paginated)
```json
{
  "data": [
    { "id": "123", "name": "Item 1" },
    { "id": "124", "name": "Item 2" }
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 150,
    "lastPage": 8
  },
  "message": "Items retrieved successfully"
}
```

**Frontend Usage:**
- Display `data` array in list/table components
- Use `meta` for pagination controls (show page X of `lastPage`)
- Show total count from `meta.total` in header

### Success Response - Single Resource
```json
{
  "data": {
    "id": "123",
    "name": "Item Name",
    "createdAt": "2025-05-20T10:00:00Z"
  },
  "message": "Item retrieved successfully"
}
```

**Frontend Usage:**
- Use `data` object to populate detail forms or display panels
- `message` can be shown as confirmation toast

### Success Response - Mutation (Create/Update)
```json
{
  "data": {
    "id": "125",
    "name": "New Item",
    "createdAt": "2025-05-25T14:30:00Z"
  },
  "message": "Item created successfully"
}
```

**Frontend Usage:**
- Display `message` as success notification
- Refresh list with new data or add to local state
- For forms, use returned `data` to update local state

### Success Response - Delete (204 No Content)
```
HTTP/1.1 204 No Content
```

**Frontend Usage:**
- No response body expected
- Show "Item deleted" confirmation
- Remove item from list

### Error Response
```json
{
  "error": "Unauthorized",
  "message": "User does not have permission to access this resource",
  "code": 403
}
```

Or for validation errors:
```json
{
  "errors": {
    "email": ["Email is already in use"],
    "password": ["Password must be at least 8 characters"]
  },
  "message": "Validation failed"
}
```

**Frontend Usage:**
- Extract `errors` object and map to form fields
- Display error messages below corresponding inputs
- Show `message` as error toast for general errors

---

## Error Handling

### HTTP Status Codes

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 200 | Success | Process response normally |
| 201 | Created | Show success, refresh list |
| 204 | No Content (delete) | Show success, remove from list |
| 400 | Bad Request | Show validation errors in form |
| 401 | Unauthorized | Redirect to login |
| 403 | Forbidden | Show permission denied message |
| 404 | Not Found | Show "Item not found" and go back |
| 422 | Validation Failed | Show field-specific errors |
| 500 | Server Error | Show generic error, retry option |

### Retry Strategy
- Implement exponential backoff for 5xx errors
- Auto-retry 3 times with delays: 1s, 2s, 4s
- Don't retry on 4xx errors (except 408, 429)
- Cancel retries if user navigates away

---

## User Flow Mappings

### Flow 1: Admin Dashboard Initialization

**Frontend Location:** `admin-overview/admin-overview-page.tsx`

**Sequence:**
1. User logs in (auth endpoint)
2. **GET `/me`** → Display user profile in topbar
3. **GET `/me/permissions`** → Gate menu items and features
4. **GET `/me/dashboard-context`** → Load dashboard counters (optional, else call next 2)
5. **GET `/admin/overview`** → Load KPI cards and activity feed
6. **GET `/admin/analytics/kpis?range=30d`** → Load analytics cards

**Response Mapping:**
```
Admin Overview Page
├── Topbar
│   └── /me → name, email, userType
├── Sidebar
│   └── /me/permissions → visible menu items
├── KPI Cards Row
│   └── /admin/overview → stats array
├── Activity Feed
│   └── /admin/overview → activity array
└── Analytics Section
    └── /admin/analytics/kpis → kpi array
```

---

### Flow 2: Users Management (Admin)

**Frontend Location:** `users-management/users-management-page.tsx`

#### 2a. View Users List
**Sequence:**
1. Page loads with default filters
2. **GET `/admin/users?page=1&perPage=20&sort=-createdAt`** → Load users table
3. User applies filters/sorts
4. **GET `/admin/users?page={page}&perPage={perPage}&filter.status={status}&sort={field}`** → Reload table

**Query Parameter Mapping (Frontend → Backend):**
| Frontend Filter | Backend Parameter | Example |
|---|---|---|
| Status dropdown | `filter.status` | `filter.status=active` |
| Role dropdown | `filter.role` | `filter.role=general` |
| Search input | `filter.search` | `filter.search=john` |
| Sort dropdown | `sort` | `sort=-createdAt` or `sort=name` |

**Table Columns to Display:**
- id → unique identifier (hide from UI)
- name → User Name
- email → Email Address
- phone → Phone Number
- role → Role (badge)
- status → Status (badge: active/inactive)
- postsCount → Posts
- reportsCount → Reports
- createdAt → Joined Date (format: MM/DD/YYYY)
- lastActiveAt → Last Active (format: "X days ago" or exact date)

**Pagination:**
- Show "Page {currentPage} of {lastPage}"
- Disable "Previous" if `currentPage === 1`
- Disable "Next" if `currentPage === lastPage`
- Show "Showing X-Y of {total} items"

#### 2b. Create User
**Sequence:**
1. User clicks "Create User" button
2. Form opens with fields: name, email, phone, role, status
3. User fills form and clicks "Create"
4. **POST `/admin/users`** with body:
```json
{
  "name": "Ahmed Hassan",
  "email": "ahmed@example.com",
  "phone": "+962791234567",
  "role": "general",
  "status": "active"
}
```
5. On success (201):
   - Show "User created successfully" toast
   - Close form
   - Refresh users list OR add to top of current list

**Form Validation (Client-side):**
- Name: required, min 3 chars
- Email: required, valid email format
- Phone: required
- Role: required dropdown (general|volunteer|job_seeker|donor)
- Status: required dropdown (active|inactive)

#### 2c. View User Details
**Sequence:**
1. User clicks row or "View" button
2. **GET `/admin/users/{userId}`** → Load full user details
3. Display in detail modal/panel/page

**Response Fields to Display:**
- All fields from list
- Plus: document links, verification status, metadata

#### 2d. Update User
**Sequence:**
1. User opens user details
2. Clicks "Edit" button
3. Form pre-fills with current data from previous detail fetch
4. User modifies fields
5. Clicks "Save"
6. **PATCH `/admin/users/{userId}`** with modified fields
7. On success:
   - Show "User updated successfully" toast
   - Refresh details panel
   - Update list row if visible

**Example Request:**
```json
{
  "name": "Ahmed Hassan Updated",
  "phone": "+962799999999"
}
```

#### 2e. Change User Password
**Sequence:**
1. User opens user details
2. Clicks "Change Password" action
3. Dialog opens with: newPassword, confirmPassword
4. **PATCH `/admin/users/{userId}/password`**
```json
{
  "newPassword": "NewSecurePass123",
  "confirmPassword": "NewSecurePass123"
}
```
5. On success:
   - Show "Password updated successfully" toast
   - Close dialog

#### 2f. Update User Status
**Sequence:**
1. User clicks status badge or status toggle
2. Confirm action
3. **PATCH `/admin/users/{userId}/status`**
```json
{
  "status": "inactive"
}
```
4. Update badge color/text in UI

#### 2g. Delete User
**Sequence:**
1. User clicks delete/trash icon
2. Confirm dialog appears
3. **DELETE `/admin/users/{userId}`**
4. On success:
   - Show "User deleted successfully" toast
   - Remove from list
   - Close details panel if open

---

### Flow 3: Organizations Management (Admin)

**Frontend Location:** `organizations-management/organizations-management-page.tsx`

#### 3a. View Organizations List
**Sequence:**
1. **GET `/admin/organizations?page=1&perPage=20&sort=-createdAt`**
2. Display list with filters available

**Query Parameters:**
```
page=1
perPage=20
filter.verificationStatus=verified|unverified
filter.status=active|inactive
filter.location=string
filter.search=string
sort=name|-name|createdAt|-createdAt
sortBy=name_asc|name_desc|created_newest|created_oldest (frontend compat)
```

**Table Columns:**
- name → Organization Name
- email → Contact Email
- phone → Phone
- location → Location
- verificationStatus → Verification (badge: verified/unverified)
- status → Status (badge)
- campaignsCount → Campaigns
- postsCount → Posts
- activeVolunteersCount → Active Volunteers
- activityScore → Activity Score (metric/percentage)
- createdAt → Created
- lastActiveAt → Last Active

#### 3b. View Organization Details
**Sequence:**
1. User clicks organization row
2. **GET `/admin/organizations/{organizationId}`**
3. Display details page with all information:

**Detail Fields to Display:**
```
Basic Information:
- name, email, phone, location, verificationStatus, status

Legal Information:
- organizationType
- registrationNumber
- establishmentDate
- shortAddress
- description
- licenseDocumentName (show link)
- delegationDocumentName (show link)

Owner Information:
- ownerFullName
- ownerEmail
- ownerPhone

Web Presence:
- website (clickable link)
- socialMedia (links)

Acceptance:
- acceptedAt (timestamp if accepted)
```

#### 3c. Update Organization
**Sequence:**
1. User clicks "Edit" on details page
2. Form pre-fills with detail data
3. User modifies allowed fields
4. **PATCH `/admin/organizations/{organizationId}`**
```json
{
  "name": "Updated Org Name",
  "email": "org@example.com",
  "phone": "+962791234567",
  "description": "Updated description"
}
```
5. Refresh details display

#### 3d. Change Organization Status
**Sequence:**
1. Click status badge → toggle
2. **PATCH `/admin/organizations/{organizationId}/status`**
```json
{
  "status": "active"
}
```

#### 3e. Change Organization Verification
**Sequence:**
1. Click verification badge → toggle
2. **PATCH `/admin/organizations/{organizationId}/verification`**
```json
{
  "verificationStatus": "verified"
}
```

#### 3f. Accept Organization
**Sequence:**
1. Organization is pending (status=unverified)
2. User clicks "Accept" button
3. Confirm action
4. **POST `/admin/organizations/{organizationId}/accept`**
5. This sets: status=active, verificationStatus=verified, acceptedAt=now
6. Show confirmation and refresh

#### 3g. Delete Organization
**Sequence:**
1. User clicks delete action
2. Confirm dialog
3. **DELETE `/admin/organizations/{organizationId}`**
4. Remove from list and close details

---

### Flow 4: Post Review (Admin Moderation)

**Frontend Location:** `posts-review/posts-review-page.tsx`

#### 4a. List Posts for Review
**Sequence:**
1. **GET `/admin/review/posts?page=1&perPage=20&sort=-submittedAt`**

**Query Parameters:**
```
page, perPage
filter.status=pending|approved|rejected
filter.organizationId=string
filter.organizationName=string (fallback)
filter.type=help_request|job_opportunity|awareness|campaign_update
sort=title|-title|submittedAt|-submittedAt
sortBy=title_asc|title_desc|created_at_newest|created_at_oldest (compat)
```

**Table Columns:**
- title → Post Title
- summary → Summary (truncate to 100 chars)
- organizationName → Organization
- authorName → Author
- location → Location
- submittedAt → Submitted Date
- publishedAt → Published (if approved)
- status → Status (badge: pending/approved/rejected)
- type → Type (help_request, job_opportunity, etc.)
- reviewedBy → Reviewed By (user name if status !== pending)
- rejectionReason → (show if rejected)

#### 4b. View Post Details
**Sequence:**
1. User clicks post row
2. **GET `/admin/review/posts/{postId}`**
3. Display full post in modal/detail panel

**Fields to Display:**
- All table fields plus
- Full content/body
- Media (images, attachments)
- Metadata (views, reactions, reports)

#### 4c. Approve Post
**Sequence:**
1. User reviews post details
2. Clicks "Approve" button
3. Optional: enter note
4. **POST `/admin/review/posts/{postId}/approve`**
```json
{
  "note": "Content approved for publication"
}
```
5. Status changes to "approved" in list
6. Show success notification
7. Refresh list or update row

#### 4d. Reject Post
**Sequence:**
1. User clicks "Reject" button
2. Modal opens with required field: reason (min 8 chars)
3. **POST `/admin/review/posts/{postId}/reject`**
```json
{
  "reason": "Content violates community guidelines regarding appropriate language and respectful discourse"
}
```
4. Status changes to "rejected"
5. Show success notification
6. Refresh list

---

### Flow 5: Campaign Review (Admin Moderation)

**Frontend Location:** `campaigns-review/campaigns-review-page.tsx`

**Very similar to post review:**
- **GET `/admin/review/campaigns`** → List campaigns
- **GET `/admin/review/campaigns/{campaignId}`** → Details
- **POST `/admin/review/campaigns/{campaignId}/approve`** → Approve
- **POST `/admin/review/campaigns/{campaignId}/reject`** → Reject with reason

**Query Parameters:**
```
filter.status=pending|approved|rejected
filter.organizationId
filter.category=health|education|shelter|food|emergency
sort=title|-title|submittedAt|-submittedAt
```

**Table Columns:**
- title, summary, organizationName, managerName, location
- category (badge), goalAmount, raisedAmount, beneficiariesCount
- startDate, endDate, submittedAt, status

---

### Flow 6: Reports Management (Admin)

**Frontend Location:** `reports-management/reports-management-page.tsx`

#### 6a. List Reports
**Sequence:**
1. **GET `/admin/reports?page=1&perPage=20&sort=-createdAt`**

**Query Parameters:**
```
filter.status=new|in_progress|waiting_response|closed
filter.severity=low|medium|high|critical
filter.entityType=post|campaign|user|organization
sort=createdAt|-createdAt
```

**Table Columns:**
- title, description → Report Summary
- status → Status (badge: new/in_progress/waiting_response/closed)
- severity → Severity (badge: red/yellow/orange/purple)
- entityType → Type (what was reported)
- entityId → Reference ID
- organizationName → Organization
- reporterName → Reported By
- createdAt → Date
- assignee → Assigned To (user name or "-")

#### 6b. View Report Details
**Sequence:**
1. **GET `/admin/reports/{reportId}`**
2. Display full report with timeline and evidence

**Fields:**
- title, description, status, severity
- Reporter info, organization, entity reference
- timeline[] array with history
- evidence[] array with attachments
- assignee info

#### 6c. Claim Report
**Sequence:**
1. Click "Claim" button (for status=new)
2. Optional: select assignee from dropdown
3. **POST `/admin/reports/{reportId}/claim`**
```json
{
  "assigneeId": "user-456"
}
```
4. Status changes to "in_progress"
5. Assignee appears in details

#### 6d. Request Info
**Sequence:**
1. Click "Request Info" button (status must be in_progress)
2. Modal with text field: note
3. **POST `/admin/reports/{reportId}/request-info`**
```json
{
  "note": "Please provide additional evidence for this claim"
}
```
4. Status changes to "waiting_response"
5. Note added to timeline

#### 6e. Close Report
**Sequence:**
1. Click "Close" button (status=in_progress or waiting_response)
2. Modal with optional note
3. **POST `/admin/reports/{reportId}/close`**
```json
{
  "note": "Issue resolved - content removed and user warned"
}
```
4. Status changes to "closed"
5. Refresh details

**Note:** Invalid transitions return 422 error - show error message to user

---

### Flow 7: Notifications (Admin)

**Frontend Location:** `notifications-management/notifications-management-page.tsx`

#### 7a. List Notifications
**Sequence:**
1. **GET `/admin/notifications?page=1&perPage=20&sort=-sentAt`**

**Query Parameters:**
```
filter.mailbox=inbox|sent
filter.status=unread|read|sent
filter.category=campaign|post|account|report|system
filter.recipientScope=all|users|organizations
filter.date=all|today|last_7_days
sort=sentAt|-sentAt
```

**Table/List Fields:**
- title, body → Notification message
- status → unread/read/sent (badge)
- category → Type (badge)
- recipientScope → Recipients
- priority → High/Normal (badge if high)
- createdAt, sentAt, readAt (timestamps)
- actions → Mark read, Resend, Delete

#### 7b. View Notification
**Sequence:**
1. Click notification
2. **GET `/admin/notifications/{notificationId}`**
3. Show full content in modal/panel
4. If status=unread, mark as read automatically

#### 7c. Create Notification
**Sequence:**
1. Click "Create Notification" button
2. Form with fields:
   - title (required)
   - body (required, textarea)
   - category (dropdown: campaign|post|account|report|system)
   - recipientScope (dropdown: all|users|organizations)
   - recipientLabel (text showing who receives it)
   - priority (toggle: normal|high, optional)
3. **POST `/admin/notifications`**
```json
{
  "title": "Platform Maintenance",
  "body": "The platform will be under maintenance on Saturday from 2-4 AM UTC",
  "category": "system",
  "recipientScope": "all",
  "recipientLabel": "All Users",
  "priority": "high"
}
```
4. Show success message
5. Close form and refresh list

#### 7d. Update Read State
**Sequence:**
1. Click notification → mark as read
2. Or bulk action: select multiple → "Mark as Read"
3. **PATCH `/admin/notifications/{notificationId}/read-state`**
```json
{
  "status": "read"
}
```
4. Visual feedback (remove highlight, change badge)

#### 7e. Resend Notification
**Sequence:**
1. Click "Resend" action on notification
2. **POST `/admin/notifications/{notificationId}/resend`**
3. Show "Notification resent" confirmation

#### 7f. Delete Notification
**Sequence:**
1. Click delete action
2. Confirm
3. **DELETE `/admin/notifications/{notificationId}`**
4. Remove from list

---

### Flow 8: Badges/Rewards (Admin)

**Frontend Location:** `rewards-management/rewards-management-page.tsx`

#### 8a. List Badges
**Sequence:**
1. **GET `/admin/badges?page=1&perPage=20&sort=-createdAt`**

**Query Parameters:**
```
filter.isActive=true|false
filter.search=string
sort=createdAt|-createdAt|name|-name
```

**Table Columns:**
- name → Badge Name
- description → Description
- criteria → Criteria
- iconName → Icon (display icon)
- isActive → Active status (toggle)
- createdAt → Created

#### 8b. Create Badge
**Sequence:**
1. Click "Create Badge" button
2. Form with:
   - name (required)
   - description (required)
   - criteria (required, textarea)
   - iconName (dropdown with icon preview)
   - isActive (toggle)
3. **POST `/admin/badges`**
```json
{
  "name": "Helper Hero",
  "description": "Awarded for helping 10+ people in need",
  "criteria": "Provide assistance through help_request posts",
  "iconName": "heart-handshake",
  "isActive": true
}
```
4. Show success, refresh list

#### 8c. View/Edit Badge
**Sequence:**
1. Click badge row
2. **GET `/admin/badges/{badgeId}`**
3. Form pre-fills with data
4. User modifies and clicks "Save"
5. **PATCH `/admin/badges/{badgeId}`**
6. Refresh list

#### 8d. Toggle Badge Status
**Sequence:**
1. Click isActive toggle on badge row
2. **PATCH `/admin/badges/{badgeId}/status`**
```json
{
  "isActive": false
}
```

#### 8e. Delete Badge
**Sequence:**
1. Click delete action
2. Confirm
3. **DELETE `/admin/badges/{badgeId}`**
4. Remove from list

---

### Flow 9: Articles/Content (Admin)

**Frontend Location:** `content-management/content-management-page.tsx`

#### 9a. List Articles
**Sequence:**
1. **GET `/admin/articles?page=1&perPage=20&sort=-createdAt`**

**Query Parameters:**
```
filter.status=draft|published
filter.search=string
sort=createdAt|-createdAt|publishedAt|-publishedAt|title|-title
```

**Table Columns:**
- title → Title
- slug → URL slug
- excerpt → Excerpt (truncate)
- status → Status (badge: draft/published)
- publishedAt → Published (date if published, else "-")
- createdAt → Created
- authorName → Author

#### 9b. Create Article
**Sequence:**
1. Click "Create Article" button
2. Form opens or navigate to editor page
3. Form fields:
   - title (required)
   - slug (optional - auto-generated from title if empty)
   - excerpt (required)
   - authorName (required)
   - status (required: draft|published)
   - content (rich editor for full article body)
4. **POST `/admin/articles`**
```json
{
  "title": "Getting Started with JOD",
  "slug": "getting-started-with-jod",
  "excerpt": "A comprehensive guide to using the JOD platform",
  "authorName": "Admin User",
  "status": "draft"
}
```
5. Success: show notification, refresh list

#### 9c. Edit Article
**Sequence:**
1. Click article row → open editor
2. **GET `/admin/articles/{articleId}`**
3. Form pre-fills
4. User edits content
5. **PATCH `/admin/articles/{articleId}`**
```json
{
  "title": "Updated Title",
  "excerpt": "Updated excerpt",
  "status": "published"
}
```
6. Refresh list

#### 9d. Publish Article
**Sequence:**
1. Article created as draft
2. Click "Publish" button
3. **PATCH `/admin/articles/{articleId}`**
```json
{
  "status": "published"
}
```
4. Status badge changes to "published"
5. publishedAt gets timestamp

#### 9e. Delete Article
**Sequence:**
1. Click delete action
2. Confirm
3. **DELETE `/admin/articles/{articleId}`**
4. Remove from list

---

### Flow 10: Analytics & Audit Logs (Admin)

**Frontend Location:** `analytics-dashboard/analytics-dashboard-page.tsx`, `audit-log/audit-log-page.tsx`

#### 10a. View Analytics KPIs
**Sequence:**
1. Page loads
2. **GET `/admin/analytics/kpis?range=30d`**
   - range can be: 7d, 30d, 90d, 12m
3. Display KPI cards showing metrics and change vs last period

**Response fields:**
```
kpis: [
  {
    id: "unique-id",
    label: "Total Users",
    value: 1250,
    changeVsLastMonth: 12.5  // percentage
  }
]
```

#### 10b. View Weekly Analytics
**Sequence:**
1. **GET `/admin/analytics/weekly?range=30d`**
2. Display chart data (line/bar chart)

**Response fields:**
```
rows: [
  {
    weekLabel: "Week 1 (May 1-7)",
    visits: 5230,
    newUsers: 120,
    donations: 15000
  }
]
```

#### 10c. View Audit Logs
**Sequence:**
1. **GET `/admin/audit-logs?page=1&perPage=20&sort=-at`**

**Query Parameters:**
```
filter.actorUserId=string
filter.action=string
filter.from=date (YYYY-MM-DD)
filter.to=date (YYYY-MM-DD)
sort=at|-at
```

**Table Columns:**
- action → Action (create, update, delete, approve, reject, etc.)
- user → Actor/User (who performed action)
- entityType → Entity Type (post, campaign, user, org, etc.)
- entityId → Entity Reference
- metadata → Details (JSON or summary)
- at → Timestamp (sortable)

---

### Flow 11: Platform Settings (Admin)

**Frontend Location:** `platform-settings/platform-settings-page.tsx`

#### 11a. View Settings
**Sequence:**
1. **GET `/admin/platform-settings`**
2. Form displays with current values

**Fields:**
```json
{
  "siteName": "JOD - Jordanian Opportunity & Development",
  "allowNewPosts": true,
  "requirePostReview": true
}
```

#### 11b. Update Settings
**Sequence:**
1. User modifies form fields
2. Clicks "Save"
3. **PATCH `/admin/platform-settings`**
```json
{
  "siteName": "Updated Platform Name",
  "allowNewPosts": false,
  "requirePostReview": true
}
```
4. Show "Settings saved successfully"

---

### Flow 12: Organization - Campaigns

**Frontend Location:** `organization-campaigns/organization-campaigns-page.tsx`

#### 12a. List Organization Campaigns
**Sequence:**
1. User navigates to Org Dashboard → Campaigns
2. **GET `/api/v1/org/campaigns?page=1&perPage=20&sort=-updatedAt`**

**Query Parameters:**
```
filter.status=draft|active|closed
filter.category=health|education|food|shelter|employment
filter.location=string
sort=updatedAt|-updatedAt|progress|-progress
sortBy=updated_newest|updated_oldest|progress_highest|progress_lowest (compat)
```

**Table Columns:**
- title → Campaign Title
- summary → Description (truncate)
- category → Category (badge)
- status → Status (badge: draft/active/closed)
- location → Location
- goalAmount → Goal (currency formatted)
- raisedAmount → Raised (currency formatted)
- progress → Progress % (calculated from raised/goal)
- beneficiariesCount → Beneficiaries
- donorsCount → Donors
- applicantsCount → Applicants
- startDate, endDate → Dates
- createdAt, updatedAt → Metadata

#### 12b. Create Campaign
**Sequence:**
1. Click "Create Campaign" button
2. Form opens with fields:
   - title (required)
   - summary (required, textarea)
   - category (required: health, education, food, shelter, employment)
   - status (required: draft|active|closed)
   - location (required)
   - goalAmount (required, numeric, >= 0)
   - beneficiariesCount (required, numeric, >= 0)
   - startDate (required, date picker)
   - endDate (required, date picker)
3. **POST `/org/campaigns`**
```json
{
  "title": "Healthcare Initiative 2025",
  "summary": "Providing healthcare services to underprivileged communities",
  "category": "health",
  "status": "draft",
  "location": "Amman, Jordan",
  "goalAmount": 50000,
  "beneficiariesCount": 500,
  "startDate": "2025-06-01",
  "endDate": "2025-12-31"
}
```
4. Validation:
   - startDate <= endDate
   - goalAmount >= 0
   - beneficiariesCount >= 0
5. Success: refresh list

#### 12c. View Campaign Details
**Sequence:**
1. Click campaign row
2. **GET `/org/campaigns/{campaignId}`**
3. Display detail page with all campaign info

#### 12d. Update Campaign
**Sequence:**
1. Click "Edit" on campaign details
2. Form pre-fills with current data
3. User modifies fields
4. **PATCH `/org/campaigns/{campaignId}`**
```json
{
  "title": "Updated Title",
  "goalAmount": 60000
}
```
5. Refresh details

#### 12e. Close Campaign
**Sequence:**
1. Click "Close" button (only available if status=active)
2. Modal with required field: reason (min 8 chars)
3. **POST `/org/campaigns/{campaignId}/close`**
```json
{
  "reason": "Campaign goals have been achieved and the fundraising period has ended successfully"
}
```
4. Status changes to "closed"
5. Show success

#### 12f. Delete Campaign
**Sequence:**
1. Click delete (only if status=draft)
2. Confirm
3. **DELETE `/org/campaigns/{campaignId}`**
4. Remove from list

---

### Flow 13: Organization - Posts

**Frontend Location:** `organization-posts-management/posts-management-page.tsx`

#### 13a. List Posts
**Sequence:**
1. **GET `/org/posts?page=1&perPage=20&sort=-updatedAt`**

**Query Parameters:**
```
filter.status=draft|published|archived
filter.type=general|job_opportunity|campaign_teaser|campaign_update|campaign_summary
sort=updatedAt|-updatedAt|title|-title
sortBy=updated_newest|updated_oldest|title_asc|title_desc (compat)
```

**Table Columns:**
- title → Title
- summary → Summary (truncate)
- type → Type (badge)
- status → Status (badge: draft/published/archived)
- authorName → Author
- location → Location
- campaignTitle → Campaign (if applicable)
- viewsCount → Views
- reactionsCount → Reactions
- applicationsCount → Applications (for job_opportunity type)
- updatedAt → Last Updated
- publishedAt → Published (if published)

#### 13b. Create Post
**Sequence:**
1. Click "Create Post" button
2. Form with:
   - title (required)
   - summary (required)
   - type (required: general, job_opportunity, campaign_teaser, campaign_update, campaign_summary)
   - status (required: draft|published|archived)
   - authorName (required)
   - location (required)
   - campaignTitle (required if type is campaign_* or job_opportunity)
3. **POST `/org/posts`**
```json
{
  "title": "Job Opportunity: Healthcare Workers Needed",
  "summary": "We are seeking experienced healthcare professionals",
  "type": "job_opportunity",
  "status": "draft",
  "authorName": "Healthcare Team",
  "location": "Amman, Jordan",
  "campaignTitle": "Healthcare Initiative 2025"
}
```
4. Validation: campaign-related types require campaignTitle or campaignId
5. Success: refresh list

#### 13c. View Post Details
**Sequence:**
1. **GET `/org/posts/{postId}`**

#### 13d. Update Post
**Sequence:**
1. Click "Edit" on post
2. Form pre-fills
3. **PATCH `/org/posts/{postId}`**

#### 13e. Publish Post (State Transition)
**Sequence:**
1. Post status=draft
2. Click "Publish" button
3. **POST `/org/posts/{postId}/publish`**
4. Status changes to published
5. publishedAt gets current timestamp

#### 13f. Archive Post (State Transition)
**Sequence:**
1. Post status=published
2. Click "Archive" button
3. **POST `/org/posts/{postId}/archive`**
4. Status changes to archived

#### 13g. Restore Post (State Transition)
**Sequence:**
1. Post status=archived
2. Click "Restore" button
3. **POST `/org/posts/{postId}/restore`**
4. Status changes back to draft

#### 13h. Delete Post
**Sequence:**
1. Click delete (only if status=draft)
2. Confirm
3. **DELETE `/org/posts/{postId}`**
4. Remove from list

---

### Flow 14: Organization - Donors & Applicants

**Frontend Location:** `donors-management/donors-management-page.tsx`

#### 14a. List Donors
**Sequence:**
1. **GET `/org/donors?page=1&perPage=20&sort=-donatedAt`**

**Query Parameters:**
```
filter.campaignId=string
filter.city=string
filter.search=string
sort=donatedAt|-donatedAt|name|-name
sortBy=date_newest|date_oldest|name_asc|name_desc (compat)
```

**Table Columns:**
- name → Donor Name
- email → Email
- phone → Phone
- campaignTitle → Campaign
- amountOrType → Amount/Type (e.g., "500 JOD" or "In-kind donation")
- donatedAt → Donation Date
- city → City
- source → Source (how they were acquired)
- paymentMethod → Payment Method
- campaignRef → Reference ID
- assignedTo → Assigned To (staff member name)
- internalNotes → Notes (internal, not visible to donor)

#### 14b. Create Donor Entry
**Sequence:**
1. Click "Add Donor" button
2. Form with fields: name, email, phone, campaignTitle, amountOrType, city, paymentMethod, assignedTo, internalNotes
3. **POST `/org/donors`**
```json
{
  "name": "Hassan Ahmed",
  "email": "hassan@example.com",
  "phone": "+962791234567",
  "campaignTitle": "Healthcare Initiative 2025",
  "amountOrType": "500 JOD",
  "city": "Amman",
  "paymentMethod": "bank_transfer",
  "assignedTo": "staff-member-id",
  "internalNotes": "Follow-up needed"
}
```

#### 14c. View/Edit Donor
**Sequence:**
1. Click donor row
2. Details sheet/modal appears
3. Click "Edit"
4. **PATCH `/org/donors/{donorId}`**
```json
{
  "assignedTo": "new-staff-id",
  "internalNotes": "Updated notes"
}
```

#### 14d. Delete Donor
**Sequence:**
1. Click delete action
2. Confirm
3. **DELETE `/org/donors/{donorId}`**

#### 14e. List Applicants
**Sequence:**
1. Navigate to "Applicants" tab
2. **GET `/org/applicants?page=1&perPage=20&sort=-donatedAt`**

**Query Parameters:**
```
filter.campaignId=string
filter.applicantStatus=string
filter.search=string
sort=donatedAt|-donatedAt|name|-name
```

**Table Columns:**
- Same structure as donors (name, email, phone, campaign, amountOrType as status, date, etc.)

#### 14f. Create/Update/Delete Applicant
**Sequence:**
- Same pattern as donors
- **POST `/org/applicants`**, **PATCH `/org/applicants/{applicantId}`**, **DELETE `/org/applicants/{applicantId}`**

---

### Flow 15: Organization - Staff & Roles

**Frontend Location:** `staff-management/staff-management-page.tsx`

#### 15a. List Staff
**Sequence:**
1. **GET `/org/staff?page=1&perPage=20&sort=-invitedAt`**

**Query Parameters:**
```
filter.role=owner|manager|editor|viewer
sort=invitedAt|-invitedAt|name|-name
sortBy=invited_newest|invited_oldest|name_asc|name_desc (compat)
```

**Table Columns:**
- name → Staff Name
- email → Email
- role → Role (badge: owner/manager/editor/viewer)
- invitedAt → Invited Date
- status → Status (active/pending/inactive - if tracked)

#### 15b. Add Staff Member
**Sequence:**
1. Click "Add Staff" button
2. Form with:
   - name (required)
   - email (required)
   - role (required: owner|manager|editor|viewer)
3. **POST `/org/staff`**
```json
{
  "name": "Ahmed Manager",
  "email": "ahmed.manager@org.com",
  "role": "manager"
}
```
4. System sends invite email (backend)
5. Show success notification

#### 15c. Update Staff Role
**Sequence:**
1. Click staff row
2. Click "Change Role"
3. Select new role from dropdown
4. **PATCH `/org/staff/{staffId}`**
```json
{
  "role": "editor"
}
```

#### 15d. Remove Staff
**Sequence:**
1. Click delete action on staff
2. Confirm
3. **DELETE `/org/staff/{staffId}`**

#### 15e. List Roles
**Sequence:**
1. Navigate to "Roles" tab
2. **GET `/org/roles?page=1&perPage=20&sort=-updatedAt`**

**Query Parameters:**
```
filter.status=active|inactive
sort=updatedAt|-updatedAt|permissionsCount|-permissionsCount|membersCount|-membersCount
sortBy=updated_newest|updated_oldest|permissions_most|members_most (compat)
```

**Table Columns:**
- role → Role Name
- description → Description
- permissions[] → Permissions (count + list)
- isActive → Active status (toggle)
- isSystem → System Role (badge, can't delete if true)
- membersCount → Members with this role
- updatedAt → Last Updated

#### 15f. Create Role
**Sequence:**
1. Click "Create Role" button
2. Form with:
   - role (required, text)
   - description (required, textarea)
   - permissions[] (required, multi-select from catalog)
   - isActive (toggle)
3. Fetch permission catalog: **GET `/org/permissions/catalog`**
   - Shows all available permissions to select from
4. User selects permissions
5. **POST `/org/roles`**
```json
{
  "role": "Content Manager",
  "description": "Manages posts and campaigns content",
  "permissions": [
    "org.posts.create",
    "org.posts.edit",
    "org.campaigns.view",
    "org.donors.view"
  ],
  "isActive": true
}
```

#### 15g. Update Role
**Sequence:**
1. Click role row
2. Click "Edit"
3. Form pre-fills with current data
4. User modifies permissions/description/isActive
5. **PATCH `/org/roles/{roleId}`**
```json
{
  "description": "Updated description",
  "permissions": ["org.posts.create", "org.posts.edit", "org.campaigns.view"],
  "isActive": true
}
```

#### 15h. Delete Role
**Sequence:**
1. Click delete (only on non-system roles)
2. Confirm
3. **DELETE `/org/roles/{roleId}`**
4. Rules:
   - Cannot delete system roles (isSystem=true)
   - Members of deleted role automatically reassigned to "viewer" role
5. Show success message

---

### Flow 16: Organization - Notifications

**Frontend Location:** `organization-notifications/organization-notifications-page.tsx`

#### 16a. List Organization Notifications
**Sequence:**
1. **GET `/org/notifications?page=1&perPage=20&sort=-sentAt`**

**Query Parameters:**
```
filter.mailbox=inbox|sent
filter.status=unread|read|sent
filter.category=campaign|post|account|report|system
filter.recipientScope=all|organization_members|staff
filter.date=all|today|last_7_days
sort=sentAt|-sentAt
```

**Table Columns:**
- title, body → Notification text
- category → Category (badge)
- status → Status (badge: unread/read)
- sentAt → Date sent
- priority → Priority (badge if high)
- actions → Mark read, delete, resend

#### 16b. Create Organization Notification
**Sequence:**
1. Click "Create Notification"
2. Form with: title, body, category, recipientScope, recipientLabel, priority
3. **POST `/org/notifications`**
```json
{
  "title": "Campaign Milestone",
  "body": "Your Healthcare campaign has reached 50% of the goal!",
  "category": "campaign",
  "recipientScope": "organization_members",
  "recipientLabel": "All Organization Members",
  "priority": "high"
}
```

#### 16c. Mark as Read/Unread
**Sequence:**
1. Click notification or bulk action
2. **PATCH `/org/notifications/{notificationId}/read-state`**
```json
{
  "status": "read"
}
```

#### 16d. Resend Notification
**Sequence:**
1. Click "Resend" action
2. **POST `/org/notifications/{notificationId}/resend`**

#### 16e. Delete Notification
**Sequence:**
1. Click delete
2. **DELETE `/org/notifications/{notificationId}`**

---

### Flow 17: Organization - Reports (Read-only)

**Frontend Location:** `organization-reports/organization-reports-page.tsx`

#### 17a. List Organization Reports
**Sequence:**
1. **GET `/org/reports?page=1&perPage=20&sort=-submittedAt`**

**Query Parameters:**
```
filter.status=open|in_review|closed
filter.category=content|harassment|fraud|other
sort=submittedAt|-submittedAt
```

**Table Columns:**
- subject → Subject
- summary → Summary
- category → Category (badge)
- status → Status (badge: open/in_review/closed)
- submittedAt → Date
- reporterLabel → Reporter (anonymized)

#### 17b. View Report Details
**Sequence:**
1. **GET `/org/reports/{reportId}`**
2. Display report details (read-only)
3. Cannot take actions (only admin can)

---

### Flow 18: Organization Settings & Profile

**Frontend Location:** `dashboard-profile/dashboard-profile-page.tsx`, `dashboard-settings/dashboard-settings-page.tsx`

#### 18a. View User Profile
**Sequence:**
1. User navigates to profile
2. **GET `/me/profile`**
3. Display profile information

**Fields:**
```json
{
  "name": "User Name",
  "email": "user@example.com",
  "phone": "+962791234567",
  "verifiedFlags": { ... }
}
```

#### 18b. Update User Profile
**Sequence:**
1. Click "Edit"
2. Form with: name, email (phone optional)
3. **PATCH `/me/profile`**
```json
{
  "name": "Updated Name",
  "email": "new@example.com"
}
```

#### 18c. View Organization Bank Settings
**Sequence:**
1. Org staff/owner navigates to Settings
2. **GET `/org/settings/bank-account`**
3. Display bank info

**Fields:**
```json
{
  "bankName": "Arab Bank",
  "iban": "JO94CBJO0010000000000131000302"
}
```

#### 18d. Update Bank Settings
**Sequence:**
1. Click "Edit Bank Account"
2. Form with: bankName, iban
3. **PATCH `/org/settings/bank-account`**
```json
{
  "bankName": "Bank of Jordan",
  "iban": "JO94CBJO0010000000000131000302"
}
```

---

## Detailed Endpoint Documentation

### Common Query Parameters

#### Pagination (All List Endpoints)
| Parameter | Type | Default | Max | Example |
|-----------|------|---------|-----|---------|
| page | integer | 1 | N/A | `?page=2` |
| perPage | integer | 20 | 100 | `?perPage=50` |

**Usage:** `GET /admin/users?page=2&perPage=50`
**Response includes:**
```json
"meta": {
  "currentPage": 2,
  "perPage": 50,
  "total": 250,
  "lastPage": 5
}
```

#### Filtering
| Format | Meaning | Example |
|--------|---------|---------|
| `filter.fieldName=value` | Exact match | `?filter.status=active` |
| `filter.fieldName=val1,val2` | One of multiple | `?filter.status=active,pending` |
| `filter.search=text` | Search across text fields | `?filter.search=john` |
| `filter.from=date` | Date range start | `?filter.from=2025-01-01` |
| `filter.to=date` | Date range end | `?filter.to=2025-05-25` |

#### Sorting
| Format | Meaning | Example |
|--------|---------|---------|
| `sort=fieldName` | Ascending | `?sort=name` |
| `sort=-fieldName` | Descending | `?sort=-createdAt` |

**Spatie/Laravel style** - same backend handling all list endpoints

#### Frontend Compatibility (Sort Mapping)
Some frontend pages use different sort key names. Backend accepts both:

| Frontend sortBy | Backend sort | Notes |
|---|---|---|
| `name_asc` | `sort=name` | Convert in frontend before sending |
| `name_desc` | `sort=-name` | |
| `created_newest` | `sort=-createdAt` | |
| `created_oldest` | `sort=createdAt` | |
| `updated_newest` | `sort=-updatedAt` | |
| `updated_oldest` | `sort=updatedAt` | |
| `progress_highest` | `sort=-progress` | |
| `progress_lowest` | `sort=progress` | |
| `date_newest` | `sort=-donatedAt` | |
| `date_oldest` | `sort=donatedAt` | |
| `invited_newest` | `sort=-invitedAt` | |
| `invited_oldest` | `sort=invitedAt` | |
| `permissions_most` | `sort=-permissionsCount` | |
| `members_most` | `sort=-membersCount` | |

---

### Authentication Headers

**All requests must include:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json (for POST/PATCH)
```

**Token Management:**
- Tokens issued by auth endpoint (assumed to exist)
- Store in localStorage with expiration time
- Refresh token before expiration (add 5 min buffer)
- On 401 response: clear token and redirect to login
- Implement token refresh endpoint (assumed)

---

### Rate Limiting

**Assumed from typical Laravel API:**
- Default: 60 requests per minute per IP
- Headers in response: `X-RateLimit-Limit`, `X-RateLimit-Remaining`
- On limit exceeded (429): Implement exponential backoff retry

---

### Data Types & Formatting

#### Dates
- Format: ISO 8601 (e.g., `2025-05-25T14:30:00Z`)
- Display: Format according to locale (MM/DD/YYYY, DD/MM/YYYY, etc.)
- Queries use: `YYYY-MM-DD` (e.g., `?filter.from=2025-01-01`)

#### Currency
- Backend returns: numeric values (e.g., `50000`)
- Frontend formats with locale: `$50,000.00` or `50,000 JOD`

#### Booleans
- Backend: JSON `true`/`false`
- Frontend: checkboxes, toggles

#### Enums
- All enum values should be validated client-side against allowed values
- Server returns 422 if invalid enum

#### Arrays
- Filter arrays use comma-separated values: `?filter.status=active,pending`
- Response arrays maintain order from backend

---

### Validation Rules (Client-Side)

**Apply these before sending to API to improve UX:**

| Field | Rule | Example |
|-------|------|---------|
| name, title | Required, 3-255 chars | "New Campaign" |
| email | Required, valid email format | "user@example.com" |
| phone | Required, valid format | "+962791234567" |
| password | Min 8 chars | "SecurePass123" |
| confirmPassword | Must match password | Same as password |
| description, reason | Min 8 chars (if required) | "Detailed reason..." |
| dates | startDate <= endDate | 2025-06-01 to 2025-12-31 |
| numeric | >= 0 | 50000 |
| enum | Must be from allowed list | "active" (not "enabled") |

---

### Common Response Codes

| Code | Scenario | Frontend Action |
|------|----------|-----------------|
| 200 OK | Successful GET | Parse response |
| 201 Created | Successful POST | Show success, refresh |
| 204 No Content | Successful DELETE | Show success, remove |
| 400 Bad Request | Malformed request | Show error message |
| 401 Unauthorized | Invalid/expired token | Redirect to login |
| 403 Forbidden | Insufficient permissions | Show "Access Denied" |
| 404 Not Found | Resource doesn't exist | Show "Not Found" message |
| 422 Unprocessable Entity | Validation failed | Show field errors |
| 429 Too Many Requests | Rate limited | Retry with backoff |
| 500 Server Error | Backend error | Show "Server Error", allow retry |

---

### Error Response Formats

#### Validation Error (422)
```json
{
  "errors": {
    "email": ["Email is already in use"],
    "password": ["Password must be at least 8 characters"]
  },
  "message": "Validation failed"
}
```
**Frontend:** Extract `errors` object, map to form fields

#### Generic Error
```json
{
  "error": "Forbidden",
  "message": "User does not have permission to access this resource",
  "code": 403
}
```
**Frontend:** Show `message` in error toast

#### Invalid Transition (422)
```json
{
  "message": "Cannot transition from draft to closed"
}
```
**Frontend:** Show as error message

---

### Implementation Checklist

#### Phase 1: Setup & Bootstrap
- [ ] Configure API base URL
- [ ] Implement auth token storage/refresh
- [ ] Create HTTP interceptor for auth header
- [ ] Implement error handler (401, 422, 5xx)
- [ ] Create loading states and spinners
- [ ] Create error toast/notification component

#### Phase 2: Admin Dashboard
- [ ] Implement `/me` endpoint call on app init
- [ ] Implement `/me/permissions` to gate menu items
- [ ] Implement `/admin/overview` for KPI cards
- [ ] Implement `/admin/analytics/kpis` for chart data
- [ ] Implement `/admin/analytics/weekly` for weekly chart

#### Phase 3: Admin Users
- [ ] List users with pagination, filters, sorting
- [ ] Create user form and POST endpoint
- [ ] View user details (GET detail endpoint)
- [ ] Edit user (PATCH endpoint)
- [ ] Change password (PATCH /password)
- [ ] Delete user (DELETE endpoint)
- [ ] Update status (PATCH /status)

#### Phase 4: Admin Organizations
- [ ] List organizations with filters
- [ ] View organization details
- [ ] Edit organization
- [ ] Update status and verification
- [ ] Accept organization workflow
- [ ] Delete organization

#### Phase 5: Admin Moderation
- [ ] Posts review list, details, approve/reject
- [ ] Campaigns review list, details, approve/reject
- [ ] Reports management (list, view, claim, request info, close)
- [ ] Implement state transition validations

#### Phase 6: Admin Content
- [ ] Notifications (list, create, read/unread, resend, delete)
- [ ] Badges (CRUD)
- [ ] Articles (CRUD with editor)
- [ ] Platform settings (view/update)
- [ ] Audit logs with filters

#### Phase 7: Organization Workspace
- [ ] Organization overview
- [ ] Campaigns (CRUD + state transitions)
- [ ] Posts (CRUD + state transitions)
- [ ] Donors/Applicants (manage, link to campaigns)
- [ ] Notifications (org-scoped)
- [ ] Reports (read-only, org-scoped)

#### Phase 8: Organization Governance
- [ ] Staff management (add/remove/change role)
- [ ] Roles (CRUD with permission selection)
- [ ] Permission catalog endpoint
- [ ] Profile and settings (profile update, bank account)

---

### Example Integration Pattern

**For any list page:**

1. **On mount:**
   ```
   - Initialize default filters/sort
   - Set loading state
   - Call GET /endpoint?page=1&perPage=20&sort=-createdAt
   ```

2. **On filter/sort change:**
   ```
   - Build query string with all active filters and sort
   - Reset page to 1
   - Set loading state
   - Call GET /endpoint with new query params
   ```

3. **Response handling:**
   ```
   - Extract data array for table
   - Extract meta for pagination
   - Update table rows
   - Enable pagination buttons based on meta
   - Show data count: "Showing {start}-{end} of {total}"
   ```

4. **Error handling:**
   ```
   - On 401: Clear token, redirect to login
   - On 403: Show "You don't have permission"
   - On 5xx: Show "Error loading data" with retry button
   ```

**For any create/edit form:**

1. **Form submission:**
   ```
   - Clear previous errors
   - Validate all fields client-side
   - If invalid: show field errors, don't submit
   - Set loading state
   - Call POST/PATCH with form data
   ```

2. **Success (201/200):**
   ```
   - Show success toast
   - Close form/modal
   - Refresh parent list OR add new item to list
   - Clear form fields
   ```

3. **Error handling (422):**
   ```
   - Extract errors object
   - Map each error to corresponding form field
   - Show error text below field
   - Keep form open so user can correct
   ```

---

### Frontend Development Timeline

**Estimated effort (frontend only):**
- Phase 1-2: 3-4 days (setup, dashboard)
- Phase 3: 4-5 days (users management)
- Phase 4: 4-5 days (organizations)
- Phase 5: 5-6 days (moderation)
- Phase 6: 3-4 days (content)
- Phase 7: 6-7 days (org workspace)
- Phase 8: 4-5 days (governance)

**Total: 30-35 days** for one developer

---

### Testing Checklist

For each endpoint:
- [ ] Happy path (200/201 response)
- [ ] Empty results (data array is empty)
- [ ] Pagination (test page 1, last page, invalid page)
- [ ] Filters (single filter, multiple filters, no filters)
- [ ] Sort (ascending, descending, invalid sort field)
- [ ] Authentication (with token, without token, expired token)
- [ ] Authorization (with permission, without permission)
- [ ] Validation (all required fields, invalid data types)
- [ ] Error states (404, 422, 500)

---

### Performance Considerations

1. **Pagination**: Always paginate large lists (max 20-50 per page)
2. **Caching**: Cache `/me/permissions` for session duration
3. **Debouncing**: Debounce search/filter inputs (300ms delay)
4. **Request cancellation**: Cancel previous request when user starts new search
5. **State management**: Use context/store to avoid repeated API calls
6. **Lazy loading**: Load detail data only when user views it
7. **Batch operations**: If available, batch multiple deletes/updates

---

## Implementation Support

**For questions during implementation:**
1. Refer to corresponding flow in "User Flow Mappings" section
2. Check "Detailed Endpoint Documentation" for specific endpoint details
3. Review "Error Handling" section for error codes
4. Use "Example Integration Pattern" as reference implementation approach

**Backend API Status:**
- Check Postman collection for current request/response examples
- Refer to DASHBOARD_API_ENDPOINTS_PLAN.md for endpoint specifications
- All endpoints ready for Phase 1 implementation (TBD)
