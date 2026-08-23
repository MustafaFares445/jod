<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Media;
use App\Services\Auth\CompanyRegistrationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

test('pending organization owner can upload replace and delete logo through general media api', function (): void {
    $user = media_test_owner();
    Sanctum::actingAs($user);
    $organizationId = (string) $user->organization_id;

    $upload = $this->post("/api/v1/media/organization/{$organizationId}/logo", [
        'file' => UploadedFile::fake()->image('logo.png'),
    ], ['Accept' => 'application/json']);

    $upload->assertCreated()
        ->assertJsonPath('data.model', 'organization')
        ->assertJsonPath('data.modelId', $organizationId)
        ->assertJsonPath('data.prop', 'logo');

    $mediaId = (string) $upload->json('data.id');
    $originalPath = Media::query()->findOrFail($mediaId)->path;
    Storage::disk('public')->assertExists($originalPath);

    $this->post("/api/v1/media/organization/{$organizationId}/logo", [
        'file' => UploadedFile::fake()->image('duplicate.png'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();

    $replace = $this->post("/api/v1/media/organization/{$organizationId}/logo/{$mediaId}/replace", [
        'file' => UploadedFile::fake()->image('replacement.webp'),
    ], ['Accept' => 'application/json']);

    $replace->assertOk()->assertJsonPath('data.id', $mediaId);
    $replacementPath = Media::query()->findOrFail($mediaId)->path;
    expect($replacementPath)->not->toBe($originalPath);
    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($replacementPath);

    $this->deleteJson("/api/v1/media/organization/{$organizationId}/logo/{$mediaId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    Storage::disk('public')->assertMissing($replacementPath);
});

test('campaign media is scoped to target model id and prop', function (): void {
    $user = media_test_owner();
    Sanctum::actingAs($user);

    $campaign = Campaign::query()->create([
        'title' => 'Media campaign',
        'summary' => 'Campaign summary',
        'category' => 'health',
        'status' => 'active',
        'location' => 'Damascus',
        'organization_id' => $user->organization_id,
        'creator_id' => $user->id,
        'goal_amount' => 1000,
        'beneficiaries_count' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    $otherCampaign = Campaign::query()->create([
        'title' => 'Other media campaign',
        'summary' => 'Other summary',
        'category' => 'health',
        'status' => 'active',
        'location' => 'Damascus',
        'organization_id' => $user->organization_id,
        'creator_id' => $user->id,
        'goal_amount' => 1000,
        'beneficiaries_count' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    $upload = $this->post("/api/v1/media/campaign/{$campaign->id}/images", [
        'file' => UploadedFile::fake()->image('campaign.jpg'),
    ], ['Accept' => 'application/json']);

    $upload->assertCreated();
    $mediaId = (string) $upload->json('data.id');

    $this->post("/api/v1/media/campaign/{$otherCampaign->id}/images/{$mediaId}/replace", [
        'file' => UploadedFile::fake()->image('wrong-target.jpg'),
    ], ['Accept' => 'application/json'])->assertNotFound();

    $this->deleteJson("/api/v1/media/campaign/{$otherCampaign->id}/images/{$mediaId}")
        ->assertNotFound();

    $this->assertDatabaseHas('media', [
        'id' => $mediaId,
        'model_type' => 'campaign',
        'model_id' => $campaign->id,
        'prop' => 'images',
    ]);
});

test('invalid model prop and invalid file are rejected', function (): void {
    $user = media_test_owner();
    Sanctum::actingAs($user);

    $this->post("/api/v1/media/organization/{$user->organization_id}/images", [
        'file' => UploadedFile::fake()->image('wrong-prop.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prop']);

    $this->post("/api/v1/media/organization/{$user->organization_id}/logo", [
        'file' => UploadedFile::fake()->create('not-image.txt', 10, 'text/plain'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

function media_test_owner(): \App\Models\User
{
    $suffix = Str::lower(Str::random(10));

    return app(CompanyRegistrationService::class)->register([
        'companyName' => 'Media Org '.$suffix,
        'ownerName' => 'Media Owner',
        'organizationNumber' => 'ORG-'.$suffix,
        'registrationNumber' => 'REG-'.$suffix,
        'bankAccountNumber' => 'BANK-'.$suffix,
        'companyEmail' => "media-{$suffix}@example.test",
        'companyPhone' => '0999999999',
        'location' => 'Damascus',
        'website' => null,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
}
