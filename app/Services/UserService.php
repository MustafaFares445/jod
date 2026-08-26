<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\UserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function store(UserData $data): User
    {
        return DB::transaction(static function () use ($data) {
            $attributes = $data->onlyModelAttributes();
            if (isset($attributes['password'])) {
                $attributes['password'] = Hash::make($attributes['password']);
            }

            return User::create($attributes);
        });
    }

    /**
     * @param  list<string>  $preserveNullAttributes
     */
    public function update(UserData $data, User $user, array $preserveNullAttributes = []): User
    {
        return DB::transaction(static function () use ($data, $user, $preserveNullAttributes) {
            $attributes = $data->onlyModelAttributes($preserveNullAttributes);
            if (isset($attributes['password']) && ! empty($attributes['password'])) {
                $attributes['password'] = Hash::make($attributes['password']);
            } else {
                unset($attributes['password']);
            }

            tap($user)->update($attributes);

            return $user;
        });
    }

    public function updateStatus(User $user, string $status): User
    {
        return DB::transaction(static function () use ($user, $status) {
            tap($user)->update(['status' => $status]);

            if ($status !== 'active') {
                $user->tokens()->delete();
            }

            return $user;
        });
    }

    public function updatePassword(User $user, string $newPassword): User
    {
        return DB::transaction(static function () use ($user, $newPassword) {
            tap($user)->update(['password' => Hash::make($newPassword)]);

            return $user;
        });
    }
}
