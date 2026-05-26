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
        if (!Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->enum('category', ['fraud', 'abuse', 'inappropriate', 'spam', 'other'])->default('other');
            $table->enum('status', ['new', 'in_progress', 'waiting_response', 'closed'])->default('new');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('entity_type', ['post', 'campaign', 'user', 'organization'])->default('post');
            $table->string('entity_id')->nullable();
            $table->string('organization_id')->nullable();
            $table->string('reporter_id')->nullable();
            $table->string('assignee_id')->nullable();
            $table->json('evidence')->nullable();
            $table->json('timeline')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assignee_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
