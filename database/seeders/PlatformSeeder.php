<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * @var list<array{name: string}>
     */
    private array $platforms = [
        ['name' => 'فيسبوك'],
        ['name' => 'إنستغرام'],
        ['name' => 'واتساب'],
        ['name' => 'تيك توك'],
        ['name' => 'جوجل'],
        ['name' => 'إحالة'],
        ['name' => 'أخرى'],
    ];

    public function run(): void
    {
        foreach ($this->platforms as $platform) {
            Platform::firstOrCreate(
                ['name' => $platform['name']],
                ['is_active' => true]
            );
        }
    }
}
