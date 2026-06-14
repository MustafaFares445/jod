<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name')->unique();
                $table->enum('target', ['post', 'campaign']);
                $table->text('description');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
