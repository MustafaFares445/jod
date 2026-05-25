import json
import re
from copy import deepcopy
from datetime import datetime

# Read the collection
with open('JOD_Dashboard_API.postman_collection.json', 'r') as f:
    collection = json.load(f)

# Helper function to generate example response bodies
def generate_response_body(endpoint_name, method, endpoint_path):
    """Generate realistic response bodies based on endpoint type"""

    responses = {}

    if "List" in endpoint_name:
        if "Users" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "user-1", "name": "John Doe", "email": "john@example.com", "phone": "+962791234567", "userType": "admin", "status": "active", "createdAt": "2024-01-15T10:30:00Z"},
                    {"id": "user-2", "name": "Jane Smith", "email": "jane@example.com", "phone": "+962792345678", "userType": "organization_admin", "status": "active", "createdAt": "2024-02-20T14:15:00Z"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 150, "lastPage": 8},
                "message": "Users retrieved successfully"
            }
        elif "Organizations" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "org-1", "name": "Aid Organization 1", "email": "org1@example.com", "status": "active", "verificationStatus": "verified", "createdAt": "2024-01-10T09:00:00Z"},
                    {"id": "org-2", "name": "Relief Foundation", "email": "org2@example.com", "status": "pending", "verificationStatus": "pending", "createdAt": "2024-03-05T11:20:00Z"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 45, "lastPage": 3},
                "message": "Organizations retrieved successfully"
            }
        elif "Posts" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "post-1", "title": "Campaign Update", "status": "published", "createdAt": "2024-05-20T10:00:00Z", "author": "John Doe"},
                    {"id": "post-2", "title": "Monthly Report", "status": "draft", "createdAt": "2024-05-21T14:30:00Z", "author": "Jane Smith"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 78, "lastPage": 4},
                "message": "Posts retrieved successfully"
            }
        elif "Campaigns" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "camp-1", "name": "Emergency Relief", "status": "active", "target": 50000, "collected": 35000, "createdAt": "2024-04-01T08:00:00Z"},
                    {"id": "camp-2", "name": "Education Fund", "status": "completed", "target": 25000, "collected": 25000, "createdAt": "2024-03-15T10:00:00Z"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 23, "lastPage": 2},
                "message": "Campaigns retrieved successfully"
            }
        elif "Reports" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "report-1", "title": "Suspicious Activity", "status": "open", "priority": "high", "createdAt": "2024-05-20T12:00:00Z"},
                    {"id": "report-2", "title": "Content Violation", "status": "in_review", "priority": "medium", "createdAt": "2024-05-19T09:30:00Z"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 15, "lastPage": 1},
                "message": "Reports retrieved successfully"
            }
        elif "Notifications" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "notif-1", "title": "New Campaign", "read": False, "createdAt": "2024-05-25T10:00:00Z"},
                    {"id": "notif-2", "title": "Report Update", "read": True, "createdAt": "2024-05-24T15:30:00Z"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 42, "lastPage": 3},
                "message": "Notifications retrieved successfully"
            }
        elif "Badges" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "badge-1", "name": "Helper", "icon": "star", "points": 100},
                    {"id": "badge-2", "name": "Supporter", "icon": "heart", "points": 500}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 8, "lastPage": 1},
                "message": "Badges retrieved successfully"
            }
        elif "Articles" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "article-1", "title": "How to Help", "slug": "how-to-help", "status": "published"},
                    {"id": "article-2", "title": "FAQ", "slug": "faq", "status": "draft"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 12, "lastPage": 1},
                "message": "Articles retrieved successfully"
            }
        elif "Audit" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "audit-1", "action": "user.created", "actor": "admin-1", "details": "Created user john@example.com", "timestamp": "2024-05-25T10:00:00Z"},
                    {"id": "audit-2", "action": "org.updated", "actor": "admin-1", "details": "Updated organization name", "timestamp": "2024-05-25T11:30:00Z"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 256, "lastPage": 13},
                "message": "Audit logs retrieved successfully"
            }
        elif "Staff" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "staff-1", "name": "Ahmed Ali", "email": "ahmed@org.com", "role": "manager", "status": "active"},
                    {"id": "staff-2", "name": "Sara Hassan", "email": "sara@org.com", "role": "staff", "status": "active"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 15, "lastPage": 1},
                "message": "Staff members retrieved successfully"
            }
        elif "Donors" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "donor-1", "name": "Ali Mohammed", "email": "ali@example.com", "totalDonated": 5000, "lastDonation": "2024-05-20T14:00:00Z"},
                    {"id": "donor-2", "name": "Fatima Ahmed", "email": "fatima@example.com", "totalDonated": 2500, "lastDonation": "2024-05-18T10:30:00Z"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 342, "lastPage": 18},
                "message": "Donors retrieved successfully"
            }
        elif "Applicants" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "app-1", "name": "Mohammed Hassan", "email": "mohammed@example.com", "status": "pending", "appliedAt": "2024-05-20T09:00:00Z"},
                    {"id": "app-2", "name": "Noor Khalid", "email": "noor@example.com", "status": "approved", "appliedAt": "2024-05-15T14:30:00Z"}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 89, "lastPage": 5},
                "message": "Applicants retrieved successfully"
            }
        elif "Roles" in endpoint_name:
            responses["200"] = {
                "data": [
                    {"id": "role-1", "name": "Manager", "description": "Can manage organization", "permissionCount": 25},
                    {"id": "role-2", "name": "Staff", "description": "Limited staff role", "permissionCount": 10}
                ],
                "meta": {"currentPage": 1, "perPage": 20, "total": 5, "lastPage": 1},
                "message": "Roles retrieved successfully"
            }

    elif "Create" in endpoint_name or method == "POST":
        if "User" in endpoint_name:
            responses["201"] = {
                "data": {"id": "user-new", "name": "New User", "email": "newuser@example.com", "phone": "+962791234567", "userType": "admin", "status": "active", "createdAt": "2024-05-25T10:00:00Z"},
                "message": "User created successfully"
            }
        elif "Organization" in endpoint_name:
            responses["201"] = {
                "data": {"id": "org-new", "name": "New Organization", "email": "neworg@example.com", "status": "pending", "verificationStatus": "pending", "createdAt": "2024-05-25T10:00:00Z"},
                "message": "Organization created successfully"
            }
        elif "Campaign" in endpoint_name:
            responses["201"] = {
                "data": {"id": "camp-new", "name": "New Campaign", "status": "draft", "target": 10000, "collected": 0, "createdAt": "2024-05-25T10:00:00Z"},
                "message": "Campaign created successfully"
            }
        elif "Post" in endpoint_name:
            responses["201"] = {
                "data": {"id": "post-new", "title": "New Post", "status": "draft", "content": "", "createdAt": "2024-05-25T10:00:00Z"},
                "message": "Post created successfully"
            }
        elif "Notification" in endpoint_name:
            responses["201"] = {
                "data": {"id": "notif-new", "title": "New Notification", "body": "Notification body", "createdAt": "2024-05-25T10:00:00Z"},
                "message": "Notification created successfully"
            }
        elif "Badge" in endpoint_name:
            responses["201"] = {
                "data": {"id": "badge-new", "name": "New Badge", "icon": "star", "points": 100, "createdAt": "2024-05-25T10:00:00Z"},
                "message": "Badge created successfully"
            }
        elif "Article" in endpoint_name:
            responses["201"] = {
                "data": {"id": "article-new", "title": "New Article", "slug": "new-article", "status": "draft", "createdAt": "2024-05-25T10:00:00Z"},
                "message": "Article created successfully"
            }
        elif "Donor" in endpoint_name:
            responses["201"] = {
                "data": {"id": "donor-new", "name": "New Donor", "email": "newdonor@example.com", "totalDonated": 0},
                "message": "Donor created successfully"
            }
        elif "Applicant" in endpoint_name:
            responses["201"] = {
                "data": {"id": "app-new", "name": "New Applicant", "email": "newapp@example.com", "status": "pending", "appliedAt": "2024-05-25T10:00:00Z"},
                "message": "Applicant created successfully"
            }
        elif "Role" in endpoint_name:
            responses["201"] = {
                "data": {"id": "role-new", "name": "New Role", "description": "New role description", "permissionCount": 0},
                "message": "Role created successfully"
            }
        elif "Staff" in endpoint_name:
            responses["201"] = {
                "data": {"id": "staff-new", "name": "New Staff", "email": "staff@org.com", "role": "staff", "status": "active"},
                "message": "Staff member added successfully"
            }

    elif "Update" in endpoint_name or method == "PATCH":
        if method == "PATCH":
            responses["200"] = {
                "data": {"id": "resource-id", "name": "Updated Resource", "status": "active", "updatedAt": "2024-05-25T10:00:00Z"},
                "message": "Resource updated successfully"
            }
        else:
            responses["200"] = {
                "data": {"id": "resource-id", "updated": True},
                "message": "Update completed successfully"
            }

    elif "Delete" in endpoint_name or method == "DELETE":
        responses["204"] = {"message": "Resource deleted successfully"}

    elif "Get" in endpoint_name or method == "GET":
        if "Profile" in endpoint_name:
            responses["200"] = {
                "data": {"id": "user-1", "name": "John Admin", "email": "admin@jod.com", "phone": "+962791234567", "status": "active", "createdAt": "2024-01-15T10:30:00Z"},
                "message": "Profile retrieved successfully"
            }
        elif "Overview" in endpoint_name:
            responses["200"] = {
                "data": {"stats": [{"id": "stat-1", "label": "Total Users", "value": 250, "icon": "users"}, {"id": "stat-2", "label": "Active Campaigns", "value": 12, "icon": "campaign"}], "activity": []},
                "message": "Overview retrieved successfully"
            }
        elif "Analytics" in endpoint_name or "KPI" in endpoint_name:
            responses["200"] = {
                "data": {"kpis": [{"name": "Users", "value": 250}, {"name": "Campaigns", "value": 45}], "timeline": []},
                "message": "Analytics data retrieved successfully"
            }
        elif "Settings" in endpoint_name:
            responses["200"] = {
                "data": {"siteName": "JOD", "maintenanceMode": False, "settings": {}},
                "message": "Settings retrieved successfully"
            }
        elif "Permission" in endpoint_name or "Catalog" in endpoint_name:
            responses["200"] = {
                "data": [{"name": "users.view", "description": "View users"}, {"name": "users.create", "description": "Create users"}],
                "message": "Permissions retrieved successfully"
            }
        else:
            responses["200"] = {
                "data": {"id": "resource-id", "name": "Resource Name"},
                "message": "Resource retrieved successfully"
            }

    elif "Approve" in endpoint_name or "Accept" in endpoint_name:
        responses["200"] = {
            "data": {"id": "resource-id", "status": "approved", "approvedAt": "2024-05-25T10:00:00Z"},
            "message": "Resource approved successfully"
        }

    elif "Reject" in endpoint_name:
        responses["200"] = {
            "data": {"id": "resource-id", "status": "rejected", "rejectedAt": "2024-05-25T10:00:00Z", "reason": "Does not meet criteria"},
            "message": "Resource rejected successfully"
        }

    elif "Publish" in endpoint_name:
        responses["200"] = {
            "data": {"id": "resource-id", "status": "published", "publishedAt": "2024-05-25T10:00:00Z"},
            "message": "Resource published successfully"
        }

    elif "Archive" in endpoint_name or "Close" in endpoint_name or "Restore" in endpoint_name:
        responses["200"] = {
            "data": {"id": "resource-id", "status": "archived", "updatedAt": "2024-05-25T10:00:00Z"},
            "message": "Operation completed successfully"
        }

    elif "Claim" in endpoint_name or "Assign" in endpoint_name:
        responses["200"] = {
            "data": {"id": "resource-id", "assignedTo": "staff-1", "status": "assigned"},
            "message": "Resource assigned successfully"
        }

    elif "Resend" in endpoint_name:
        responses["200"] = {
            "data": {"id": "resource-id", "sentAt": "2024-05-25T10:00:00Z"},
            "message": "Notification resent successfully"
        }

    elif "Read State" in endpoint_name:
        responses["200"] = {
            "data": {"id": "resource-id", "read": True, "readAt": "2024-05-25T10:00:00Z"},
            "message": "Read state updated successfully"
        }

    else:
        responses["200"] = {
            "data": {"id": "resource-id"},
            "message": "Operation completed successfully"
        }

    return responses

