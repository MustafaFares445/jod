<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('posts', 'author_name')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->string('author_name')->nullable()->after('author_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'author_name')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropColumn('author_name');
            });
        }
    }
};
