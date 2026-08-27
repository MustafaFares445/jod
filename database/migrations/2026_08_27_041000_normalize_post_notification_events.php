<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'event_type')) {
            return;
        }

        DB::table('notifications')->where('event_type', 'post.approved')->update(['event_type' => 'post.published']);
        DB::table('notifications')->where('event_type', 'post.rejected')->update(['event_type' => 'post.blocked']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'event_type')) {
            return;
        }

        DB::table('notifications')->where('event_type', 'post.published')->update(['event_type' => 'post.approved']);
        DB::table('notifications')->where('event_type', 'post.blocked')->update(['event_type' => 'post.rejected']);
    }
};
