<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $categories = [
            'health' => [
                'name' => 'الصحة',
                'description' => 'الحملات والمبادرات المتعلقة بالصحة والعلاج والرعاية الطبية.',
            ],
            'education' => [
                'name' => 'التعليم',
                'description' => 'الحملات والمبادرات المتعلقة بالتعليم ودعم الطلبة والمؤسسات التعليمية.',
            ],
            'food' => [
                'name' => 'الغذاء',
                'description' => 'الحملات والمبادرات المتعلقة بالأمن الغذائي وتوفير الاحتياجات الغذائية.',
            ],
            'emergency' => [
                'name' => 'الطوارئ',
                'description' => 'حملات الإغاثة والاستجابة للحالات الطارئة والكوارث.',
            ],
            'shelter' => [
                'name' => 'الإيواء',
                'description' => 'الحملات والمبادرات المتعلقة بتوفير السكن والمأوى للمحتاجين.',
            ],
        ];

        foreach ($categories as $englishName => $arabicData) {
            $englishCategory = DB::table('categories')
                ->where('name', $englishName)
                ->first();

            if ($englishCategory === null) {
                continue;
            }

            $arabicCategory = DB::table('categories')
                ->where('name', $arabicData['name'])
                ->first();

            if ($arabicCategory !== null && $arabicCategory->id !== $englishCategory->id) {
                if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'category_id')) {
                    DB::table('campaigns')
                        ->where('category_id', $englishCategory->id)
                        ->update(['category_id' => $arabicCategory->id]);
                }

                if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'category_id')) {
                    DB::table('posts')
                        ->where('category_id', $englishCategory->id)
                        ->update(['category_id' => $arabicCategory->id]);
                }

                DB::table('categories')
                    ->where('id', $englishCategory->id)
                    ->delete();

                continue;
            }

            DB::table('categories')
                ->where('id', $englishCategory->id)
                ->update([
                    'name' => $arabicData['name'],
                    'description' => $arabicData['description'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $categories = [
            'الصحة' => [
                'name' => 'health',
                'description' => 'الصحة',
            ],
            'التعليم' => [
                'name' => 'education',
                'description' => 'التعليم',
            ],
            'الغذاء' => [
                'name' => 'food',
                'description' => 'الغذاء',
            ],
            'الطوارئ' => [
                'name' => 'emergency',
                'description' => 'الطوارئ',
            ],
            'الإيواء' => [
                'name' => 'shelter',
                'description' => 'الإيواء',
            ],
        ];

        foreach ($categories as $arabicName => $englishData) {
            DB::table('categories')
                ->where('name', $arabicName)
                ->update([
                    'name' => $englishData['name'],
                    'description' => $englishData['description'],
                    'updated_at' => now(),
                ]);
        }
    }
};
