<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_verification_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('created_at');
            $table->timestamp('last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_verification_tokens');
    }
};
