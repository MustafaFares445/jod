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
        Schema::table('help_offers', function (Blueprint $table): void {
            $table->string('contact_value')->nullable()->after('contact_method');
            $table->timestamp('helper_agreed_at')->nullable()->after('agreed_at');
            $table->timestamp('receiver_agreed_at')->nullable()->after('helper_agreed_at');
        });

        DB::table('help_offers')
            ->whereNotNull('agreed_at')
            ->update([
                'helper_agreed_at' => DB::raw('agreed_at'),
                'receiver_agreed_at' => DB::raw('agreed_at'),
            ]);

        Schema::table('donations', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->after('cancel_reason');
            $table->decimal('confirmed_amount', 15, 2)->nullable()->after('amount_or_type');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropColumn(['accepted_at', 'confirmed_amount']);
        });

        Schema::table('help_offers', function (Blueprint $table): void {
            $table->dropColumn(['contact_value', 'helper_agreed_at', 'receiver_agreed_at']);
        });
    }
};
