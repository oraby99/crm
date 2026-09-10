<?php

namespace Database\Seeders;

use App\Models\CustomerNeed;
use Illuminate\Database\Seeder;

class CustomerNeedSeeder extends Seeder
{
    /**
     * @var list<array{name: string, slug: string, sort_order: int}>
     */
    private array $needs = [
        ['name' => 'HDF', 'slug' => 'hdf', 'sort_order' => 1],
        ['name' => 'SPC', 'slug' => 'spc', 'sort_order' => 2],
        ['name' => 'أبواب', 'slug' => 'doors', 'sort_order' => 3],
        ['name' => 'HDF وأبواب', 'slug' => 'hdf-doors', 'sort_order' => 4],
        ['name' => 'SPC وأبواب', 'slug' => 'spc-doors', 'sort_order' => 5],
        ['name' => 'كلادينج', 'slug' => 'cladding', 'sort_order' => 6],
    ];

    public function run(): void
    {
        foreach ($this->needs as $need) {
            CustomerNeed::firstOrCreate(
                ['slug' => $need['slug']],
                array_merge($need, ['is_active' => true])
            );
        }
    }
}
