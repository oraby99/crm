<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * @var list<array{name: string, slug: string, sort_order: int}>
     */
    private array $platforms = [
        ['name' => 'فيسبوك', 'slug' => 'facebook', 'sort_order' => 1],
        ['name' => 'إنستغرام', 'slug' => 'instagram', 'sort_order' => 2],
        ['name' => 'واتساب', 'slug' => 'whatsapp', 'sort_order' => 3],
        ['name' => 'تيك توك', 'slug' => 'tiktok', 'sort_order' => 4],
        ['name' => 'جوجل', 'slug' => 'google', 'sort_order' => 5],
        ['name' => 'إحالة', 'slug' => 'referral', 'sort_order' => 6],
        ['name' => 'أخرى', 'slug' => 'other', 'sort_order' => 7],
    ];

    public function run(): void
    {
        foreach ($this->platforms as $platform) {
            Platform::firstOrCreate(
                ['slug' => $platform['slug']],
                array_merge($platform, ['is_active' => true])
            );
        }
    }
}
