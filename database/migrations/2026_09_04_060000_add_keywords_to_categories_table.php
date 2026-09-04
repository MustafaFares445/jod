<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'keywords')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->json('keywords')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'keywords')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->dropColumn('keywords');
            });
        }
    }
};
