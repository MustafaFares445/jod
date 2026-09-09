<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AppDownloadEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'app_download.path' => 'releases/jod.apk',
            'app_download.filename' => 'JOD.apk',
            'app_download.mime_type' => 'application/vnd.android.package-archive',
        ]);
    }

    public function test_it_downloads_the_configured_application_package(): void
    {
        Storage::disk('local')->put('releases/jod.apk', 'fake-apk-content');

        $this->get('/api/v1/app/download')
            ->assertOk()
            ->assertDownload('JOD.apk')
            ->assertHeader('content-type', 'application/vnd.android.package-archive');
    }

    public function test_it_returns_not_found_when_application_package_is_missing(): void
    {
        $this->getJson('/api/v1/app/download')
            ->assertNotFound();
    }
}
