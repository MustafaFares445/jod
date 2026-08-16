<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });

        Schema::table('campaign_applications', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });

        Schema::create('mobile_password_reset_codes', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_password_reset_codes');

        $this->restoreRequiredEmail('donations');
        $this->restoreRequiredEmail('campaign_applications');

        // A rollback cannot make user email non-null while preserving phone-first
        // accounts, so provide deterministic invalid placeholders only for the
        // rollback path before restoring the original schema constraint.
        DB::table('users')
            ->whereNull('email')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['email' => 'rollback-'.$user->id.'@invalid.local']);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
        });
    }

    private function restoreRequiredEmail(string $tableName): void
    {
        DB::table($tableName)
            ->whereNull('email')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $record) use ($tableName): void {
                DB::table($tableName)
                    ->where('id', $record->id)
                    ->update(['email' => 'rollback-'.$tableName.'-'.$record->id.'@invalid.local']);
            });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
        });
    }
};
