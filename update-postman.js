const fs = require('fs');
const path = require('path');

// Read the current Postman collection
const collectionPath = 'JOD_Dashboard_API.postman_collection.json';
const collection = JSON.parse(fs.readFileSync(collectionPath, 'utf8'));

// Seed data and examples for each endpoint
const examples = {
  'Get Current User Profile': {
    method: 'GET',
    url: '{{base_url}}/me',
    body: null,
    response: {
      data: {
        id: 'user-123',
        name: 'John Admin',
        email: 'admin@jod.com',
        phone: '+962791234567',
        userType: 'admin',
        status: 'active',
        createdAt: '2024-01-15T10:30:00Z',
        lastActiveAt: '2025-05-25T14:22:00Z'
      },
      message: 'User profile retrieved successfully'
    }
  },
  'Get User Permissions': {
    method: 'GET',
    url: '{{base_url}}/me/permissions',
    body: null,
    response: {
      data: {
        modules: [
          {
            name: 'Dashboard',
            permissions: ['dashboard.view']
          },
          {
            name: 'Users Management',
            permissions: ['users.view', 'users.create', 'users.update', 'users.delete', 'users.reset_password']
          }
        ],
        flat: {
          'dashboard.view': true,
          'users.view': true,
          'users.create': true
        },
        granted: ['dashboard.view', 'users.view', 'users.create']
      },
      message: 'Permissions retrieved successfully'
    }
  },
  'Get Dashboard Context (Bootstrap)': {
    method: 'GET',
    url: '{{base_url}}/me/dashboard-context',
    body: null,
    response: {
      data: {
        profile: {
          id: 'user-123',
          name: 'John Admin',
          email: 'admin@jod.com',
          userType: 'admin',
          status: 'active'
        },
        permissions: {
          'dashboard.view': true,
          'users.view': true
        },
        counters: {
          unreadNotifications: 5,
          pendingReviews: 12,
          openReports: 3
        }
      },
      message: 'Dashboard context loaded'
    }
  },
  'Get Admin Overview': {
    method: 'GET',
    url: '{{base_url}}/admin/overview',
    body: null,
    response: {
      data: {
        stats: [
          {
            id: 'total-users',
            label: 'Total Users',
            value: 15420,
            subLabel: '+234 this week',
            icon: 'users'
          }
        ],
        activity: [
          {
            id: 'activity-1',
            title: 'New organization registered',
            detail: 'Tech for Good Foundation started their profile',
            at: '2025-05-25T14:22:00Z'
          }
        ]
      },
      message: 'Overview data retrieved'
    }
  },
  'List Admin Users': {
    method: 'GET',
    url: '{{base_url}}/admin/users?page=1&perPage=20&sort=-createdAt',
    body: null,
    response: {
      data: [
        {
          id: 'user-123',
          name: 'John Admin',
          email: 'admin@jod.com',
          phone: '+962791234567',
          role: 'admin',
          status: 'active',
          postsCount: 0,
          reportsCount: 5,
          createdAt: '2024-01-15T10:30:00Z',
          lastActiveAt: '2025-05-25T14:22:00Z'
        }
      ],
      meta: {
        currentPage: 1,
        perPage: 20,
        total: 15420,
        lastPage: 771
      },
      message: 'Users retrieved successfully'
    }
  },
  'Create User': {
    method: 'POST',
    url: '{{base_url}}/admin/users',
    body: {
      name: 'New User',
      email: 'newuser@example.com',
      phone: '+962791234570',
      role: 'volunteer',
      status: 'active'
    },
    response: {
      data: {
        id: 'user-999',
        name: 'New User',
        email: 'newuser@example.com',
        phone: '+962791234570',
        role: 'volunteer',
        status: 'active',
        createdAt: '2025-05-25T15:00:00Z'
      },
      message: 'User created successfully'
    }
  },
  'Update User': {
    method: 'PATCH',
    url: '{{base_url}}/admin/users/{userId}',
    body: {
      name: 'Updated Name',
      email: 'updated@example.com',
      phone: '+962791234567',
      status: 'active'
    },
    response: {
      data: {
        id: 'user-123',
        name: 'Updated Name',
        email: 'updated@example.com',
        phone: '+962791234567',
        status: 'active'
      },
      message: 'User updated successfully'
    }
  },
  'Update User Status': {
    method: 'PATCH',
    url: '{{base_url}}/admin/users/{userId}/status',
    body: {
      status: 'inactive'
    },
    response: {
      data: {
        id: 'user-123',
        status: 'inactive'
      },
      message: 'User status updated'
    }
  },
  'Change User Password': {
    method: 'PATCH',
    url: '{{base_url}}/admin/users/{userId}/password',
    body: {
      newPassword: 'NewSecurePassword456!',
      confirmPassword: 'NewSecurePassword456!'
    },
    response: {
      message: 'Password updated successfully'
    }
  },
  'Delete User': {
    method: 'DELETE',
    url: '{{base_url}}/admin/users/{userId}',
    body: null,
    response: {
      message: 'User deleted successfully'
    }
  },
  'List Organizations': {
    method: 'GET',
    url: '{{base_url}}/admin/organizations?page=1&perPage=20&filter.status=active',
    body: null,
    response: {
      data: [
        {
          id: 'org-001',
          name: 'Help Foundation',
          email: 'contact@helpfoundation.org',
          phone: '+962796543210',
          location: 'Amman, Jordan',
          verificationStatus: 'verified',
          status: 'active',
          campaignsCount: 12,
          postsCount: 45,
          activeVolunteersCount: 23,
          activityScore: 8.5,
          createdAt: '2023-06-15T08:00:00Z',
          lastActiveAt: '2025-05-25T13:45:00Z'
        }
      ],
      meta: {
        currentPage: 1,
        perPage: 20,
        total: 342,
        lastPage: 18
      },
      message: 'Organizations retrieved successfully'
    }
  },
  'Get Organization Details': {
    method: 'GET',
    url: '{{base_url}}/admin/organizations/{organizationId}',
    body: null,
    response: {
      data: {
        id: 'org-001',
        name: 'Help Foundation',
        email: 'contact@helpfoundation.org',
        phone: '+962796543210',
        location: 'Amman, Jordan',
        verificationStatus: 'verified',
        status: 'active',
        organizationType: 'ngo',
        registrationNumber: 'NGO-2023-001',
        establishmentDate: '2020-03-15',
        shortAddress: '123 Help Street, Amman',
        description: 'Dedicated to providing humanitarian aid',
        ownerFullName: 'Sarah Ahmed',
        ownerEmail: 'sarah@helpfoundation.org',
        ownerPhone: '+962791234567',
        website: 'https://helpfoundation.org',
        acceptedAt: '2023-06-20T12:00:00Z'
      },
      message: 'Organization retrieved successfully'
    }
  },
  'List Posts for Review': {
    method: 'GET',
    url: '{{base_url}}/admin/review/posts?page=1&perPage=20&filter.status=pending',
    body: null,
    response: {
      data: [
        {
          id: 'post-001',
          title: 'Emergency flood relief needed',
          summary: 'Our area has been hit by severe flooding',
          organizationName: 'Help Foundation',
          authorName: 'Ahmed Hassan',
          location: 'Amman',
          submittedAt: '2025-05-25T14:00:00Z',
          status: 'pending',
          type: 'help_request',
          reviewedBy: null,
          rejectionReason: null
        }
      ],
      meta: {
        currentPage: 1,
        perPage: 20,
        total: 45,
        lastPage: 3
      },
      message: 'Posts retrieved for review'
    }
  },
  'Approve Post': {
    method: 'POST',
    url: '{{base_url}}/admin/review/posts/{postId}/approve',
    body: {
      note: 'Approved - content meets guidelines'
    },
    response: {
      data: {
        id: 'post-001',
        status: 'approved',
        reviewedAt: '2025-05-25T15:00:00Z'
      },
      message: 'Post approved successfully'
    }
  },
  'Reject Post': {
    method: 'POST',
    url: '{{base_url}}/admin/review/posts/{postId}/reject',
    body: {
      reason: 'Content violates community guidelines regarding sensitive topics'
    },
    response: {
      data: {
        id: 'post-001',
        status: 'rejected',
        rejectionReason: 'Content violates community guidelines regarding sensitive topics',
        reviewedAt: '2025-05-25T15:00:00Z'
      },
      message: 'Post rejected successfully'
    }
  },
  'List Campaigns for Review': {
    method: 'GET',
    url: '{{base_url}}/admin/review/campaigns?page=1&perPage=20&filter.status=pending',
    body: null,
    response: {
      data: [
        {
          id: 'campaign-001',
          title: 'Emergency Medical Fund',
          summary: 'Raising funds for emergency medical treatment',
          organizationName: 'Help Foundation',
          managerName: 'Sarah Ahmed',
          location: 'Amman',
          category: 'health',
          goalAmount: 50000,
          raisedAmount: 0,
          beneficiariesCount: 150,
          startDate: '2025-06-01',
          endDate: '2025-08-31',
          submittedAt: '2025-05-25T12:00:00Z',
          status: 'pending',
          reviewedBy: null,
          rejectionReason: null
        }
      ],
      meta: {
        currentPage: 1,
        perPage: 20,
        total: 23,
        lastPage: 2
      },
      message: 'Campaigns retrieved for review'
    }
  },
  'List Reports': {
    method: 'GET',
    url: '{{base_url}}/admin/reports?page=1&perPage=20&filter.status=new',
    body: null,
    response: {
      data: [
        {
          id: 'report-001',
          title: 'Suspicious campaign activity',
          description: 'Campaign claims are not matching actual activities',
          status: 'new',
          severity: 'high',
          entityType: 'campaign',
          entityId: 'campaign-789',
          organizationName: 'Unknown Organization',
          reporterName: 'Anonymous User',
          createdAt: '2025-05-25T14:30:00Z',
          assignee: null,
          timeline: [],
          evidence: []
        }
      ],
      meta: {
        currentPage: 1,
        perPage: 20,
        total: 3,
        lastPage: 1
      },
      message: 'Reports retrieved successfully'
    }
  },
  'List Org Campaigns': {
    method: 'GET',
    url: '{{base_url}}/org/campaigns?page=1&perPage=20&filter.status=active',
    body: null,
    response: {
      data: [
        {
          id: 'campaign-001',
          title: 'Emergency Medical Fund',
          summary: 'Raising funds for emergency medical treatment',
          category: 'health',
          status: 'active',
          location: 'Amman',
          goalAmount: 50000,
          raisedAmount: 35000,
          beneficiariesCount: 150,
          donorsCount: 234,
          applicantsCount: 45,
          startDate: '2025-06-01',
          endDate: '2025-08-31',
          createdAt: '2025-05-20T10:00:00Z',
          updatedAt: '2025-05-25T14:30:00Z'
        }
      ],
      meta: {
        currentPage: 1,
        perPage: 20,
        total: 12,
        lastPage: 1
      },
      message: 'Campaigns retrieved successfully'
    }
  },
  'List Organization Staff': {
    method: 'GET',
    url: '{{base_url}}/org/staff?page=1&perPage=20',
    body: null,
    response: {
      data: [
        {
          id: 'staff-001',
          name: 'Sarah Ahmed',
          email: 'sarah@helpfoundation.org',
          role: 'manager',
          invitedAt: '2025-03-15T10:00:00Z'
        }
      ],
      meta: {
        currentPage: 1,
        perPage: 20,
        total: 8,
        lastPage: 1
      },
      message: 'Staff members retrieved successfully'
    }
  },
  'List Organization Roles': {
    method: 'GET',
    url: '{{base_url}}/org/roles?page=1&perPage=20',
    body: null,
    response: {
      data: [
        {
          id: 'role-001',
          role: 'Editor',
          description: 'Can create and edit campaigns and posts',
          permissions: ['org.campaigns.view', 'org.campaigns.create', 'org.posts.view', 'org.posts.create'],
          updatedAt: '2025-05-20T10:00:00Z',
          isActive: true,
          isSystem: false,
          membersCount: 3
        }
      ],
      meta: {
        currentPage: 1,
        perPage: 20,
        total: 4,
        lastPage: 1
      },
      message: 'Roles retrieved successfully'
    }
  },
  'Get Permissions Catalog': {
    method: 'GET',
    url: '{{base_url}}/org/permissions/catalog',
    body: null,
    response: {
      data: [
        {
          group: 'Campaigns',
          permissions: [
            {
              key: 'org.campaigns.view',
              label: 'View Campaigns',
              description: 'Can view organization campaigns'
            },
            {
              key: 'org.campaigns.create',
              label: 'Create Campaigns',
              description: 'Can create new campaigns'
            }
          ]
        }
      ],
      message: 'Permissions catalog retrieved'
    }
  }
};

