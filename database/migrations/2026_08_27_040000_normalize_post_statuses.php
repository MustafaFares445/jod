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
        if (! Schema::hasTable('posts')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE posts MODIFY status ENUM('draft','pending','published','archived','approved','rejected','blocked') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'block_reason')) {
                $table->text('block_reason')->nullable();
            }
            if (! Schema::hasColumn('posts', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable();
            }
            if (! Schema::hasColumn('posts', 'blocked_by')) {
                $table->string('blocked_by')->nullable();
            }
        });

        if (Schema::hasColumn('posts', 'rejection_reason')) {
            DB::table('posts')->whereNotNull('rejection_reason')->update([
                'block_reason' => DB::raw('rejection_reason'),
            ]);
        }
        if (Schema::hasColumn('posts', 'rejected_at')) {
            DB::table('posts')->whereNotNull('rejected_at')->update([
                'blocked_at' => DB::raw('rejected_at'),
            ]);
        }
        if (Schema::hasColumn('posts', 'rejected_by')) {
            DB::table('posts')->whereNotNull('rejected_by')->update([
                'blocked_by' => DB::raw('rejected_by'),
            ]);
        }

        if (Schema::hasColumn('posts', 'approved_at')) {
            DB::table('posts')
                ->where('status', 'approved')
                ->whereNull('published_at')
                ->update(['published_at' => DB::raw('approved_at')]);
        }

        DB::table('posts')->where('status', 'approved')->update(['status' => 'published']);
        DB::table('posts')->where('status', 'rejected')->update(['status' => 'blocked', 'published_at' => null]);
        DB::table('posts')->where('status', 'archived')->update([
            'status' => 'draft',
            'published_at' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);

        DB::table('posts')
            ->whereNotNull('organization_id')
            ->whereIn('status', ['pending', 'blocked'])
            ->update([
                'status' => 'draft',
                'submitted_at' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'blocked_at' => null,
                'blocked_by' => null,
                'block_reason' => null,
            ]);

        if (Schema::hasTable('users')) {
            $adminIds = DB::table('users')->where('user_type', 'admin')->pluck('id');
            if ($adminIds->isNotEmpty()) {
                DB::table('posts')
                    ->whereNull('organization_id')
                    ->whereIn('author_id', $adminIds)
                    ->whereIn('status', ['pending', 'blocked'])
                    ->update([
                        'status' => 'draft',
                        'submitted_at' => null,
                        'reviewed_at' => null,
                        'reviewed_by' => null,
                        'blocked_at' => null,
                        'blocked_by' => null,
                        'block_reason' => null,
                    ]);
            }
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE posts MODIFY status ENUM('draft','pending','published','blocked') NOT NULL DEFAULT 'draft'");
        }

        $legacyForeignColumns = array_values(array_filter(
            ['approved_by', 'rejected_by'],
            static fn (string $column): bool => Schema::hasColumn('posts', $column),
        ));

        if ($legacyForeignColumns !== []) {
            Schema::table('posts', function (Blueprint $table) use ($legacyForeignColumns): void {
                foreach ($legacyForeignColumns as $column) {
                    $table->dropForeign([$column]);
                }
            });
        }

        $legacyColumns = array_values(array_filter(
            ['approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason'],
            static fn (string $column): bool => Schema::hasColumn('posts', $column),
        ));

        if ($legacyColumns !== []) {
            Schema::table('posts', fn (Blueprint $table) => $table->dropColumn($legacyColumns));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE posts MODIFY status ENUM('draft','pending','published','archived','approved','rejected','blocked') NOT NULL DEFAULT 'draft'");
        }
    }
};
