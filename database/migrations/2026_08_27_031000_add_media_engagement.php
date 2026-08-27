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
        if (Schema::hasTable('media')) {
            $needsReactionsCount = ! Schema::hasColumn('media', 'reactions_count');
            $needsSavesCount = ! Schema::hasColumn('media', 'saves_count');

            if ($needsReactionsCount || $needsSavesCount) {
                Schema::table('media', function (Blueprint $table) use ($needsReactionsCount, $needsSavesCount): void {
                    if ($needsReactionsCount) {
                        $table->unsignedBigInteger('reactions_count')->default(0);
                    }
                    if ($needsSavesCount) {
                        $table->unsignedBigInteger('saves_count')->default(0);
                    }
                });
            }
        }

        if (! Schema::hasTable('media_likes')) {
            Schema::create('media_likes', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('user_id');
                $table->string('media_id');
                $table->timestamps();
                $table->unique(['user_id', 'media_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('saved_media')) {
            Schema::create('saved_media', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('user_id');
                $table->string('media_id');
                $table->timestamps();
                $table->unique(['user_id', 'media_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'entity_type')) {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE reports MODIFY entity_type ENUM('post','campaign','user','organization','media') NOT NULL DEFAULT 'post'");
            } else {
                Schema::table('reports', function (Blueprint $table): void {
                    $table->string('entity_type')->default('post')->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'entity_type')) {
            DB::table('reports')->where('entity_type', 'media')->delete();
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE reports MODIFY entity_type ENUM('post','campaign','user','organization') NOT NULL DEFAULT 'post'");
            }
        }

        Schema::dropIfExists('saved_media');
        Schema::dropIfExists('media_likes');

        if (Schema::hasTable('media')) {
            $dropReactionsCount = Schema::hasColumn('media', 'reactions_count');
            $dropSavesCount = Schema::hasColumn('media', 'saves_count');

            if ($dropReactionsCount || $dropSavesCount) {
                Schema::table('media', function (Blueprint $table) use ($dropReactionsCount, $dropSavesCount): void {
                    if ($dropReactionsCount) {
                        $table->dropColumn('reactions_count');
                    }
                    if ($dropSavesCount) {
                        $table->dropColumn('saves_count');
                    }
                });
            }
        }
    }
};
