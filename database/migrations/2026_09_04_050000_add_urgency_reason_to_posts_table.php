<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('posts', 'urgency_reason')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->string('urgency_reason', 500)->nullable()->after('urgency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'urgency_reason')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropColumn('urgency_reason');
            });
        }
    }
};
