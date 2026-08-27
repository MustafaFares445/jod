<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts')) {
            DB::table('posts')
                ->whereNotNull('organization_id')
                ->whereIn('status', ['pending', 'blocked'])
                ->update([
                    'status' => 'draft',
                    'published_at' => null,
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                    'block_reason' => null,
                    'blocked_at' => null,
                    'blocked_by' => null,
                ]);

            DB::table('posts')
                ->whereNotNull('organization_id')
                ->where('status', 'published')
                ->whereNull('published_at')
                ->update(['published_at' => now()]);

            if (Schema::hasTable('users')) {
                $adminIds = DB::table('users')->where('user_type', 'admin')->pluck('id');
                if ($adminIds->isNotEmpty()) {
                    DB::table('posts')
                        ->whereNull('organization_id')
                        ->whereIn('author_id', $adminIds)
                        ->whereIn('status', ['pending', 'blocked'])
                        ->update([
                            'status' => 'draft',
                            'published_at' => null,
                            'reviewed_at' => null,
                            'reviewed_by' => null,
                            'block_reason' => null,
                            'blocked_at' => null,
                            'blocked_by' => null,
                        ]);
                }
            }
        }

        if (Schema::hasTable('campaigns')) {
            DB::table('campaigns')->whereIn('status', ['pending', 'approved'])->update(['status' => 'active']);
            DB::table('campaigns')->where('status', 'rejected')->update([
                'status' => 'draft',
                'rejection_reason' => null,
                'reviewed_by' => null,
            ]);

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE campaigns MODIFY status ENUM('draft','active','closed') NOT NULL DEFAULT 'active'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaigns') && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE campaigns MODIFY status ENUM('draft','pending','approved','rejected','active','closed') NOT NULL DEFAULT 'active'");
        }
    }
};
