<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campaign_applications')) return;

        Schema::table('campaign_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('campaign_applications', 'withdrawal_reason')) $table->text('withdrawal_reason')->nullable();
            if (! Schema::hasColumn('campaign_applications', 'rejection_reason')) $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('campaign_applications')) return;

        Schema::table('campaign_applications', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('campaign_applications', 'withdrawal_reason')) $columns[] = 'withdrawal_reason';
            if (Schema::hasColumn('campaign_applications', 'rejection_reason')) $columns[] = 'rejection_reason';
            if ($columns !== []) $table->dropColumn($columns);
        });
    }
};
