<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SeedOperationalIdentifiersSeeder extends Seeder
{
    public function run(): void
    {
        $this->normalizeUserEmails();
        $this->normalizeStaffEmails();
        $this->normalizeOrganizationEmails();
    }

    private function normalizeUserEmails(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) LIKE ?', ['%demo%'])
            ->orderBy('id')
            ->chunk(100, function ($users): void {
                foreach ($users as $user) {
                    $attributes = [
                        'email' => $this->accountEmail((string) $user->id),
                    ];

                    if (Schema::hasColumn('users', 'updated_at')) {
                        $attributes['updated_at'] = now();
                    }

                    DB::table('users')->where('id', $user->id)->update($attributes);
                }
            });
    }

    private function normalizeStaffEmails(): void
    {
        if (! Schema::hasTable('organization_staff') || ! Schema::hasColumn('organization_staff', 'email')) {
            return;
        }

        DB::table('organization_staff')
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) LIKE ?', ['%demo%'])
            ->orderBy('user_id')
            ->chunk(100, function ($members): void {
                foreach ($members as $member) {
                    $userId = (string) $member->user_id;
                    $email = Schema::hasTable('users')
                        ? DB::table('users')->where('id', $userId)->value('email')
                        : null;

                    if (! is_string($email) || $email === '' || str_contains(strtolower($email), 'demo')) {
                        $email = $this->accountEmail($userId);
                    }

                    $attributes = ['email' => $email];
                    if (Schema::hasColumn('organization_staff', 'updated_at')) {
                        $attributes['updated_at'] = now();
                    }

                    DB::table('organization_staff')
                        ->where('organization_id', $member->organization_id)
                        ->where('user_id', $member->user_id)
                        ->update($attributes);
                }
            });
    }

    private function normalizeOrganizationEmails(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasColumn('organizations', 'email')) {
            return;
        }

        DB::table('organizations')
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) LIKE ?', ['%demo%'])
            ->orderBy('id')
            ->chunk(100, function ($organizations): void {
                foreach ($organizations as $organization) {
                    $attributes = [
                        'email' => 'organization-'.substr(hash('sha256', (string) $organization->id), 0, 16).'@jod.local',
                    ];

                    if (Schema::hasColumn('organizations', 'updated_at')) {
                        $attributes['updated_at'] = now();
                    }

                    DB::table('organizations')->where('id', $organization->id)->update($attributes);
                }
            });
    }

    private function accountEmail(string $userId): string
    {
        return 'account-'.substr(hash('sha256', $userId), 0, 16).'@jod.local';
    }
}
