<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Existing active accounts predate registration verification. Keep them
        // usable after deployment; only newly registered accounts must verify.
        DB::table('users')
            ->where('status', 'active')
            ->whereNull('email_verified_at')
            ->update([
                'email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_verification_tokens');
    }
};
