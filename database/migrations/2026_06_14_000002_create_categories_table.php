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
                $table->text('description');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'category_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'category_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        Schema::dropIfExists('categories');
    }
};
