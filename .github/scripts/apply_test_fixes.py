from pathlib import Path
import base64
import re

ROOT = Path(__file__).resolve().parents[2]


def write_if_changed(path: Path, content: str) -> None:
    if path.exists() and path.read_text() == content:
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content)


# Sanctum 4.3 actingAs() defaults to no abilities. All existing one-argument
# calls in these feature tests represent an authenticated access-token request;
# authorization-specific 403s are asserted by policies after the middleware.
for path in (ROOT / 'tests').rglob('*.php'):
    text = path.read_text()
    if 'Sanctum::actingAs' not in text:
        continue

    original = text
    lines = text.splitlines()
    changed = False

    for index, line in enumerate(lines):
        if 'Sanctum::actingAs(' not in line or '[TokenService::ACCESS_ABILITY]' in line:
            continue

        match = re.search(r'Sanctum::actingAs\((.+)\);', line)
        if match is None or ',' in match.group(1):
            continue

        lines[index] = (
            line[:match.start()]
            + f'Sanctum::actingAs({match.group(1)}, [TokenService::ACCESS_ABILITY]);'
            + line[match.end():]
        )
        changed = True

    if not changed:
        continue

    text = '\n'.join(lines) + ('\n' if original.endswith('\n') else '')

    if 'use App\\Services\\Auth\\TokenService;' not in text:
        lines = text.splitlines()
        insert_at = None
        for index, line in enumerate(lines):
            if line.startswith('use App\\'):
                insert_at = index + 1
        if insert_at is None:
            for index, line in enumerate(lines):
                if line.startswith('use Laravel\\Sanctum\\Sanctum;'):
                    insert_at = index
                    break
        if insert_at is None:
            raise RuntimeError(f'Could not place TokenService import in {path}')
        lines.insert(insert_at, 'use App\\Services\\Auth\\TokenService;')
        text = '\n'.join(lines) + ('\n' if original.endswith('\n') else '')

    path.write_text(text)


# The login test is explicitly an admin login. Organization permission sync is
# supposed to replace managed permissions for non-admin users.
auth_test = ROOT / 'tests/Feature/Auth/AuthEndpointsTest.php'
text = auth_test.read_text()
if "'user_type' => 'admin'," not in text.split("test('login synchronizes", 1)[0]:
    text = text.replace(
        "'last_active_at' => null,",
        "'last_active_at' => null,\n        'user_type' => 'admin',",
        1,
    )
auth_test.write_text(text)


# Pest global helper functions do not have a bound $this. Pass the organization
# into the helper explicitly instead.
contract_test = ROOT / 'tests/Feature/Org/OrganizationContractCompatibilityTest.php'
text = contract_test.read_text()
text = text.replace(
    "campaign(['status' => 'active'])",
    "organization_contract_campaign($this->organization, ['status' => 'active'])",
)
text = text.replace(
    "campaign(['status' => 'draft'])",
    "organization_contract_campaign($this->organization, ['status' => 'draft'])",
)
text = text.replace(
    'function campaign(array $overrides = []): Campaign',
    'function organization_contract_campaign(Organization $organization, array $overrides = []): Campaign',
)
helper_marker = 'function organization_contract_campaign(Organization $organization, array $overrides = []): Campaign'
if helper_marker in text:
    before, helper = text.split(helper_marker, 1)
    helper = helper.replace("'organization_id' => $this->organization->id,", "'organization_id' => $organization->id,", 1)
    text = before + helper_marker + helper
contract_test.write_text(text)


# Campaign.category is constrained to the same values accepted by CampaignRequest.
# These mobile tests use post types, not campaign category, to model donation or
# volunteer behavior, so use a valid neutral category for their persisted rows.
category_replacements = {
    'tests/Feature/Mobile/MobileCampaignApplicationTest.php': [
        ("'category' => 'donation'", "'category' => 'health'"),
        ("'category' => 'volunteer'", "'category' => 'health'"),
    ],
    'tests/Feature/Mobile/MobileDiscoveryTest.php': [
        ("'category' => 'volunteer'", "'category' => 'health'"),
    ],
    'tests/Feature/Mobile/MobileDiscoveryContractTest.php': [
        ("'category' => 'community'", "'category' => 'health'"),
    ],
    'tests/Feature/Mobile/MobileDonationHistoryContractTest.php': [
        ("'category' => 'donation'", "'category' => 'health'"),
    ],
    'tests/Feature/Mobile/MobileDonationTest.php': [
        ("'category' => 'emergency'", "'category' => 'health'"),
    ],
}
for relative, replacements in category_replacements.items():
    path = ROOT / relative
    text = path.read_text()
    for old, new in replacements:
        text = text.replace(old, new)
    path.write_text(text)


# API response middleware wraps 204 deletes as the frontend's 200 JSON envelope,
# and deleting a protected system role is a conflict (409), not validation (422).
for relative in [
    'tests/Feature/Org/RoleManagementTest.php',
    'tests/Feature/Org/StaffManagementTest.php',
]:
    path = ROOT / relative
    text = path.read_text()
    text = text.replace(
        '$response->assertNoContent();',
        "$response->assertOk()->assertJsonPath('message', 'Data deleted successfully.');",
    )
    if relative.endswith('RoleManagementTest.php'):
        target = "$response->assertStatus(422);"
        if target in text:
            text = text.replace(target, '$response->assertStatus(409);', 1)
    path.write_text(text)


write_if_changed(
    ROOT / 'database/factories/CampaignFactory.php',
    '''<?php

declare(strict_types=1);

namespace Database\\Factories;

use App\\Models\\Campaign;
use App\\Models\\Organization;
use Illuminate\\Database\\Eloquent\\Factories\\Factory;
use Illuminate\\Support\\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'category' => 'health',
            'status' => 'draft',
            'location' => fake()->city(),
            'organization_id' => Organization::factory(),
            'goal_amount' => 1000,
            'raised_amount' => 0,
            'beneficiaries_count' => 0,
            'donors_count' => 0,
            'applicants_count' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ];
    }
}
''',
)

write_if_changed(
    ROOT / 'database/factories/ReportFactory.php',
    '''<?php

declare(strict_types=1);

namespace Database\\Factories;

use App\\Models\\Organization;
use App\\Models\\Report;
use App\\Models\\User;
use Illuminate\\Database\\Eloquent\\Factories\\Factory;
use Illuminate\\Support\\Str;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'category' => 'other',
            'status' => 'new',
            'severity' => 'medium',
            'organization_id' => Organization::factory(),
            'reporter_id' => User::factory(),
            'evidence' => [],
            'timeline' => [],
        ];
    }
}
''',
)


# ExampleTest boots session/encryption services, so tests need a deterministic key.
phpunit = ROOT / 'phpunit.xml'
text = phpunit.read_text()
if '<env name="APP_KEY"' not in text:
    key = 'base64:' + base64.b64encode(bytes(32)).decode()
    text = text.replace(
        '<env name="APP_ENV" value="testing"/>',
        '<env name="APP_ENV" value="testing"/>\n        <env name="APP_KEY" value="' + key + '"/>',
        1,
    )
phpunit.write_text(text)
