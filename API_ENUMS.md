# JOD Dashboard API - Enums Documentation

Complete enumeration values for all API endpoints. Use these for validation, dropdowns, and API testing.

---

## USER & ACCOUNT ENUMS

### User Types
```
admin              Platform administrator with full access
general            Regular user account
volunteer          Volunteer user account
job_seeker         Job seeker account
donor              Donor user account
```

**Validation Rule**: `Rule::in('admin', 'general', 'volunteer', 'job_seeker', 'donor')`

---

### User Status
```
active             User account is active
inactive           User account is inactive/disabled
pending            Account pending activation/verification
rejected           Account rejected/banned
```

**Validation Rule**: `Rule::in('active', 'inactive', 'pending', 'rejected')`
**Default**: `active`

---

## ORGANIZATION ENUMS

### Organization Status
```
active             Organization is active
inactive           Organization is inactive/disabled
pending            Organization pending verification
rejected           Organization application rejected
```

**Validation Rule**: `Rule::in('active', 'inactive', 'pending', 'rejected')`
**Default**: `pending`

---

### Organization Verification Status
```
verified           Organization has been verified
unverified         Organization has not been verified
pending            Verification pending review
rejected           Verification was rejected
```

**Validation Rule**: `Rule::in('verified', 'unverified', 'pending', 'rejected')`
**Default**: `unverified`

---

### Organization Type
```
ngo                Non-Governmental Organization
charity            Charity Organization
social_enterprise  Social Enterprise
community_group    Community Group
government         Government Organization
other              Other type
```

**Validation Rule**: `Rule::in('ngo', 'charity', 'social_enterprise', 'community_group', 'government', 'other')`

---

## CONTENT ENUMS

### Post Status
```
draft              Post is in draft, not published
published          Post is published and visible
archived           Post is archived
rejected           Post was rejected during moderation
```

**Validation Rule**: `Rule::in('draft', 'published', 'archived', 'rejected')`
**Default**: `draft`

---

### Post Type
```
general            General post
job_opportunity    Job opportunity post
campaign_teaser    Campaign teaser post
campaign_update    Campaign update post
campaign_summary   Campaign summary post
help_request       Help request post
awareness          Awareness/educational post
```

**Validation Rule**: `Rule::in('general', 'job_opportunity', 'campaign_teaser', 'campaign_update', 'campaign_summary', 'help_request', 'awareness')`

---

### Campaign Status
```
draft              Campaign in draft state
active             Campaign is currently active
closed             Campaign has been closed
rejected           Campaign was rejected during review
paused             Campaign is temporarily paused
```

**Validation Rule**: `Rule::in('draft', 'active', 'closed', 'rejected', 'paused')`
**Default**: `draft`

---

### Campaign Category
```
health             Health & Medical
education          Education
shelter            Shelter & Housing
food               Food & Nutrition
employment         Employment & Skills
emergency          Emergency Relief
environment        Environment & Climate
technology         Technology & Innovation
other              Other
```

**Validation Rule**: `Rule::in('health', 'education', 'shelter', 'food', 'employment', 'emergency', 'environment', 'technology', 'other')`

---

## REVIEW & MODERATION ENUMS

### Review Status
```
pending            Awaiting review
approved           Approved by moderator
rejected           Rejected by moderator
appealed           Appeal submitted
```

**Validation Rule**: `Rule::in('pending', 'approved', 'rejected', 'appealed')`

---

### Report Status
```
new                New report, not yet assigned
in_progress        Report is being investigated
waiting_response   Waiting for additional information
closed             Report investigation closed
resolved           Issue has been resolved
```

**Validation Rule**: `Rule::in('new', 'in_progress', 'waiting_response', 'closed', 'resolved')`

---

### Report Severity
```
low                Low severity report
medium             Medium severity report
high               High severity report
critical           Critical/urgent report
```

**Validation Rule**: `Rule::in('low', 'medium', 'high', 'critical')`

---

### Report Entity Type
```
post               Report about a post
campaign           Report about a campaign
user               Report about a user
organization       Report about an organization
comment            Report about a comment
```

**Validation Rule**: `Rule::in('post', 'campaign', 'user', 'organization', 'comment')`

---

### Report Category
```
spam               Spam content
fraud              Fraudulent activity
harassment         Harassment or abuse
inappropriate      Inappropriate content
misinformation     Misinformation/false claims
copyright          Copyright violation
other              Other reason
```

