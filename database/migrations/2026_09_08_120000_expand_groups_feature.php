<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->boolean('requires_post_approval')->default(false)->after('rules');
        });

        Schema::create('group_categories', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('group_id');
            $table->string('category', 120)->index();
            $table->timestamps();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unique(['group_id', 'category']);
        });

        Schema::create('group_invitations', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('group_id');
            $table->string('invited_user_id');
            $table->string('invited_by');
            $table->enum('status', ['pending', 'accepted', 'declined', 'cancelled'])->default('pending')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('invited_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['group_id', 'invited_user_id']);
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->string('group_id')->nullable()->after('organization_id')->index();
            $table->string('group_review_status', 30)->nullable()->after('status')->index();
            $table->text('group_rejection_reason')->nullable()->after('group_review_status');
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
        });

        DB::table('groups')->select(['id', 'category', 'status', 'owner_id', 'proposed_admin_ids'])->orderBy('id')->chunk(250, function ($groups): void {
            $now = now();
            foreach ($groups as $group) {
                if (filled($group->category)) {
                    DB::table('group_categories')->insertOrIgnore([
                        'id' => (string) Str::uuid(),
                        'group_id' => (string) $group->id,
                        'category' => (string) $group->category,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $ids = json_decode((string) ($group->proposed_admin_ids ?? '[]'), true);
                if (! is_array($ids)) continue;
                foreach (array_unique(array_filter($ids)) as $userId) {
                    if ((string) $userId === (string) $group->owner_id) continue;
                    DB::table('group_invitations')->insertOrIgnore([
                        'id' => (string) Str::uuid(),
                        'group_id' => (string) $group->id,
                        'invited_user_id' => (string) $userId,
                        'invited_by' => (string) $group->owner_id,
                        'status' => $group->status === 'active' ? 'accepted' : 'pending',
                        'responded_at' => $group->status === 'active' ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        // Move legacy group posts into the canonical posts table using the same UUID.
        // This preserves comment IDs/references and lets all future group content reuse
        // normal JOD images, likes, help offers, applications, and feed contracts.
        DB::table('group_posts')->orderBy('id')->chunk(250, function ($rows): void {
            foreach ($rows as $row) {
                $group = DB::table('groups')->where('id', $row->group_id)->first(['location']);
                DB::table('posts')->insertOrIgnore([
                    'id' => (string) $row->id,
                    'title' => null,
                    'summary' => mb_substr((string) $row->body, 0, 255),
                    'content' => (string) $row->body,
                    'type' => 'awareness',
                    'status' => $row->status === 'published' ? 'published' : 'blocked',
                    'group_review_status' => $row->status === 'published' ? 'approved' : 'rejected',
                    'location' => $group?->location,
                    'group_id' => (string) $row->group_id,
                    'author_id' => $row->author_id,
                    'views_count' => 0,
                    'reactions_count' => (int) $row->likes_count,
                    'applications_count' => 0,
                    'published_at' => $row->status === 'published' ? $row->created_at : null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        });

        DB::table('group_post_likes')->orderBy('post_id')->chunk(500, function ($rows): void {
            $now = now();
            foreach ($rows as $row) {
                DB::table('post_likes')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'user_id' => (string) $row->user_id,
                    'post_id' => (string) $row->post_id,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]);
            }
        });

        Schema::table('group_comments', function (Blueprint $table): void {
            $table->dropForeign(['post_id']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        Schema::create('post_polls', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('post_id')->unique();
            $table->string('question', 500);
            $table->boolean('allows_multiple_choices')->default(false);
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        Schema::create('post_poll_options', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('poll_id');
            $table->string('label', 300);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('votes_count')->default(0);
            $table->timestamps();
            $table->foreign('poll_id')->references('id')->on('post_polls')->cascadeOnDelete();
            $table->index(['poll_id', 'position']);
        });

        Schema::create('post_poll_votes', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('poll_id');
            $table->string('option_id');
            $table->string('user_id');
            $table->timestamps();
            $table->foreign('poll_id')->references('id')->on('post_polls')->cascadeOnDelete();
            $table->foreign('option_id')->references('id')->on('post_poll_options')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['option_id', 'user_id']);
            $table->index(['poll_id', 'user_id']);
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->string('organization_id')->nullable()->change();
            $table->string('group_id')->nullable()->after('organization_id')->index();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->string('organization_id')->nullable()->change();
            $table->string('group_id')->nullable()->after('organization_id')->index();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
        });

        Schema::table('campaign_applications', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->string('organization_id')->nullable()->change();
            $table->string('group_id')->nullable()->after('organization_id')->index();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_applications', function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });

        Schema::dropIfExists('post_poll_votes');
        Schema::dropIfExists('post_poll_options');
        Schema::dropIfExists('post_polls');

        Schema::table('group_comments', function (Blueprint $table): void {
            $table->dropForeign(['post_id']);
            $table->foreign('post_id')->references('id')->on('group_posts')->cascadeOnDelete();
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['group_id', 'group_review_status', 'group_rejection_reason']);
        });

        Schema::dropIfExists('group_invitations');
        Schema::dropIfExists('group_categories');
        Schema::table('groups', function (Blueprint $table): void {
            $table->dropColumn('requires_post_approval');
        });
    }
};
