<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AvatarService
{
    public function replace(User $user, UploadedFile $file): User
    {
        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $filename = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
        $path = $file->storeAs("mobile/avatars/{$user->id}", $filename, 'public');

        if ($path === false) {
            throw new RuntimeException('Unable to store avatar image.');
        }

        $oldDisk = $user->avatar_disk;
        $oldPath = $user->avatar_path;

        try {
            $updated = DB::transaction(function () use ($user, $path): User {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $locked->update([
                    'avatar_disk' => 'public',
                    'avatar_path' => $path,
                ]);

                return $locked->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        if (filled($oldDisk) && filled($oldPath)) {
            Storage::disk((string) $oldDisk)->delete((string) $oldPath);
        }

        return $updated;
    }

    public function delete(User $user): User
    {
        $oldDisk = $user->avatar_disk;
        $oldPath = $user->avatar_path;

        $updated = DB::transaction(function () use ($user): User {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'avatar_disk' => null,
                'avatar_path' => null,
            ]);

            return $locked->refresh();
        });

        if (filled($oldDisk) && filled($oldPath)) {
            Storage::disk((string) $oldDisk)->delete((string) $oldPath);
        }

        return $updated;
    }
}
