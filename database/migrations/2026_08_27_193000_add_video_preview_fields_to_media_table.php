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
            $table->string('preview_disk', 32)->nullable()->after('path');
            $table->string('preview_path')->nullable()->after('preview_disk');
            $table->string('preview_mime_type', 128)->nullable()->after('preview_path');
            $table->unsignedBigInteger('preview_size')->nullable()->after('preview_mime_type');
            $table->string('preview_status', 32)->nullable()->after('preview_size');
            $table->text('preview_error')->nullable()->after('preview_status');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn([
                'preview_disk',
                'preview_path',
                'preview_mime_type',
                'preview_size',
                'preview_status',
                'preview_error',
            ]);
        });
    }
};
