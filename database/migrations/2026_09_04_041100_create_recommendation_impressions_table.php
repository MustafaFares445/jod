<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_impressions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id')->index();
            $table->string('subject_type', 30);
            $table->string('subject_id');
            $table->string('feed_type', 30)->index();
            $table->string('category_id')->nullable()->index();
            $table->string('publisher_type', 30)->nullable();
            $table->string('publisher_id')->nullable()->index();
            $table->string('city', 120)->nullable()->index();
            $table->decimal('score', 10, 2)->nullable();
            $table->json('reasons')->nullable();
            $table->timestamp('shown_at')->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(
                ['user_id', 'subject_type', 'subject_id', 'shown_at'],
                'rec_impression_user_subject_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_impressions');
    }
};
