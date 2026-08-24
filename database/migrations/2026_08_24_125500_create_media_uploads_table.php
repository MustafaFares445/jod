<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('uploaded_by')->nullable()->index();
            $table->uuid('replace_media_id')->nullable()->index();
            $table->uuid('media_id')->nullable()->index();
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('total_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->json('received_chunks')->nullable();
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->string('status', 32)->default('initiated');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'media_uploads_org_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
    }
};
