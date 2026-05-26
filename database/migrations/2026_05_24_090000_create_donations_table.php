<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('donations')) {
               Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id');
            $table->string('campaign_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('campaign_title');
            $table->string('amount_or_type');
            $table->timestamp('donated_at');
            $table->string('city')->nullable();
            $table->string('source')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('campaign_ref')->nullable();
            $table->string('assigned_to')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('donated_at');
            $table->index('name');
        });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
