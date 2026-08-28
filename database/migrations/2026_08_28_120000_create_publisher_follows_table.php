<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publisher_follows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('follower_user_id');
            $table->string('target_type', 32);
            $table->uuid('target_id');
            $table->string('notification_level', 32)->default('all');
            $table->timestamps();

            $table->foreign('follower_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['follower_user_id', 'target_type', 'target_id'], 'publisher_follows_unique');
            $table->index(['target_type', 'target_id'], 'publisher_follows_target_index');
            $table->index(['follower_user_id', 'created_at'], 'publisher_follows_follower_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publisher_follows');
    }
};
