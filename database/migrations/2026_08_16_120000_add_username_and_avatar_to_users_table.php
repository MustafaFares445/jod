<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 80)->nullable()->after('name');
            $table->string('avatar_disk')->nullable()->after('bio');
            $table->string('avatar_path')->nullable()->after('avatar_disk');
        });

        $used = [];

        DB::table('users')
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user) use (&$used): void {
                $base = filled($user->email)
                    ? Str::before((string) $user->email, '@')
                    : Str::slug((string) $user->name, '.');
                $base = Str::lower(preg_replace('/[^a-zA-Z0-9._-]+/', '', $base) ?: 'jod');
                $base = substr($base, 0, 64) ?: 'jod';
                $candidate = $base;
                $suffix = 1;

                while (isset($used[$candidate])) {
                    $candidate = substr($base, 0, 70).'-'.$suffix++;
                }

                $used[$candidate] = true;
                DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username', 'users_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_username_unique');
            $table->dropColumn(['username', 'avatar_disk', 'avatar_path']);
        });
    }
};
