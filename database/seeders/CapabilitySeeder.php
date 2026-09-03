<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Capability;
use Illuminate\Database\Seeder;

class CapabilitySeeder extends Seeder
{
    public function run(): void
    {
        $capabilities = [
            ['slug' => 'financial_donation', 'name' => 'تبرع مالي'],
            ['slug' => 'item_donation', 'name' => 'تبرع عيني'],
            ['slug' => 'transport', 'name' => 'نقل'],
            ['slug' => 'field_volunteering', 'name' => 'تطوع ميداني'],
            ['slug' => 'teaching', 'name' => 'تدريس'],
            ['slug' => 'medical_support', 'name' => 'دعم طبي'],
            ['slug' => 'technical_support', 'name' => 'دعم تقني'],
            ['slug' => 'legal_consulting', 'name' => 'استشارة قانونية'],
            ['slug' => 'translation', 'name' => 'ترجمة'],
            ['slug' => 'remote_assistance', 'name' => 'مساعدة عن بعد'],
        ];

        foreach ($capabilities as $index => $capability) {
            Capability::query()->updateOrCreate(
                ['slug' => $capability['slug']],
                [
                    'name' => $capability['name'],
                    'status' => 'active',
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
