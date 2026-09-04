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
            $table->text('urgency_reason')->nullable()->after('urgency');
            $table->uuid('urgency_reviewed_by')->nullable()->index()->after('urgency_reason');
            $table->timestamp('urgency_reviewed_at')->nullable()->after('urgency_reviewed_by');
            $table->timestamp('fulfilled_at')->nullable()->index()->after('expires_at');
            $table->foreign('urgency_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['urgency_reviewed_by']);
            $table->dropColumn(['urgency_reason', 'urgency_reviewed_by', 'urgency_reviewed_at', 'fulfilled_at']);
        });
    }
};
