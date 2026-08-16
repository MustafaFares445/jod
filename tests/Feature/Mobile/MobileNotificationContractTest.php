<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Notification;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileNotificationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_contract_maps_mobile_type_and_action_metadata(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'recipient_id' => $user->id,
            'category' => 'applicant',
            'reference_label' => 'عرض الطلب',
            'reference_path' => '/applications/application-1',
        ]);
        Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

        $this->getJson("/api/mobile/me/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'volunteer')
            ->assertJsonPath('data.actionLabel', 'عرض الطلب')
            ->assertJsonPath('data.action.label', 'عرض الطلب')
            ->assertJsonPath('data.action.route', '/applications/application-1')
            ->assertJsonPath('data.referenceLabel', 'عرض الطلب')
            ->assertJsonPath('data.referencePath', '/applications/application-1');
    }

    public function test_campaign_and_donation_categories_map_to_campaign_type(): void
    {
        $user = User::factory()->create();
        Notification::factory()->create([
            'recipient_id' => $user->id,
            'category' => 'campaign',
            'title' => 'Campaign update',
        ]);
        Notification::factory()->create([
            'recipient_id' => $user->id,
            'category' => 'donation',
            'title' => 'Donation received',
        ]);
        Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

        $response = $this->getJson('/api/mobile/me/notifications?perPage=10');
        $response->assertOk()
            ->assertJsonPath('data.0.type', 'campaign')
            ->assertJsonPath('data.1.type', 'campaign');
    }

    public function test_post_notifications_only_map_to_saved_when_reference_is_saved_related(): void
    {
        $user = User::factory()->create();
        $saved = Notification::factory()->create([
            'recipient_id' => $user->id,
            'category' => 'post',
            'reference_label' => 'المنشورات المحفوظة',
            'reference_path' => '/saved-posts',
            'sent_at' => now(),
        ]);
        $general = Notification::factory()->create([
            'recipient_id' => $user->id,
            'category' => 'post',
            'reference_label' => 'عرض المنشور',
            'reference_path' => '/posts/post-1',
            'sent_at' => now()->subMinute(),
        ]);
        Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

        $this->getJson("/api/mobile/me/notifications/{$saved->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'saved');

        $this->getJson("/api/mobile/me/notifications/{$general->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'system');
    }

    public function test_notification_without_reference_has_null_action(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'recipient_id' => $user->id,
            'category' => 'system',
            'reference_label' => null,
            'reference_path' => null,
        ]);
        Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

        $this->getJson("/api/mobile/me/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'system')
            ->assertJsonPath('data.actionLabel', null)
            ->assertJsonPath('data.action', null);
    }
}
