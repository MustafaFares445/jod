<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('personal campaign is reviewed and accepts multiple independent completed donations', function () {
    $owner = User::factory()->create(['user_type'=>'user','status'=>'active']);
    $admin = User::factory()->create(['user_type'=>'admin','status'=>'active']);
    $donorA = User::factory()->create(['status'=>'active']);
    $donorB = User::factory()->create(['status'=>'active']);
    $category = Category::factory()->create(['name'=>'الصحة والعلاج','target'=>'campaign','status'=>'active']);

    Sanctum::actingAs($owner);
    $created = $this->postJson('/api/mobile/me/campaigns', [
        'title'=>'عملية قلب تجريبية',
        'summary'=>'حملة شخصية لاختبار جمع مساهمات متعددة لتغطية تكلفة العملية.',
        'categoryId'=>$category->id,
        'location'=>'Damascus',
        'goalAmount'=>1000,
        'beneficiariesCount'=>1,
    ])->assertOk()->assertJsonPath('data.status','pending');
    $campaignId = (string) $created->json('data.id');

    $postId = (string) \App\Models\Post::query()->where('campaign_id', $campaignId)->where('type', 'donation_campaign')->value('id');
    expect($postId)->not->toBe('');

    Sanctum::actingAs($admin);
    $this->patchJson("/api/posts/{$postId}", ['status' => 'published'])
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.type', 'donation_campaign')
        ->assertJsonPath('data.campaignStatus', 'active');

    Sanctum::actingAs($owner);
    $this->postJson("/api/mobile/campaigns/{$campaignId}/donations", ['amount'=>100,'contactMethod'=>'phone','paymentMethod'=>'cash'])->assertUnprocessable();

    $ids = [];
    foreach ([[$donorA,600],[$donorB,500]] as [$donor,$amount]) {
        Sanctum::actingAs($donor);
        $response = $this->postJson("/api/mobile/campaigns/{$campaignId}/donations", ['amount'=>$amount,'contactMethod'=>'phone','paymentMethod'=>'cash'])->assertOk()->assertJsonPath('data.status','pending');
        $ids[] = (string) $response->json('data.id');
    }

    Sanctum::actingAs($owner);
    foreach ($ids as $index => $id) {
        $this->patchJson("/api/mobile/me/donations/{$id}/accept")->assertOk()->assertJsonPath('data.status','accepted');
        $this->patchJson("/api/mobile/me/donations/{$id}/contact")->assertOk()->assertJsonPath('data.status','contacting');
        $this->patchJson("/api/mobile/me/donations/{$id}/agree")->assertOk()->assertJsonPath('data.status','agreed');
        $this->patchJson("/api/mobile/me/donations/{$id}/complete", ['confirmedAmount'=>$index===0?600:500])->assertOk()->assertJsonPath('data.status','completed');
    }

    $campaign = Campaign::query()->findOrFail($campaignId);
    expect((float)$campaign->raised_amount)->toBe(1100.0)
        ->and($campaign->status)->toBe('active')
        ->and((int)$campaign->donors_count)->toBe(2);
});
