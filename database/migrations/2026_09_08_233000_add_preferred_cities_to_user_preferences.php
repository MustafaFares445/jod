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
        if (! Schema::hasTable('user_preferences')) return;

        $hasLegacyCity = Schema::hasColumn('user_preferences', 'preferred_city');

        if (! Schema::hasColumn('user_preferences', 'preferred_cities')) {
            Schema::table('user_preferences', function (Blueprint $table) use ($hasLegacyCity): void {
                $column = $table->json('preferred_cities')->nullable();
                if ($hasLegacyCity) $column->after('preferred_city');
            });
        }

        if (! $hasLegacyCity) return;

        DB::table('user_preferences')
            ->whereNotNull('preferred_city')
            ->where('preferred_city', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $existing = is_string($row->preferred_cities ?? null)
                        ? json_decode($row->preferred_cities, true)
                        : ($row->preferred_cities ?? null);

                    if (! is_array($existing) || $existing === []) {
                        DB::table('user_preferences')->where('id', $row->id)->update([
                            'preferred_cities' => json_encode([(string) $row->preferred_city], JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                }
            }, 'id');

        Schema::table('user_preferences', fn (Blueprint $table) => $table->dropColumn('preferred_city'));
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_preferences')) return;

        if (! Schema::hasColumn('user_preferences', 'preferred_city')) {
            Schema::table('user_preferences', function (Blueprint $table): void {
                $table->string('preferred_city')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('user_preferences', 'preferred_cities')) return;

        DB::table('user_preferences')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $cities = is_string($row->preferred_cities ?? null)
                    ? json_decode($row->preferred_cities, true)
                    : ($row->preferred_cities ?? null);

                DB::table('user_preferences')->where('id', $row->id)->update([
                    'preferred_city' => is_array($cities) ? ($cities[0] ?? null) : null,
                ]);
            }
        }, 'id');

        Schema::table('user_preferences', fn (Blueprint $table) => $table->dropColumn('preferred_cities'));
    }
};
