<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id')->unique();
            $table->string('intent', 20)->nullable()->index();
            $table->string('preferred_city')->nullable()->index();
            $table->string('preferred_governorate')->nullable()->index();
            $table->unsignedSmallInteger('preferred_radius_km')->nullable();
            $table->boolean('remote_help_enabled')->default(false);
            $table->string('availability_status', 30)->nullable()->index();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
