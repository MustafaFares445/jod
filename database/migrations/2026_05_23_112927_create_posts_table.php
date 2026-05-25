<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->text('content')->nullable();
            $table->enum('type', ['general', 'job_opportunity', 'campaign_teaser', 'campaign_update', 'campaign_summary', 'help_request', 'awareness'])->default('general');
            $table->enum('status', ['draft', 'pending', 'published', 'archived', 'approved', 'rejected'])->default('draft');
            $table->string('location')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('author_name')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('reactions_count')->default(0);
            $table->unsignedBigInteger('applications_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
