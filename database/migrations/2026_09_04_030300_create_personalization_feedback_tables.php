<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_feedback', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('post_id');
            $table->string('type', 30);
            $table->timestamps();

            $table->unique(['user_id', 'post_id', 'type']);
            $table->index(['user_id', 'type']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        Schema::create('hidden_publishers', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('publisher_type', 30);
            $table->string('publisher_id');
            $table->timestamps();

            $table->unique(['user_id', 'publisher_type', 'publisher_id'], 'hidden_publishers_unique');
            $table->index(['user_id', 'publisher_type']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_publishers');
        Schema::dropIfExists('post_feedback');
    }
};
