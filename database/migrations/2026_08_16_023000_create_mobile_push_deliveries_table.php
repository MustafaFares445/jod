<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_push_deliveries', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('notification_id');
            $table->string('mobile_device_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id', 512)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('notification_id')->references('id')->on('notifications')->cascadeOnDelete();
            $table->foreign('mobile_device_id')->references('id')->on('mobile_devices')->nullOnDelete();
            $table->unique(['notification_id', 'mobile_device_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_push_deliveries');
    }
};
