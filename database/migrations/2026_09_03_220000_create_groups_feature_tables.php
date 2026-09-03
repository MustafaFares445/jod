<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_id')->nullable()->index();
            $table->string('organization_id')->nullable()->index();
            $table->string('name', 120);
            $table->text('description');
            $table->string('category', 120)->index();
            $table->string('location', 255)->nullable()->index();
            $table->enum('status', ['pending', 'active', 'rejected', 'suspended', 'archived'])->default('pending')->index();
            $table->text('purpose');
            $table->json('rules')->nullable();
            $table->json('proposed_admin_ids')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'category', 'location']);
        });

        Schema::create('group_members', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('group_id');
            $table->string('user_id');
            $table->enum('role', ['owner', 'admin', 'moderator', 'member'])->default('member');
            $table->enum('status', ['active', 'left', 'removed'])->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['group_id', 'user_id']);
            $table->index(['group_id', 'status', 'role']);
        });

        Schema::create('group_posts', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('group_id');
            $table->string('author_id')->nullable();
            $table->text('body');
            $table->enum('status', ['published', 'hidden'])->default('published')->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['group_id', 'status', 'created_at']);
        });

        Schema::create('group_comments', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('post_id');
            $table->string('author_id')->nullable();
            $table->string('parent_id')->nullable();
            $table->text('body');
            $table->enum('status', ['published', 'hidden'])->default('published')->index();
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('group_posts')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('group_comments')->nullOnDelete();
            $table->index(['post_id', 'parent_id', 'status', 'created_at']);
        });

        Schema::create('group_post_likes', function (Blueprint $table): void {
            $table->string('post_id');
            $table->string('user_id');
            $table->timestamps();
            $table->foreign('post_id')->references('id')->on('group_posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('group_comment_likes', function (Blueprint $table): void {
            $table->string('comment_id');
            $table->string('user_id');
            $table->timestamps();
            $table->foreign('comment_id')->references('id')->on('group_comments')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_comment_likes');
        Schema::dropIfExists('group_post_likes');
        Schema::dropIfExists('group_comments');
        Schema::dropIfExists('group_posts');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
