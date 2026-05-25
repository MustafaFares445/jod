<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        Article::create([
            'id' => 'article-001',
            'title' => 'How to Start a Successful Campaign',
            'slug' => 'how-to-start-successful-campaign',
            'excerpt' => 'Tips and best practices for launching your fundraising campaign',
            'content' => 'Detailed guide on starting campaigns...',
            'author_id' => 'user-123',
            'status' => 'published',
            'published_at' => now()->subDays(5),
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(5),
        ]);

        Article::create([
            'id' => 'article-002',
            'title' => 'Volunteer Safety Guidelines',
            'slug' => 'volunteer-safety-guidelines',
            'excerpt' => 'Important safety protocols every volunteer should follow',
            'content' => 'Comprehensive safety guidelines...',
            'author_id' => 'user-123',
            'status' => 'published',
            'published_at' => now()->subDays(10),
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(10),
        ]);

        Article::create([
            'id' => 'article-003',
            'title' => 'Maximizing Donation Impact',
            'slug' => 'maximizing-donation-impact',
            'excerpt' => 'How to ensure your donations make the maximum impact',
            'content' => 'Guide on effective giving...',
            'author_id' => 'user-456',
            'status' => 'published',
            'published_at' => now()->subDays(15),
            'created_at' => now()->subDays(17),
            'updated_at' => now()->subDays(15),
        ]);

        Article::create([
            'id' => 'article-004',
            'title' => 'Building Community Trust',
            'slug' => 'building-community-trust',
            'excerpt' => 'Strategies for NGOs to build trust with their communities',
            'content' => 'Community engagement strategies...',
            'author_id' => 'user-123',
            'status' => 'draft',
            'published_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(1),
        ]);

        Article::create([
            'id' => 'article-005',
            'title' => 'Digital Transformation for NGOs',
            'slug' => 'digital-transformation-ngos',
            'excerpt' => 'Embracing technology to improve NGO operations',
            'content' => 'Digital transformation guide...',
            'author_id' => 'user-456',
            'status' => 'published',
            'published_at' => now()->subMonths(1),
            'created_at' => now()->subMonths(1)->subDays(2),
            'updated_at' => now()->subMonths(1),
        ]);
    }
}
