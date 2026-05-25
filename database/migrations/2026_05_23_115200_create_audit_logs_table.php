<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_user_id');
            $table->foreign('actor_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('metadata')->nullable();
            $table->timestamp('at');

            $table->index('actor_user_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
