# JOD Dashboard API - Complete Data Seed & Documentation

Complete data seed and documentation package for all API endpoints.

---

## 📦 DELIVERABLES

### 1. **SEED_DATA_REFERENCE.md** (42 KB)
Complete seed data examples for all API endpoints with:
- Full JSON response/request examples for every endpoint
- Real-world sample data organized by endpoint group
- Pagination meta examples
- Error response examples
- Authentication headers
- Query parameter examples

**Use for**: 
- API testing and QA
- Frontend development/mocking
- API documentation
- Database seeding reference

---

### 2. **API_ENUMS.md** (14 KB)
Complete enumeration reference guide with:
- **All valid enum values** for every field
- Validation rules with Laravel `Rule::in()` syntax
- Default values for each enum
- Sort compatibility mapping (frontend ↔ API)
- HTTP status codes
- Filter value guidelines
- Criteria types for badges/gamification

**Use for**:
- Frontend form validation
- Dropdown/select options
- API validation rules
- Sort parameter compatibility

---

### 3. **JOD_Dashboard_API.postman_collection.json** (Updated)
Postman collection with:
- ✅ 21+ endpoints with full example data
- ✅ Request bodies with all required/optional fields
- ✅ Response examples with real-world sample data
- ✅ Authentication headers pre-configured
- ✅ Query parameters documented
- ✅ Error response examples

**Use for**:
- API testing in Postman
- API documentation
- Integration testing
- Frontend development/mocking

---

## 🔑 KEY ENUMS QUICK REFERENCE

### Status Fields
```
Users:          active, inactive, pending, rejected
Organizations:  active, inactive, pending, verified/unverified
Posts:          draft, published, archived, rejected
Campaigns:      draft, active, closed, rejected
Reports:        new, in_progress, waiting_response, closed
```

### Categories & Types
```
Campaign Categories:  health, education, shelter, food, employment, emergency
Post Types:           general, job_opportunity, campaign_teaser, campaign_update, help_request, awareness
Report Severity:      low, medium, high, critical
Notification Priority: normal, high, urgent, info
```

### Staff Roles
```
owner       Full organization access
manager     Can manage staff and content
editor      Can create and edit content
viewer      Read-only access
```

---

## 📋 ENDPOINTS BY GROUP

### Bootstrap & Profile (3 endpoints)
- `GET /me` - Current user profile
- `GET /me/permissions` - User permissions with module breakdown
- `GET /me/dashboard-context` - One-call bootstrap with profile + permissions + counters

### Admin Overview (4 endpoints)
- `GET /admin/overview` - KPI stats and activity feed
- `GET /admin/analytics/kpis` - Key performance indicators
- `GET /admin/analytics/weekly` - Weekly analytics data
- `GET /admin/audit-logs` - Audit log entries

### Admin Users (8 endpoints)
- `GET /admin/users` - List users with filtering/sorting
- `POST /admin/users` - Create new user
- `GET /admin/users/{userId}` - Get user details
- `PATCH /admin/users/{userId}` - Update user
- `PATCH /admin/users/{userId}/status` - Change status
- `PATCH /admin/users/{userId}/password` - Reset password
- `DELETE /admin/users/{userId}` - Soft delete user

### Admin Organizations (7 endpoints)
- `GET /admin/organizations` - List organizations
- `GET /admin/organizations/{organizationId}` - Details with full profile
- `PATCH /admin/organizations/{organizationId}` - Update organization
- `PATCH /admin/organizations/{organizationId}/status` - Change status
- `PATCH /admin/organizations/{organizationId}/verification` - Change verification
- `POST /admin/organizations/{organizationId}/accept` - Accept organization
- `DELETE /admin/organizations/{organizationId}` - Soft delete

### Admin Moderation (6 endpoints)
- `GET /admin/review/posts` - Posts pending review
- `GET /admin/review/posts/{postId}` - Post details
- `POST /admin/review/posts/{postId}/approve` - Approve post
- `POST /admin/review/posts/{postId}/reject` - Reject post
- `GET /admin/review/campaigns` - Campaigns pending review
- Similar operations for campaigns

