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
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_role_id')->nullable()->constrained('organization_roles')->nullOnDelete();
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
