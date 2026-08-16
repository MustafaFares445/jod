<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('source_notification_id')->nullable()->after('recipient_id');
            $table->uuid('distribution_batch_id')->nullable()->after('source_notification_id');

            $table->foreign('source_notification_id')
                ->references('id')
                ->on('notifications')
                ->nullOnDelete();
            $table->unique(
                ['distribution_batch_id', 'recipient_id'],
                'notifications_distribution_recipient_unique',
            );
            $table->index('source_notification_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique('notifications_distribution_recipient_unique');
            $table->dropIndex(['source_notification_id']);
            $table->dropForeign(['source_notification_id']);
            $table->dropColumn(['distribution_batch_id', 'source_notification_id']);
        });
    }
};
