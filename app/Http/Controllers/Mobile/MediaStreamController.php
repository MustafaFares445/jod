<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\MediaModel;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Organization;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController extends Controller
{
    public function __invoke(Request $request, string $video): JsonResponse|Response|StreamedResponse
    {
        $media = $this->publicVideo($video);

        if ($media === null) {
            return MobileApiResponse::error('not_found', 'The requested media could not be found.', null, 404);
        }

        return $this->streamAsset(
            $request,
            (string) $media->disk,
            (string) $media->path,
            $media->mime_type ?: 'application/octet-stream',
            false,
        );
    }

    public function preview(Request $request, string $video): JsonResponse|Response|StreamedResponse
    {
        $media = $this->publicVideo($video);

        if ($media === null) {
            return MobileApiResponse::error('not_found', 'The requested media could not be found.', null, 404);
        }

        if (
            $media->preview_status !== 'ready'
            || blank($media->preview_disk)
            || blank($media->preview_path)
        ) {
            return MobileApiResponse::error(
                'preview_not_ready',
                'The video preview is not available yet.',
                null,
                404,
            );
        }

        return $this->streamAsset(
            $request,
            (string) $media->preview_disk,
            (string) $media->preview_path,
            $media->preview_mime_type ?: 'video/mp4',
            true,
        );
    }

    private function publicVideo(string $video): ?Media
    {
        return Media::query()
            ->whereKey($video)
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('prop', 'videos')
            ->whereIn('model_id', Organization::query()->where('status', 'active')->select('id'))
            ->first();
    }

    private function streamAsset(
        Request $request,
        string $diskName,
        string $path,
        string $mimeType,
        bool $cacheable,
    ): JsonResponse|Response|StreamedResponse {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return MobileApiResponse::error('not_found', 'The requested video file could not be found.', null, 404);
        }

        $size = (int) $disk->size($path);
        $range = $this->resolveRange($request->header('Range'), $size);

        if (! $range['valid']) {
            return response('', Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE)
                ->withHeaders([
                    'Accept-Ranges' => 'bytes',
                    'Content-Range' => "bytes */{$size}",
                ]);
        }

        $start = $range['start'];
        $end = $range['end'];
        $length = $size === 0 ? 0 : ($end - $start + 1);
        $status = $range['requested'] ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK;
        $stream = $disk->readStream($path);

        if ($stream === false) {
            return MobileApiResponse::error('not_found', 'The requested video file could not be read.', null, 404);
        }

        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $length,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($cacheable) {
            $headers['Cache-Control'] = 'public, max-age=31536000, immutable';
        }

        if ($range['requested']) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($stream, $start, $length): void {
            try {
                $this->moveToOffset($stream, $start);

                $remaining = $length;

                while ($remaining > 0 && ! feof($stream)) {
                    $buffer = fread($stream, min(64 * 1024, $remaining));

                    if ($buffer === false || $buffer === '') {
                        break;
                    }

                    echo $buffer;
                    $remaining -= strlen($buffer);
                }
            } finally {
                fclose($stream);
            }
        }, $status, $headers);
    }

    /**
     * @return array{requested: bool, valid: bool, start: int, end: int}
     */
    private function resolveRange(?string $header, int $size): array
    {
        if ($header === null || trim($header) === '') {
            return [
                'requested' => false,
                'valid' => true,
                'start' => 0,
                'end' => max(0, $size - 1),
            ];
        }

        if ($size <= 0) {
            return $this->invalidRange();
        }

        $header = trim($header);

        if (! str_starts_with(strtolower($header), 'bytes=')) {
            return $this->invalidRange();
        }

        $specification = trim(substr($header, 6));

        if ($specification === '' || str_contains($specification, ',')) {
            return $this->invalidRange();
        }

        if (preg_match('/^(\\d*)-(\\d*)$/', $specification, $matches) !== 1) {
            return $this->invalidRange();
        }

        $startValue = $matches[1];
        $endValue = $matches[2];

        if ($startValue === '' && $endValue === '') {
            return $this->invalidRange();
        }

        if ($startValue === '') {
            if (! ctype_digit($endValue)) {
                return $this->invalidRange();
            }

            $suffixLength = (int) $endValue;

            if ($suffixLength <= 0) {
                return $this->invalidRange();
            }

            return [
                'requested' => true,
                'valid' => true,
                'start' => max(0, $size - $suffixLength),
                'end' => $size - 1,
            ];
        }

        if (! ctype_digit($startValue)) {
            return $this->invalidRange();
        }

        $start = (int) $startValue;

        if ($start >= $size) {
            return $this->invalidRange();
        }

        if ($endValue === '') {
            $end = $size - 1;
        } else {
            if (! ctype_digit($endValue)) {
                return $this->invalidRange();
            }

            $end = min((int) $endValue, $size - 1);

            if ($end < $start) {
                return $this->invalidRange();
            }
        }

        return [
            'requested' => true,
            'valid' => true,
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * @return array{requested: bool, valid: bool, start: int, end: int}
     */
    private function invalidRange(): array
    {
        return [
            'requested' => true,
            'valid' => false,
            'start' => 0,
            'end' => 0,
        ];
    }

    /**
     * @param resource $stream
     */
    private function moveToOffset(mixed $stream, int $offset): void
    {
        if ($offset <= 0) {
            return;
        }

        $metadata = stream_get_meta_data($stream);

        if (($metadata['seekable'] ?? false) && fseek($stream, $offset, SEEK_SET) === 0) {
            return;
        }

        if ($metadata['seekable'] ?? false) {
            rewind($stream);
        }

        $remaining = $offset;

        while ($remaining > 0 && ! feof($stream)) {
            $discarded = fread($stream, min(64 * 1024, $remaining));

            if ($discarded === false || $discarded === '') {
                break;
            }

            $remaining -= strlen($discarded);
        }
    }
}
