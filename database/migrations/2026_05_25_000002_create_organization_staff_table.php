<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_staff', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('organization_role_id')->nullable();
            $table->foreign('organization_role_id')->references('id')->on('organization_roles')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->enum('status', ['active', 'inactive', 'invited'])->default('invited');
            $table->timestamp('invited_at')->nullable()->default(now());
            $table->timestamp('accepted_at')->nullable();
            $table->string('invitation_token')->nullable()->unique();
            $table->timestamps();
            $table->unique(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_staff');
    }
};
