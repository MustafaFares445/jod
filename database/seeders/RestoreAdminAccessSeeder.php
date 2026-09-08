<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RestoreAdminAccessSeeder extends Seeder
{
    public const EMAIL = 'admin@jod-demo.com';

    private const PASSWORD_HASH = '$2y$12$/xd7GfGrYWJQfx/YN1IYZ.ROmUkJCkWzjQErSQsY5LIig9LrbKDS6';

    public function run(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $existingRequested = DB::table('users')->where('email', self::EMAIL)->first();
        $admin = $existingRequested
            ?? DB::table('users')->where('user_type', 'admin')->orderBy('id')->first();

        if ($admin === null) {
            throw new \RuntimeException('Unable to restore the administrator seed login because no administrator account exists.');
        }

        if ($existingRequested !== null && (string) ($existingRequested->user_type ?? '') !== 'admin') {
            throw new \RuntimeException('The requested administrator email is already assigned to a non-admin user.');
        }

        $attributes = [
            'email' => self::EMAIL,
            'password' => self::PASSWORD_HASH,
            'user_type' => 'admin',
            'status' => 'active',
        ];

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $attributes['email_verified_at'] = $admin->email_verified_at ?? now();
        }

        if (Schema::hasColumn('users', 'organization_id')) {
            $attributes['organization_id'] = null;
        }

        if (Schema::hasColumn('users', 'updated_at')) {
            $attributes['updated_at'] = now();
        }

        DB::table('users')->where('id', $admin->id)->update($attributes);
    }
}
