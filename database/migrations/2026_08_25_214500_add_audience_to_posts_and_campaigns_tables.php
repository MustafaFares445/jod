<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('audience')->default('general')->index();
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('audience')->default('general')->index();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });
    }
};
