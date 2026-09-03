<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_category_interests', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('category_id');
            $table->decimal('explicit_weight', 8, 2)->default(0);
            $table->decimal('behavioral_weight', 8, 2)->default(0);
            $table->timestamp('last_interaction_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'category_id']);
            $table->index(['user_id', 'behavioral_weight']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_category_interests');
    }
};