### Admin Reports (4 endpoints)
- `GET /admin/reports` - List reports with filtering
- `GET /admin/reports/{reportId}` - Report details with timeline
- `POST /admin/reports/{reportId}/claim` - Claim report
- `POST /admin/reports/{reportId}/request-info` - Request more information
- `POST /admin/reports/{reportId}/close` - Close report

### Admin Notifications (5 endpoints)
- `GET /admin/notifications` - List notifications
- `POST /admin/notifications` - Send broadcast notification
- `PATCH /admin/notifications/{id}/read-state` - Mark as read/unread
- `POST /admin/notifications/{id}/resend` - Resend notification
- `DELETE /admin/notifications/{id}` - Delete notification

### Admin Badges (6 endpoints)
- `GET /admin/badges` - List badges
- `POST /admin/badges` - Create badge
- `PATCH /admin/badges/{badgeId}` - Update badge
- `PATCH /admin/badges/{badgeId}/status` - Activate/deactivate
- `DELETE /admin/badges/{badgeId}` - Delete badge

### Admin Articles (4 endpoints)
- `GET /admin/articles` - List articles
- `POST /admin/articles` - Create article
- `PATCH /admin/articles/{articleId}` - Update article
- `DELETE /admin/articles/{articleId}` - Delete article

### Admin Platform Settings (2 endpoints)
- `GET /admin/platform-settings` - Get settings
- `PATCH /admin/platform-settings` - Update settings

### Organization Overview (1 endpoint)
- `GET /org/overview` - Organization dashboard overview

### Organization Campaigns (5 endpoints)
- `GET /org/campaigns` - List campaigns
- `POST /org/campaigns` - Create campaign
- `GET /org/campaigns/{campaignId}` - Campaign details
- `PATCH /org/campaigns/{campaignId}` - Update campaign
- `POST /org/campaigns/{campaignId}/close` - Close campaign

### Organization Posts (7 endpoints)
- `GET /org/posts` - List posts
- `POST /org/posts` - Create post
- `GET /org/posts/{postId}` - Post details
- `PATCH /org/posts/{postId}` - Update post
- `POST /org/posts/{postId}/publish` - Publish post
- `POST /org/posts/{postId}/archive` - Archive post
- `POST /org/posts/{postId}/restore` - Restore from archive

### Organization Donors & Applicants (6 endpoints)
- `GET /org/donors` - List donors
- `POST /org/donors` - Add donor
- `PATCH /org/donors/{donorId}` - Update donor
- `DELETE /org/donors/{donorId}` - Delete donor
- Similar endpoints for applicants

### Organization Staff (3 endpoints)
- `GET /org/staff` - List staff members
- `POST /org/staff` - Invite staff member
- `PATCH /org/staff/{staffId}` - Update staff role
- `DELETE /org/staff/{staffId}` - Remove staff

### Organization Roles (4 endpoints)
- `GET /org/roles` - List roles
- `POST /org/roles` - Create custom role
- `PATCH /org/roles/{roleId}` - Update role
- `DELETE /org/roles/{roleId}` - Delete role

### Organization Permissions (1 endpoint)
- `GET /org/permissions/catalog` - Available permissions for roles

---

## 🔍 VALIDATION RULES

### Field Constraints
```
name, email, phone:    Required fields in most creation endpoints
password:              Min 8 characters, must contain alphanumeric + special char
email:                 Valid email format
phone:                 Valid phone format (e.g., +962791234567)
url:                   Valid HTTPS URL format
dates:                 ISO 8601 format (YYYY-MM-DDTHH:mm:ssZ)
amounts:               Positive number with max 2 decimals
descriptions/reasons:  Min 8 characters for rejection/closure reasons
location:              2-100 characters
status/category/type:  Must match approved enum values
```

### Date Validations
```
startDate <= endDate   Campaign dates must be valid range
createdAt <= updatedAt Creation date before update
from <= to             Date range filters must be logical
```

