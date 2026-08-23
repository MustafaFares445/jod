<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('model_type', 32);
            $table->uuid('model_id');
            $table->uuid('post_id')->nullable()->index(); // compatibility alias for legacy mobile post media code
            $table->string('prop', 64);
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'prop'], 'media_target_prop_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
