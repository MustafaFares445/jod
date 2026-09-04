<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            DB::table('platform_settings')->where('key', 'recommendation_overrides')->delete();
        }

        if (! Schema::hasTable('permissions')) return;

        $permissionIds = DB::table('permissions')
            ->where('name', 'recommendations.configure')
            ->pluck('id');

        if ($permissionIds->isEmpty()) return;

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    public function down(): void
    {
        // Recommendation weights are intentionally source-controlled. Do not restore runtime overrides.
    }
};
