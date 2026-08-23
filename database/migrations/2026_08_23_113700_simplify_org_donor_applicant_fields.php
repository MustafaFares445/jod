<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->string('campaign_title')->nullable()->change();
            $table->string('amount_or_type')->nullable()->change();
            $table->timestamp('donated_at')->nullable()->change();
        });

        Schema::table('campaign_applications', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('donations')->whereNull('campaign_title')->update(['campaign_title' => '']);
        DB::table('donations')->whereNull('amount_or_type')->update(['amount_or_type' => '']);
        DB::table('donations')->whereNull('donated_at')->update(['donated_at' => now()]);
        DB::table('campaign_applications')->whereNull('email')->update(['email' => '']);

        Schema::table('donations', function (Blueprint $table): void {
            $table->string('campaign_title')->nullable(false)->change();
            $table->string('amount_or_type')->nullable(false)->change();
            $table->timestamp('donated_at')->nullable(false)->change();
        });

        Schema::table('campaign_applications', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
        });
    }
};
