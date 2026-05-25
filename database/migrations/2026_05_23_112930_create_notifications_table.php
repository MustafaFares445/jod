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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('mailbox', ['inbox', 'sent'])->default('sent');
            $table->enum('status', ['unread', 'read', 'sent'])->default('sent');
            $table->enum('category', ['campaign', 'post', 'account', 'report', 'system', 'donation', 'applicant', 'staff'])->default('system');
            $table->enum('recipient_scope', ['all', 'users', 'organizations'])->default('all');
            $table->string('recipient_label')->nullable();
            $table->enum('priority', ['normal', 'high'])->default('normal');
            $table->string('reference_label')->nullable();
            $table->string('reference_path')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
