<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class PrimaryUserJourneySeeder extends Seeder
{
    public const EMAIL = 'khaled.alhassan@jod.com';

    public const PASSWORD = 'Jod@2026!';

    public const NAME = 'خالد الحسن';

    public const PHONE = '0944332211';

    public function run(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $userId = $this->seedUser();

        $this->call([
            PrimaryUserContentSeeder::class,
            PrimaryUserContributionSeeder::class,
            PrimaryUserCommunitySeeder::class,
        ]);

        DB::table('users')->where('id', $userId)->update([
            'last_active_at' => now()->subMinutes(12),
            'updated_at' => now(),
        ]);
    }

    public static function id(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-primary-user:'.$key), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }

    public static function userId(): ?string
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        $id = DB::table('users')->where('email', self::EMAIL)->value('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function seedUser(): string
    {
        $existingId = DB::table('users')->where('email', self::EMAIL)->value('id');
        $userId = is_string($existingId) && $existingId !== ''
            ? $existingId
            : self::id('user:khaled-alhassan');

        $attributes = [
            'id' => $userId,
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'phone' => self::PHONE,
            'city' => 'حلب',
            'governorate' => 'حلب',
            'location' => 'حلب - الحمدانية',
            'address' => 'الحمدانية، حلب',
            'bio' => 'متطوع مهتم بدعم الطلاب والأسر والمبادرات المجتمعية، ويشارك في حملات التبرع وفرص التطوع المحلية.',
            'user_type' => 'general',
            'organization_id' => null,
            'status' => 'active',
            'email_verified_at' => now()->subMonths(8),
            'last_active_at' => now()->subMinutes(12),
            'created_at' => now()->subMonths(10),
            'updated_at' => now(),
        ];

        $columns = array_flip(Schema::getColumnListing('users'));
        $attributes = array_intersect_key($attributes, $columns);

        DB::table('users')->updateOrInsert(['id' => $userId], $attributes);

        return $userId;
    }
}