**Validation Rule**: `Rule::in('spam', 'fraud', 'harassment', 'inappropriate', 'misinformation', 'copyright', 'other')`

---

## NOTIFICATION ENUMS

### Notification Status
```
unread             Notification has not been read
read               Notification has been read
sent               Notification was successfully sent
failed             Notification failed to send
```

**Validation Rule**: `Rule::in('unread', 'read', 'sent', 'failed')`

---

### Notification Mailbox
```
inbox              Notification in inbox
sent               Notification in sent folder
archive            Notification in archive
trash              Notification in trash
```

**Validation Rule**: `Rule::in('inbox', 'sent', 'archive', 'trash')`

---

### Notification Category
```
campaign           Campaign-related notification
post               Post-related notification
account            Account-related notification
report             Report-related notification
system             System notification
badge              Badge/reward notification
donation           Donation notification
security          Security alert
```

**Validation Rule**: `Rule::in('campaign', 'post', 'account', 'report', 'system', 'badge', 'donation', 'security')`

---

### Notification Priority
```
normal             Normal priority notification
high               High priority notification
urgent             Urgent notification
info               Informational notification
```

**Validation Rule**: `Rule::in('normal', 'high', 'urgent', 'info')`

---

### Recipient Scope
```
all                Send to all users and organizations
users              Send to users only
organizations      Send to organizations only
staff              Send to organization staff
volunteers         Send to volunteers only
donors             Send to donors only
```

**Validation Rule**: `Rule::in('all', 'users', 'organizations', 'staff', 'volunteers', 'donors')`

---

## STAFF & ROLES ENUMS

### Staff Role
```
owner              Organization owner (full access)
manager            Manager (can manage staff, content)
editor             Editor (can create/edit content)
viewer             Viewer only (read-only access)
contributor        Contributor (can submit content)
```

**Validation Rule**: `Rule::in('owner', 'manager', 'editor', 'viewer', 'contributor')`

---

### Permission Groups
The shared permission catalog in `app/Support/Permissions/PermissionCatalog.php` is the source of truth.

```
dashboard.view                    Dashboard bootstrap access
users.view|create|update|delete|reset_password
organizations.view|create|update|delete|verify|accept
posts.review.view|approve|reject
campaigns.review.view|approve|reject
reports.view|claim|request_info|close
notifications.view|create|update|delete|resend
badges.view|create|update|delete
articles.view|create|update|delete
analytics.view
audit_logs.view
platform_settings.view|update
org.campaigns.view|create|update|delete|close
org.posts.view|create|update|delete|publish|archive|restore
org.donors.view|create|update|delete|manage
org.applicants.view|create|update|delete|manage
org.staff.view|create|update|delete|manage
org.roles.view|create|update|delete
org.notifications.view|create|update|delete|send|resend
org.reports.view
org.settings.view|update
```

---

## ANALYTICS & FILTERING ENUMS

### Date Range
```
7d                 Last 7 days
30d                Last 30 days
90d                Last 90 days
12m                Last 12 months
6m                 Last 6 months
mtd                Month to date
ytd                Year to date
all                All time
```

**Validation Rule**: `Rule::in('7d', '30d', '90d', '12m', '6m', 'mtd', 'ytd', 'all')`

---

### Date Filter Shortcuts
```
today              Today only
yesterday          Yesterday only
this_week          Current week
last_week          Last week
this_month         Current month
last_month         Last month
this_year          Current year
last_year          Last year
```

**Validation Rule**: `Rule::in('today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year', 'last_year')`

---

### Sort Direction
```
asc                Ascending order
desc               Descending order
```

**Format in API**: Use Spatie convention:
- `sort=field` - Ascending
- `sort=-field` - Descending

---

## BADGE SYSTEM ENUMS

### Badge Criteria Types
```
total_donations    Based on total donation amount
volunteer_hours    Based on volunteer hours
posts_published    Based on posts published
campaigns_led      Based on campaigns created
members_helped     Based on beneficiaries helped
active_days        Based on active participation days
referrals          Based on referrals made
community_score    Based on community engagement
```

---

## PAYMENT & DONATION ENUMS

### Payment Method
```
credit_card        Credit card payment
debit_card         Debit card payment
paypal             PayPal payment
bank_transfer      Bank transfer
cash               Cash donation
check              Check payment
mobile_money       Mobile money transfer
cryptocurrency     Cryptocurrency
```

