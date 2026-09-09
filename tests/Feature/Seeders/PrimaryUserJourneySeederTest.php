<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PrimaryUserJourneySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PrimaryUserJourneySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_user_has_complete_mobile_journey_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = DB::table('users')->where('email', PrimaryUserJourneySeeder::EMAIL)->first();

        $this->assertNotNull($user);
        $this->assertSame(PrimaryUserJourneySeeder::NAME, (string) $user->name);
        $this->assertSame('active', (string) $user->status);
        $this->assertSame('general', (string) $user->user_type);
        $this->assertTrue(Hash::check(PrimaryUserJourneySeeder::PASSWORD, (string) $user->password));

        $userId = (string) $user->id;

        $helpRequests = DB::table('posts')
            ->where('author_id', $userId)
            ->where('type', 'help_request');

        $this->assertGreaterThanOrEqual(9, $helpRequests->count());
        $this->assertEqualsCanonicalizing(
            ['open', 'in_progress', 'fulfilled', 'partially_fulfilled', 'not_fulfilled', 'expired'],
            $helpRequests->where('status', 'published')->distinct()->pluck('help_status')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['draft', 'pending', 'published', 'blocked'],
            DB::table('posts')->where('author_id', $userId)->where('type', 'help_request')->distinct()->pluck('status')->all(),
        );
        $this->assertTrue(DB::table('posts')
            ->where('author_id', $userId)
            ->where('type', 'service_offer')
            ->where('status', 'published')
            ->exists());

        $this->assertEqualsCanonicalizing(
            ['pending', 'active', 'closed', 'rejected'],
            DB::table('campaigns')->where('creator_id', $userId)->distinct()->pluck('status')->all(),
        );

        $this->assertEqualsCanonicalizing(
            ['pending', 'accepted', 'contacting', 'agreed', 'completed', 'rejected', 'cancelled'],
            DB::table('help_offers')->where('helper_user_id', $userId)->distinct()->pluck('status')->all(),
        );
        $this->assertGreaterThanOrEqual(3, DB::table('help_offers')->where('post_owner_id', $userId)->count());

        $this->assertEqualsCanonicalizing(
            ['pending', 'accepted', 'contacting', 'agreed', 'completed', 'cancelled'],
            DB::table('donations')->where('created_by', $userId)->distinct()->pluck('status')->all(),
        );
        $this->assertGreaterThanOrEqual(3, DB::table('donations')
            ->join('campaigns', 'campaigns.id', '=', 'donations.campaign_id')
            ->where('campaigns.creator_id', $userId)
            ->where('donations.created_by', '!=', $userId)
            ->count());

        $this->assertGreaterThanOrEqual(1, DB::table('campaign_applications')->where('created_by', $userId)->count());
        $this->assertEqualsCanonicalizing(
            ['new', 'in_progress', 'closed', 'resolved'],
            DB::table('reports')->where('reporter_id', $userId)->distinct()->pluck('status')->all(),
        );

        $this->assertGreaterThanOrEqual(5, DB::table('post_likes')->where('user_id', $userId)->count());
        $this->assertGreaterThanOrEqual(4, DB::table('saved_posts')->where('user_id', $userId)->count());

        $preference = DB::table('user_preferences')->where('user_id', $userId)->first();
        $this->assertNotNull($preference);
        $this->assertSame('both', (string) $preference->intent);

        $this->assertGreaterThanOrEqual(8, DB::table('notifications')->where('recipient_id', $userId)->count());
        $this->assertTrue(DB::table('notifications')->where('recipient_id', $userId)->whereNull('read_at')->exists());
        $this->assertTrue(DB::table('notifications')->where('recipient_id', $userId)->whereNotNull('read_at')->exists());

        if (DB::getSchemaBuilder()->hasTable('group_members')) {
            $this->assertGreaterThanOrEqual(2, DB::table('group_members')->where('user_id', $userId)->where('status', 'active')->count());
        }

        if (DB::getSchemaBuilder()->hasTable('group_invitations')) {
            $this->assertGreaterThanOrEqual(1, DB::table('group_invitations')->where('invited_user_id', $userId)->where('status', 'pending')->count());
        }
    }
}