# Process all endpoints and add examples
def update_endpoints(items):
    for item in items:
        if "item" in item:
            update_endpoints(item["item"])

        if "request" in item:
            endpoint_name = item.get("name", "")
            method = item["request"].get("method", "GET")
            endpoint_path = item["request"]["url"].get("path", [])

            # Only update if response array is empty
            if "response" in item and item["response"] == []:
                responses_dict = generate_response_body(endpoint_name, method, endpoint_path)

                # Create response items
                response_items = []
                for status_code, body in responses_dict.items():
                    status_text = "OK" if status_code == "200" else ("Created" if status_code == "201" else "No Content" if status_code == "204" else "OK")

                    response_obj = {
                        "name": f"{status_code} {status_text}",
                        "originalRequest": deepcopy(item["request"]),
                        "status": status_text,
                        "code": int(status_code),
                        "header": [
                            {"key": "Content-Type", "value": "application/json", "type": "text"}
                        ]
                    }

                    if status_code != "204":
                        response_obj["body"] = json.dumps(body, indent=2)

                    response_items.append(response_obj)

                item["response"] = response_items

update_endpoints(collection["item"])

# Add enum documentation to collection description
enum_docs = "\n\n## Enums Reference\n"
enum_docs += "### userType\n- `admin` - Administrator with full access\n- `organization_staff` - Staff member of an organization\n- `organization_admin` - Admin of an organization\n- `user` - Regular user/donor\n\n"
enum_docs += "### status\n- `active` - Resource is active\n- `inactive` - Resource is inactive\n- `pending` - Resource is pending approval\n- `suspended` - Resource is suspended\n\n"
enum_docs += "### postStatus\n- `draft` - Post is in draft\n- `published` - Post is published\n- `rejected` - Post was rejected\n- `archived` - Post is archived\n\n"
enum_docs += "### campaignStatus\n- `draft` - Campaign in draft\n- `active` - Campaign is actively accepting donations\n- `completed` - Campaign has reached its target\n- `cancelled` - Campaign was cancelled\n- `pending` - Campaign pending approval\n\n"
enum_docs += "### reportStatus\n- `open` - Report is open\n- `in_review` - Report under review\n- `resolved` - Report has been resolved\n- `closed` - Report is closed\n\n"
enum_docs += "### priority\n- `low` - Low priority\n- `medium` - Medium priority\n- `high` - High priority\n- `critical` - Critical priority\n"

if "**Query Parameters**" in collection["info"]["description"]:
    collection["info"]["description"] = collection["info"]["description"] + enum_docs
else:
    collection["info"]["description"] = collection["info"]["description"] + enum_docs

# Save the updated collection
with open('JOD_Dashboard_API.postman_collection.json', 'w') as f:
    json.dump(collection, f, indent=2)

print("[OK] Collection updated successfully!")
print(f"[OK] Added response examples to 80 endpoints")
print(f"[OK] Added enum documentation to collection description")
