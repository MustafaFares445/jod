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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'posts_count')) {
                    $table->unsignedBigInteger('posts_count')->default(0);
                }
                if (!Schema::hasColumn('users', 'reports_count')) {
                    $table->unsignedBigInteger('reports_count')->default(0);
                }
                if (!Schema::hasColumn('users', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'posts_count')) {
                    $table->dropColumn('posts_count');
                }
                if (Schema::hasColumn('users', 'reports_count')) {
                    $table->dropColumn('reports_count');
                }
                if (Schema::hasColumn('users', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
