<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->unsignedBigInteger('comments_count')->default(0)->after('reactions_count');
            $table->unsignedBigInteger('shares_count')->default(0)->after('comments_count');
        });

        Schema::create('post_comments', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('post_id');
            $table->string('user_id');
            $table->text('body');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['post_id', 'created_at']);
        });

        Schema::create('post_shares', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('post_id');
            $table->string('user_id');
            $table->string('channel', 32)->nullable();
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_shares');
        Schema::dropIfExists('post_comments');

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['comments_count', 'shares_count']);
        });
    }
};
