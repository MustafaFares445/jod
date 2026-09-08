<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->uuid('selected_help_offer_id')->nullable()->after('help_status')->index();
            $table->foreign('selected_help_offer_id')->references('id')->on('help_offers')->nullOnDelete();
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('status', 30)->default('active')->change();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('suspension_reason')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn(['reviewed_at', 'suspension_reason']);
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['selected_help_offer_id']);
            $table->dropColumn('selected_help_offer_id');
        });
    }
};
