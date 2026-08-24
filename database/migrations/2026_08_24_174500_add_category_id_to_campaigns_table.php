<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaigns', 'category_id')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->string('category_id')->nullable()->after('category')->index();
            });
        }

        if (Schema::hasTable('categories')) {
            $categories = DB::table('categories')
                ->where('target', 'campaign')
                ->whereNull('deleted_at')
                ->pluck('id', 'name');

            foreach ($categories as $name => $categoryId) {
                DB::table('campaigns')
                    ->where('category', $name)
                    ->whereNull('category_id')
                    ->update(['category_id' => $categoryId]);
            }
        }

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaigns', 'category_id')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }
    }
};
