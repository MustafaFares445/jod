<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;

class PlatformSettingService
{
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $setting = PlatformSetting::where('key', $key)->first();
        $value = $setting ? json_decode($setting->value, true) : $default;

        $this->cache[$key] = $value;
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        DB::transaction(static function () use ($key, $value) {
            PlatformSetting::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value)]
            );
        });

        $this->cache[$key] = $value;
    }

    public function all(): array
    {
        return PlatformSetting::all()
            ->mapWithKeys(fn($setting) => [
                $setting->key => json_decode($setting->value, true),
            ])
            ->all();
    }

    public function update(array $settings): void
    {
        DB::transaction(static function () use ($settings) {
            foreach ($settings as $key => $value) {
                PlatformSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => json_encode($value)]
                );
            }
        });

        $this->cache = array_merge($this->cache, $settings);
    }
}
