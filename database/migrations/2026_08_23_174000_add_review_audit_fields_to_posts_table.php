<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('updated_by')->nullable()->after('author_id');
            $table->timestamp('submitted_at')->nullable()->after('published_at');
            $table->timestamp('blocked_at')->nullable()->after('reviewed_at');
            $table->string('blocked_by')->nullable()->after('blocked_at');

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('blocked_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['blocked_by']);
            $table->dropColumn(['updated_by', 'submitted_at', 'blocked_at', 'blocked_by']);
        });
    }
};
