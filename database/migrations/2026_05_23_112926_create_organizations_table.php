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
        Schema::create('organizations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('organization_type')->nullable();
            $table->string('registration_number')->nullable();
            $table->date('establishment_date')->nullable();
            $table->string('short_address')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('license_document_name')->nullable();
            $table->string('delegation_document_name')->nullable();
            $table->string('owner_full_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('owner_phone')->nullable();
            $table->string('website')->nullable();
            $table->json('social_media')->nullable();
            $table->enum('status', ['active', 'inactive', 'pending'])->default('active');
            $table->enum('verification_status', ['verified', 'unverified', 'pending'])->default('unverified');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('campaigns_count')->default(0);
            $table->unsignedBigInteger('posts_count')->default(0);
            $table->unsignedBigInteger('active_volunteers_count')->default(0);
            $table->decimal('activity_score', 5, 2)->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
