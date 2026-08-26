<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use Tests\TestCase;

class JodCompleteDemoDatasetTest extends TestCase
{
    public function test_complete_demo_dataset_has_expected_counts_and_rules(): void
    {
        $encoded = '';
        foreach (range(1, 4) as $part) {
            $encoded .= trim((string) file_get_contents(database_path(sprintf('data/jod_complete_demo_%02d.b64', $part))));
        }

        $compressed = base64_decode($encoded, true);
        $json = $compressed === false ? false : gzdecode($compressed);
        $this->assertNotFalse($json);

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $data['admins']);
        $this->assertCount(8, $data['organizations']);
        $this->assertCount(8, $data['organization_owners']);
        $this->assertCount(12, $data['organization_staff']);
        $this->assertCount(12, $data['users']);
        $this->assertCount(12, $data['categories']);
        $this->assertCount(12, $data['campaigns']);
        $this->assertCount(24, $data['posts']);
        $this->assertCount(14, $data['help_offers']);
        $this->assertCount(15, $data['donations']);
        $this->assertCount(12, $data['campaign_applications']);
        $this->assertCount(8, $data['articles']);
        $this->assertCount(72, $data['image_media']);
        $this->assertCount(18, $data['video_media']);
        $this->assertCount(35, $data['post_likes']);
        $this->assertCount(20, $data['saved_posts']);
        $this->assertCount(28, $data['notifications']);
        $this->assertCount(6, $data['reports']);
        $this->assertCount(5, $data['badges']);

        $this->assertNotContains('student', array_column($data['categories'], 'name'));
        $this->assertContains('student', array_column($data['campaigns'], 'audience'));
        $this->assertContains('student', array_column($data['posts'], 'audience'));

        $regularUserKeys = array_column($data['users'], 'key');
        foreach ($data['video_media'] as $media) {
            if ($media['entityType'] !== 'post') continue;
            $post = collect($data['posts'])->firstWhere('key', $media['entityKey']);
            $this->assertNotContains($post['authorKey'], $regularUserKeys, 'Regular user-created posts must not contain video media.');
            $this->assertLessThanOrEqual(15, $media['durationSeconds']);
        }
    }
}
