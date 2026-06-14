# Seeded Auth Users and Permissions

This file documents the users created by `database/seeders/UserSeeder.php`.

Important:
- `UserSeeder` creates users with UUID primary keys.
- It does not assign permissions directly.
- Organization staff roles are defined separately in `database/seeders/StaffSeeder.php` and `database/seeders/RoleSeeder.php`.
- The current downstream staff seed data still uses placeholder user IDs, so those role links do not resolve cleanly to the UUID users created here.
- Seeded login password for all users: `Password123!`

## Users

| User | Email | Type | Organization | Current seeded permissions | Notes |
|---|---|---|---|---|---|
| John Admin | `admin@jod.com` | `admin` | none | none | Platform admin account only; privileged API access must be granted separately. |
| Sarah Owner | `owner@helpfoundation.org` | `general` | `org-001` | none | Org membership is seeded, but no matching permission assignment exists in `UserSeeder`. |
| Ahmed Mohammed | `ahmed@example.com` | `volunteer` | none | none | Volunteer account only. |
| Fatima Hassan | `fatima@example.com` | `donor` | none | none | Donor account only. |
| Mohammed Ali | `mohammed@example.com` | `job_seeker` | none | none | Job seeker account only. |
| Leila Manager | `manager@helpfoundation.org` | `general` | `org-001` | none | Intended org-staff account, but the current placeholder staff UUIDs do not line up with the seeded user UUIDs. |

## What This Means In Practice

- Seeded auth users are identity records only.
- If you need permissioned access in tests, use the shared `grantPermissions()` helper or attach a role/permission assignment explicitly in the fixture.
- For organization role examples, use [`SEEDER_DATA_MAPPING.md`](SEEDER_DATA_MAPPING.md) and the shared permission catalog in [`app/Support/Permissions/PermissionCatalog.php`](app/Support/Permissions/PermissionCatalog.php).
