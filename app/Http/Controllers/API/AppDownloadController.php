<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AppDownloadController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = (string) config('app_download.path', 'releases/jod.apk');
        $disk = Storage::disk('local');

        abort_unless($path !== '' && $disk->exists($path), 404, 'Application package not found.');

        return response()->download(
            $disk->path($path),
            (string) config('app_download.filename', 'JOD.apk'),
            [
                'Content-Type' => (string) config(
                    'app_download.mime_type',
                    'application/vnd.android.package-archive',
                ),
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
