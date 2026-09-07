<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_likes', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('campaign_id');
            $table->timestamps();

            $table->unique(['user_id', 'campaign_id']);
            $table->index(['campaign_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
        });

        DB::table('post_likes')
            ->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->whereNotNull('posts.campaign_id')
            ->select(['post_likes.user_id', 'posts.campaign_id'])
            ->distinct()
            ->orderBy('posts.campaign_id')
            ->chunk(500, function ($rows): void {
                $now = now();
                $payload = [];
                foreach ($rows as $row) {
                    $payload[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => (string) $row->user_id,
                        'campaign_id' => (string) $row->campaign_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($payload !== []) DB::table('campaign_likes')->insertOrIgnore($payload);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_likes');
    }
};
