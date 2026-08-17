<?php

declare(strict_types=1);

use Database\Seeders\Permissions\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
});

function dashboard_company_registration_payload(array $overrides = []): array
{
    return [
        'companyName' => 'JOD Contract Org',
        'companyEmail' => 'company-contract@example.com',
        'companyPhone' => '+962790000001',
        'organizationType' => 'charity',
        'registrationNumber' => 'REG-CONTRACT-001',
        'location' => 'Amman',
        'ownerName' => 'Contract Owner',
        'ownerEmail' => 'owner-contract@example.com',
        'ownerPhone' => '+962790000002',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'description' => 'Organization created by the dashboard contract test.',
        'website' => 'https://example.com',
        'establishmentDate' => now()->subYear()->toDateString(),
        ...$overrides,
    ];
}

test('company registration accepts the exact dashboard request props and returns login contract', function () {
    $payload = dashboard_company_registration_payload();

    $response = $this->postJson('/api/v1/company/auth/register', $payload)
        ->assertCreated()
        ->assertJsonPath('message', 'Company registered successfully')
        ->assertJsonPath('data.tokenType', 'Bearer')
        ->assertJsonPath('data.user.email', $payload['ownerEmail'])
        ->assertJsonPath('data.user.organizationId', fn ($value) => is_string($value) && $value !== '')
        ->assertJsonStructure([
            'data' => [
                'token',
                'refreshToken',
                'tokenType',
                'expiresIn',
                'refreshExpiresIn',
                'expiresAt',
                'refreshExpiresAt',
                'user',
                'permissions' => ['modules', 'flat', 'granted'],
            ],
            'message',
        ]);

    $this->assertDatabaseHas('organizations', [
        'name' => $payload['companyName'],
        'email' => $payload['companyEmail'],
        'registration_number' => $payload['registrationNumber'],
    ]);

    $this->assertDatabaseHas('users', [
        'name' => $payload['ownerName'],
        'email' => $payload['ownerEmail'],
    ]);
});

test('company registration reports validation errors using dashboard field names', function (string $field, mixed $value = null, ?string $errorField = null) {
    $payload = dashboard_company_registration_payload([$field => $value]);

    $this->postJson('/api/v1/company/auth/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorField ?? $field]);
})->with([
    'companyName is required' => ['companyName'],
    'companyEmail must be an email' => ['companyEmail', 'not-an-email'],
    'companyPhone is required' => ['companyPhone'],
    'organizationType is required' => ['organizationType'],
    'registrationNumber is required' => ['registrationNumber'],
    'location is required' => ['location'],
    'ownerName is required' => ['ownerName'],
    'ownerEmail must be an email' => ['ownerEmail', 'not-an-email'],
    'ownerPhone is required' => ['ownerPhone'],
    'password confirmation must match' => ['password_confirmation', 'different-password', 'password'],
    'website must be a URL' => ['website', 'not-a-url'],
    'establishmentDate cannot be in the future' => ['establishmentDate', '2099-01-01'],
]);

test('company login uses the dashboard login payload and returns the same token contract', function () {
    $payload = dashboard_company_registration_payload();
    $this->postJson('/api/v1/company/auth/register', $payload)->assertCreated();

    $this->postJson('/api/v1/company/auth/login', [
        'email' => $payload['ownerEmail'],
        'password' => $payload['password'],
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Company logged in successfully')
        ->assertJsonPath('data.tokenType', 'Bearer')
        ->assertJsonPath('data.user.email', $payload['ownerEmail'])
        ->assertJsonStructure([
            'data' => [
                'token',
                'refreshToken',
                'permissions' => ['modules', 'flat', 'granted'],
            ],
            'message',
        ]);
});

test('company login rejects invalid credentials with the response handled by the dashboard', function () {
    $this->postJson('/api/v1/company/auth/login', [
        'email' => 'missing-company@example.com',
        'password' => 'password123',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'The provided company credentials are incorrect.');
});