**Validation Rule**: `Rule::in('credit_card', 'debit_card', 'paypal', 'bank_transfer', 'cash', 'check', 'mobile_money', 'cryptocurrency')`

---

### Donation Source
```
website            From website
mobile_app         From mobile app
social_media       From social media
email              From email campaign
referral           From referral program
fundraiser         From fundraiser event
offline            From offline donation
direct             Direct personal donation
```

---

## VERIFICATION ENUMS

### Document Status
```
pending            Document awaiting review
verified           Document has been verified
rejected           Document was rejected
expired            Document has expired
pending_renewal    Renewal pending
```

---

### Email Verification Status
```
unverified         Email not verified
verified           Email verified
pending            Verification pending
expired            Verification expired
```

---

### Phone Verification Status
```
unverified         Phone not verified
verified           Phone verified
pending            Verification pending
```

---

## SORT COMPATIBILITY MAP (Frontend to API)

### Admin Moderation
| Frontend | API |
|----------|-----|
| `created_at_newest` | `sort=-submittedAt` |
| `created_at_oldest` | `sort=submittedAt` |
| `title_asc` | `sort=title` |
| `title_desc` | `sort=-title` |

### Organizations
| Frontend | API |
|----------|-----|
| `name_asc` | `sort=name` |
| `name_desc` | `sort=-name` |
| `created_newest` | `sort=-createdAt` |
| `created_oldest` | `sort=createdAt` |

### Org Campaigns
| Frontend | API |
|----------|-----|
| `updated_newest` | `sort=-updatedAt` |
| `updated_oldest` | `sort=updatedAt` |
| `progress_highest` | `sort=-progress` |
| `progress_lowest` | `sort=progress` |

### Org Posts
| Frontend | API |
|----------|-----|
| `updated_newest` | `sort=-updatedAt` |
| `updated_oldest` | `sort=updatedAt` |
| `title_asc` | `sort=title` |
| `title_desc` | `sort=-title` |

### Donors/Applicants
| Frontend | API |
|----------|-----|
| `date_newest` | `sort=-donatedAt` |
| `date_oldest` | `sort=donatedAt` |
| `name_asc` | `sort=name` |
| `name_desc` | `sort=-name` |

### Staff/Roles
| Frontend | API |
|----------|-----|
| `invited_newest` | `sort=-invitedAt` |
| `invited_oldest` | `sort=invitedAt` |
| `updated_newest` | `sort=-updatedAt` |
| `updated_oldest` | `sort=updatedAt` |
| `permissions_most` | `sort=-permissionsCount` |
| `members_most` | `sort=-membersCount` |

---

## VALIDATION RULES SUMMARY

| Field | Rule | Example |
|-------|------|---------|
| Password | Min 8 chars, alphanumeric + special | `SecurePass123!` |
| Email | Valid email format | `user@example.com` |
| Phone | Valid phone format | `+962791234567` |
| URL | Valid URL format | `https://example.com` |
| Dates | ISO 8601 format | `2025-05-25T14:30:00Z` |
| Amount | Positive number, 2 decimals | `1000.00` |
| Percentage | 0-100 | `75` |
| Reason/Note | Min 8 characters | At least 8 chars |
| Name | 2-255 characters | `John Doe` |
| Location | 2-100 characters | `Amman, Jordan` |

---

## COMMON FILTER VALUES

### Status Filters (by context)
- Users: `active`, `inactive`, `pending`, `rejected`
- Organizations: `active`, `inactive`, `pending`, `verified`
- Posts: `draft`, `published`, `archived`, `pending`, `rejected`
- Campaigns: `draft`, `active`, `closed`, `pending`, `rejected`
- Reports: `new`, `in_progress`, `waiting_response`, `closed`
- Notifications: `unread`, `read`, `sent`, `failed`

### Search Filters
- By name: `filter.search=keyword`
- By email: `filter.email=email@example.com`
- By location: `filter.location=Amman`
- By organization: `filter.organizationId=org-001`
- By campaign: `filter.campaignId=campaign-001`
- By date range: `filter.from=2025-01-01&filter.to=2025-12-31`

---

## HTTP STATUS CODES

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK | Request successful |
| 201 | Created | Resource created successfully |
| 204 | No Content | Successful delete |
| 400 | Bad Request | Invalid parameters |
| 401 | Unauthorized | Missing/invalid token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation failed or invalid transition |
| 429 | Too Many Requests | Rate limited |
| 500 | Server Error | Server error |
