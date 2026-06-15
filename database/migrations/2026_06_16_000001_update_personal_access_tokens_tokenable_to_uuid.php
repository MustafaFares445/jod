<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropMorphs('tokenable');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->uuidMorphs('tokenable');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropMorphs('tokenable');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->morphs('tokenable');
        });
    }
};
