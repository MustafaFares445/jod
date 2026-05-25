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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->text('content')->nullable();
            $table->enum('category', ['health', 'education', 'shelter', 'food', 'emergency', 'employment'])->default('health');
            $table->enum('status', ['draft', 'pending', 'active', 'closed'])->default('draft');
            $table->string('location')->nullable();
            $table->string('organization_id');
            $table->string('creator_id')->nullable();
            $table->decimal('goal_amount', 15, 2)->default(0);
            $table->decimal('raised_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('beneficiaries_count')->default(0);
            $table->unsignedBigInteger('donors_count')->default(0);
            $table->unsignedBigInteger('applicants_count')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closed_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
