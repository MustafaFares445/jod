<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->text('body');
            $table->enum('mailbox', ['inbox', 'sent'])->default('sent');
            $table->enum('status', ['unread', 'read', 'sent'])->default('sent');
            $table->enum('category', ['campaign', 'post', 'account', 'report', 'system', 'donation', 'applicant', 'staff', 'badge'])->default('system');
            $table->enum('recipient_scope', ['all', 'users', 'organizations'])->default('all');
            $table->string('recipient_label')->nullable();
            $table->enum('priority', ['normal', 'high'])->default('normal');
            $table->string('reference_label')->nullable();
            $table->string('reference_path')->nullable();
            $table->string('organization_id')->nullable();
            $table->string('creator_id')->nullable();
            $table->string('recipient_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
