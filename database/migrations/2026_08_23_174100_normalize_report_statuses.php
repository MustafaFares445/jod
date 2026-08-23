<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        DB::table('reports')
            ->where('status', 'waiting_response')
            ->update(['status' => 'in_progress']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE reports MODIFY status ENUM('new','in_progress','closed') NOT NULL DEFAULT 'new'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE reports MODIFY status ENUM('new','in_progress','waiting_response','closed') NOT NULL DEFAULT 'new'");
        }
    }
};
