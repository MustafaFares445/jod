<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) return;

        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'rejection_reason')) $table->text('rejection_reason')->nullable();
            if (! Schema::hasColumn('organizations', 'rejected_at')) $table->timestamp('rejected_at')->nullable();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE organizations MODIFY status ENUM('active','inactive','pending','rejected') NOT NULL DEFAULT 'active'");
            DB::statement("ALTER TABLE organizations MODIFY verification_status ENUM('verified','unverified','pending','rejected') NOT NULL DEFAULT 'unverified'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations')) return;

        DB::table('organizations')->where('status', 'rejected')->update(['status' => 'inactive']);
        DB::table('organizations')->where('verification_status', 'rejected')->update(['verification_status' => 'unverified']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE organizations MODIFY status ENUM('active','inactive','pending') NOT NULL DEFAULT 'active'");
            DB::statement("ALTER TABLE organizations MODIFY verification_status ENUM('verified','unverified','pending') NOT NULL DEFAULT 'unverified'");
        }

        Schema::table('organizations', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('organizations', 'rejection_reason')) $columns[] = 'rejection_reason';
            if (Schema::hasColumn('organizations', 'rejected_at')) $columns[] = 'rejected_at';
            if ($columns !== []) $table->dropColumn($columns);
        });
    }
};
