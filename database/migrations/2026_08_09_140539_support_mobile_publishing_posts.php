<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'category_id')) {
                $table->string('category_id')->nullable()->after('campaign_id');
            }
        });

        if (Schema::hasColumn('posts', 'category_id')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE posts MODIFY title VARCHAR(255) NULL');
            DB::statement("ALTER TABLE posts MODIFY type VARCHAR(255) NOT NULL DEFAULT 'general'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            throw new RuntimeException('This migration is irreversible on MySQL because restoring the previous title/type constraints would reject valid mobile publishing data.');
        }

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
        });
    }
};
