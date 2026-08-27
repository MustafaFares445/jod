<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'summary')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->text('summary')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'summary')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->string('summary')->nullable()->change();
            });
        }
    }
};
