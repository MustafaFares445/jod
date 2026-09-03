<?php

declare(strict_types=1);

namespace App\Support\Mobile;

final class SyrianGovernorates
{
    /** @return list<array{id: string, name: string, nameEn: string, slug: string, sortOrder: int}> */
    public static function items(): array
    {
        return [
            ['id' => 'city_damascus', 'name' => 'دمشق', 'nameEn' => 'Damascus', 'slug' => 'damascus', 'sortOrder' => 1],
            ['id' => 'city_rif_dimashq', 'name' => 'ريف دمشق', 'nameEn' => 'Rif Dimashq', 'slug' => 'rif-dimashq', 'sortOrder' => 2],
            ['id' => 'city_aleppo', 'name' => 'حلب', 'nameEn' => 'Aleppo', 'slug' => 'aleppo', 'sortOrder' => 3],
            ['id' => 'city_homs', 'name' => 'حمص', 'nameEn' => 'Homs', 'slug' => 'homs', 'sortOrder' => 4],
            ['id' => 'city_hama', 'name' => 'حماة', 'nameEn' => 'Hama', 'slug' => 'hama', 'sortOrder' => 5],
            ['id' => 'city_lattakia', 'name' => 'اللاذقية', 'nameEn' => 'Latakia', 'slug' => 'latakia', 'sortOrder' => 6],
            ['id' => 'city_tartus', 'name' => 'طرطوس', 'nameEn' => 'Tartus', 'slug' => 'tartus', 'sortOrder' => 7],
            ['id' => 'city_idlib', 'name' => 'إدلب', 'nameEn' => 'Idlib', 'slug' => 'idlib', 'sortOrder' => 8],
            ['id' => 'city_daraa', 'name' => 'درعا', 'nameEn' => 'Daraa', 'slug' => 'daraa', 'sortOrder' => 9],
            ['id' => 'city_suwayda', 'name' => 'السويداء', 'nameEn' => 'As-Suwayda', 'slug' => 'suwayda', 'sortOrder' => 10],
            ['id' => 'city_quneitra', 'name' => 'القنيطرة', 'nameEn' => 'Quneitra', 'slug' => 'quneitra', 'sortOrder' => 11],
            ['id' => 'city_deir_ez_zor', 'name' => 'دير الزور', 'nameEn' => 'Deir ez-Zor', 'slug' => 'deir-ez-zor', 'sortOrder' => 12],
            ['id' => 'city_raqqa', 'name' => 'الرقة', 'nameEn' => 'Raqqa', 'slug' => 'raqqa', 'sortOrder' => 13],
            ['id' => 'city_hasakah', 'name' => 'الحسكة', 'nameEn' => 'Hasakah', 'slug' => 'hasakah', 'sortOrder' => 14],
        ];
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_values(array_column(self::items(), 'name'));
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_values(array_column(self::items(), 'id'));
    }

    public static function nameForId(?string $id): ?string
    {
        if (! filled($id)) return null;

        foreach (self::items() as $item) {
            if ($item['id'] === $id) return $item['name'];
        }

        return null;
    }

    public static function idForName(?string $name): ?string
    {
        if (! filled($name)) return null;

        foreach (self::items() as $item) {
            if ($item['name'] === $name) return $item['id'];
        }

        return null;
    }
}
