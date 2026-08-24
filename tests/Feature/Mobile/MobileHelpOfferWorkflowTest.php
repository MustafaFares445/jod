<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('help offer needs both confirmations and does not auto fulfill post', function () {
    $owner = User::factory()->create();
    $helper = User::factory()->create();
    $post = Post::factory()->published()->create([
        'author_id' => $owner->id,
        'type' => 'help_request',
    ]);

    expect($post->refresh()->help_status->value)->toBe('open');

    Sanctum::actingAs($helper);
    $created = $this->postJson("/api/mobile/posts/{$post->id}/help-offers", [
        'type' => 'financial',
        'amount' => 300000,
        'description' => 'I can cover part of the need.',
        'contactMethod' => 'whatsapp',
        'phone' => '0912345678',
    ])->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $offerId = (string) $created->json('data.id');
    expect($post->refresh()->help_status->value)->toBe('open');

    Sanctum::actingAs($owner);
    $this->patchJson("/api/mobile/help-offers/{$offerId}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');
    expect($post->refresh()->help_status->value)->toBe('in_progress');

    Sanctum::actingAs($helper);
    $this->patchJson("/api/mobile/help-offers/{$offerId}/contact")
        ->assertOk()
        ->assertJsonPath('data.status', 'contacting');
    $this->patchJson("/api/mobile/help-offers/{$offerId}/agree")
        ->assertOk()
        ->assertJsonPath('data.status', 'agreed');
    $this->patchJson("/api/mobile/help-offers/{$offerId}/confirm-provided")
        ->assertOk()
        ->assertJsonPath('data.status', 'agreed');

    Sanctum::actingAs($owner);
    $this->patchJson("/api/mobile/help-offers/{$offerId}/confirm-received")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($post->refresh()->help_status->value)->toBe('open');

    $this->patchJson("/api/mobile/posts/{$post->id}/help-status", ['status' => 'fulfilled'])
        ->assertOk()
        ->assertJsonPath('data.helpStatus', 'fulfilled');
});

test('help offers reject self help duplicates and fulfilled requests', function () {
    $owner = User::factory()->create();
    $helper = User::factory()->create();
    $post = Post::factory()->published()->create([
        'author_id' => $owner->id,
        'type' => 'help_request',
    ]);

    Sanctum::actingAs($owner);
    $this->postJson("/api/mobile/posts/{$post->id}/help-offers", [
        'type' => 'food',
        'description' => 'self offer',
    ])->assertUnprocessable();

    Sanctum::actingAs($helper);
    $this->postJson("/api/mobile/posts/{$post->id}/help-offers", [
        'type' => 'food',
        'description' => 'First offer',
    ])->assertOk();
    $this->postJson("/api/mobile/posts/{$post->id}/help-offers", [
        'type' => 'food',
        'description' => 'Duplicate offer',
    ])->assertUnprocessable();

    Sanctum::actingAs($owner);
    $this->patchJson("/api/mobile/posts/{$post->id}/help-status", ['status' => 'fulfilled'])->assertOk();

    $anotherHelper = User::factory()->create();
    Sanctum::actingAs($anotherHelper);
    $this->postJson("/api/mobile/posts/{$post->id}/help-offers", [
        'type' => 'service',
    ])->assertUnprocessable();
});

test('cancelled accepted offer reopens request when no other progressing offer remains', function () {
    $owner = User::factory()->create();
    $helper = User::factory()->create();
    $post = Post::factory()->published()->create([
        'author_id' => $owner->id,
        'type' => 'help_request',
    ]);

    Sanctum::actingAs($helper);
    $response = $this->postJson("/api/mobile/posts/{$post->id}/help-offers", [
        'type' => 'transportation',
        'description' => 'I can provide transport.',
    ])->assertOk();
    $offerId = (string) $response->json('data.id');

    Sanctum::actingAs($owner);
    $this->patchJson("/api/mobile/help-offers/{$offerId}/accept")->assertOk();
    expect($post->refresh()->help_status->value)->toBe('in_progress');

    Sanctum::actingAs($helper);
    $this->patchJson("/api/mobile/help-offers/{$offerId}/cancel", [
        'reason' => 'helper_withdrew',
    ])->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect($post->refresh()->help_status->value)->toBe('open');
});
