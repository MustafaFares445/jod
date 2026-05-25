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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('user_type', ['general', 'volunteer', 'job_seeker', 'donor'])->default('general');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('posts_count')->default(0);
            $table->unsignedBigInteger('reports_count')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'status', 'user_type', 'organization_id', 'posts_count', 'reports_count', 'last_active_at']);
            $table->dropSoftDeletes();
        });
    }
};
