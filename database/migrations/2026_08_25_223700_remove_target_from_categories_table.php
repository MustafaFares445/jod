<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'target')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('target');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categories') && ! Schema::hasColumn('categories', 'target')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->enum('target', ['post', 'campaign'])->nullable();
            });
        }
    }
};
