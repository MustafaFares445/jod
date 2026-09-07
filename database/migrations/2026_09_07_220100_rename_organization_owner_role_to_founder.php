<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_roles')) {
            return;
        }

        DB::table('organization_roles')
            ->where('is_system', true)
            ->where('name', 'المالك')
            ->update(['name' => 'المؤسس']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('organization_roles')) {
            return;
        }

        DB::table('organization_roles')
            ->where('is_system', true)
            ->where('name', 'المؤسس')
            ->update(['name' => 'المالك']);
    }
};
