<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_keywords', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('category_id')->index();
            $table->string('keyword', 150);
            $table->timestamps();

            $table->unique(['category_id', 'keyword']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_keywords');
    }
};