// Function to update request bodies
function updateRequestWithBody(item, exampleData) {
  if (exampleData.body && item.request) {
    if (!item.request.body) {
      item.request.body = {};
    }
    item.request.body.mode = 'raw';
    item.request.body.raw = JSON.stringify(exampleData.body, null, 2);
    item.request.header = item.request.header || [];

    // Ensure Content-Type header
    const hasContentType = item.request.header.some(h => h.key === 'Content-Type');
    if (!hasContentType) {
      item.request.header.push({
        key: 'Content-Type',
        value: 'application/json',
        type: 'text'
      });
    }
  }
}

// Function to update response examples
function updateResponse(item, exampleData) {
  if (!item.response) {
    item.response = [];
  }

  // Check if success response exists
  const successResponse = item.response.find(r => r.name === 'Success' || r.name === '200 OK');

  if (successResponse) {
    successResponse.body = JSON.stringify(exampleData.response, null, 2);
    if (!successResponse.header) {
      successResponse.header = [];
    }
    // Add Content-Type header if not exists
    const hasContentType = successResponse.header.some(h => h.key === 'Content-Type');
    if (!hasContentType) {
      successResponse.header.push({
        key: 'Content-Type',
        value: 'application/json',
        type: 'text'
      });
    }
  } else {
    // Create new success response
    item.response.push({
      name: '200 OK',
      originalRequest: item.request,
      status: 'OK',
      code: 200,
      header: [
        {
          key: 'Content-Type',
          value: 'application/json',
          type: 'text'
        }
      ],
      body: JSON.stringify(exampleData.response, null, 2)
    });
  }
}

// Recursively process all items
function processItems(items) {
  items.forEach(item => {
    // Check if this item matches any example
    const itemName = item.name;

    if (examples[itemName]) {
      const exampleData = examples[itemName];
      updateRequestWithBody(item, exampleData);
      updateResponse(item, exampleData);
    }

    // Process nested items
    if (item.item && item.item.length > 0) {
      processItems(item.item);
    }
  });
}

// Process all items in collection
processItems(collection.item);

// Save updated collection
fs.writeFileSync(collectionPath, JSON.stringify(collection, null, 2));
console.log('✓ Postman collection updated with comprehensive examples!');
console.log(`✓ Updated ${Object.keys(examples).length} endpoints`);
console.log('✓ Saved to:', collectionPath);
