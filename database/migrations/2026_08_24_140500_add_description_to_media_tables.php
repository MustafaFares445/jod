<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('original_name');
        });

        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
