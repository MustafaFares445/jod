<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $categories = [
        'campaign', 'post', 'account', 'report', 'system', 'donation',
        'help', 'applicant', 'staff', 'badge', 'group',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'category')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(sprintf(
            "ALTER TABLE `notifications` MODIFY `category` ENUM(%s) NOT NULL DEFAULT 'system'",
            $this->enumValues($this->categories),
        ));
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'category')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('notifications')->where('category', 'group')->update(['category' => 'system']);

        $categories = array_values(array_filter(
            $this->categories,
            static fn (string $category): bool => $category !== 'group',
        ));

        DB::statement(sprintf(
            "ALTER TABLE `notifications` MODIFY `category` ENUM(%s) NOT NULL DEFAULT 'system'",
            $this->enumValues($categories),
        ));
    }

    /** @param list<string> $values */
    private function enumValues(array $values): string
    {
        return collect($values)
            ->map(static fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');
    }
};
