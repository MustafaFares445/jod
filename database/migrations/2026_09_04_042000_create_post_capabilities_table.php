<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_capabilities', function (Blueprint $table): void {
            $table->string('post_id');
            $table->string('capability_id');
            $table->timestamps();
            $table->primary(['post_id', 'capability_id']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('capability_id')->references('id')->on('capabilities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_capabilities');
    }
};
