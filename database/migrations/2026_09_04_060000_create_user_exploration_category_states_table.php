<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_exploration_category_states', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('category_id');
            $table->timestamp('last_prompted_at')->nullable()->index();
            $table->string('last_response', 30)->nullable()->index();
            $table->timestamp('last_responded_at')->nullable()->index();
            $table->unsignedInteger('prompt_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'category_id'], 'exploration_user_category_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_exploration_category_states');
    }
};
