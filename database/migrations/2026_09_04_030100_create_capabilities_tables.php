<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capabilities', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_capabilities', function (Blueprint $table): void {
            $table->string('user_id');
            $table->string('capability_id');
            $table->timestamps();

            $table->primary(['user_id', 'capability_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('capability_id')->references('id')->on('capabilities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_capabilities');
        Schema::dropIfExists('capabilities');
    }
};
