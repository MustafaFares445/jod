<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaign_applications')) {
            Schema::create('campaign_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('campaign_title');
            $table->string('applicant_status');
            $table->timestamp('applied_at');
            $table->string('city')->nullable();
            $table->string('source')->nullable();
            $table->string('campaign_ref')->nullable();
            $table->string('assigned_to')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('request_type')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('applied_at');
            $table->index('name');
            $table->index('applicant_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_applications');
    }
};