### Numeric Validations
```
goalAmount >= 0        Campaign goals must be non-negative
beneficiariesCount >= 0 Beneficiary count non-negative
perPage: 1-100         Pagination limit
page: >= 1             Page number must be positive
```

---

## 📊 PAGINATION REFERENCE

All list endpoints support:
```
?page=1              Current page (default: 1)
&perPage=20          Items per page (default: 20, max: 100)
```

Response includes `meta`:
```json
{
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 1542,
    "lastPage": 78
  }
}
```

---

## 🔀 SORT & FILTER EXAMPLES

### Sorting (Spatie Style)
```
?sort=name             Sort by name ascending
?sort=-name            Sort by name descending
?sort=-createdAt       Sort by created date (newest first)
?sort=updatedAt        Sort by updated date (oldest first)
```

### Filtering
```
?filter.status=active
?filter.location=Amman
?filter.search=keyword
?filter.date=last_7_days
?filter.from=2025-01-01&filter.to=2025-12-31
?filter.category=health&filter.status=active
```

### Combined Query
```
GET /api/v1/admin/organizations?page=1&perPage=20&filter.status=active&filter.location=Amman&sort=-createdAt
```

---

## 🔐 AUTHENTICATION

All endpoints require:
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json (for POST/PATCH)
```

Tokens obtained from authentication endpoint (not included in this documentation).

---

## 📝 USAGE GUIDE

### For Frontend Developers
1. **Enums**: Use `API_ENUMS.md` for form validation and dropdown options
2. **Mock Data**: Use `SEED_DATA_REFERENCE.md` for development/testing
3. **API Testing**: Use Postman collection for testing integrations

### For Backend Developers
1. **Validation Rules**: Reference `API_ENUMS.md` for validation implementation
2. **Response Format**: Follow examples in `SEED_DATA_REFERENCE.md`
3. **Error Handling**: See error response examples in both documents

### For QA/Testers
1. **Test Data**: Use examples from `SEED_DATA_REFERENCE.md`
2. **Field Values**: Reference `API_ENUMS.md` for valid inputs
3. **API Testing**: Import Postman collection and run test scenarios

### For API Documentation
1. **Generate Docs**: Reference these files in API documentation
2. **Enums Documentation**: Include `API_ENUMS.md` in API reference
3. **Example Responses**: Copy from `SEED_DATA_REFERENCE.md`

---

## 📌 FILE LOCATIONS

```
c:\laragon\www\JOD\jod-backend\
├── SEED_DATA_REFERENCE.md              ← Example data for all endpoints
├── API_ENUMS.md                         ← Enum values and validation rules
├── JOD_Dashboard_API.postman_collection.json ← Updated Postman with examples
└── DASHBOARD_API_ENDPOINTS_PLAN.md     ← Original endpoint specifications
```

---

## ✅ NEXT STEPS

1. **Import Postman Collection**
   - Open Postman → Import → Select `JOD_Dashboard_API.postman_collection.json`
   - Set variables: `base_url=http://localhost/api/v1`, `token=your_token`

2. **Implement Backend Validation**
   - Use `API_ENUMS.md` for Laravel validation rules
   - Apply status/enum constraints from enumeration guide

3. **Frontend Integration**
   - Reference `API_ENUMS.md` for form dropdowns/selects
   - Use `SEED_DATA_REFERENCE.md` for mock API responses
   - Map sort parameters per the compatibility guide

4. **Database Seeding**
   - Use examples from `SEED_DATA_REFERENCE.md` for seed factories
   - Create seeders for default roles, badges, and permissions

5. **Documentation**
   - Include both markdown files in API documentation
   - Reference Postman collection for live testing

---

## 📞 DOCUMENTATION INCLUDES

✅ Complete seed data for all endpoints  
✅ Full request/response examples  
✅ All enum values with validation rules  
✅ Sort parameter compatibility map  
✅ Pagination reference  
✅ Filter examples  
✅ Authentication requirements  
✅ Error response examples  
✅ HTTP status codes  
✅ Validation constraints  

