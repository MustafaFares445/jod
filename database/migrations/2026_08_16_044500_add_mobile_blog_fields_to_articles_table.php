<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('category', 50)->default('awareness')->after('content');
            $table->string('cover_image')->nullable()->after('category');
            $table->index(['status', 'category', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex(['status', 'category', 'published_at']);
            $table->dropColumn(['category', 'cover_image']);
        });
    }
};
