<?php

namespace Database\Seeders;

use App\Models\CustomerNeed;
use Illuminate\Database\Seeder;

class CustomerNeedSeeder extends Seeder
{
    /**
     * @var list<array{name: string}>
     */
    private array $needs = [
        ['name' => 'HDF'],
        ['name' => 'SPC'],
        ['name' => 'أبواب'],
        ['name' => 'HDF وأبواب'],
        ['name' => 'SPC وأبواب'],
        ['name' => 'كلادينج'],
    ];

    public function run(): void
    {
        foreach ($this->needs as $need) {
            CustomerNeed::firstOrCreate(
                ['name' => $need['name']],
                ['is_active' => true]
            );
        }
    }
}
