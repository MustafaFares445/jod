<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table): void {
            $table->string('push_target_type', 16)->default('token')->after('push_token');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table): void {
            $table->dropColumn('push_target_type');
        });
    }
};
