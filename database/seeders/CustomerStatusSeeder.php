<?php

namespace Database\Seeders;

use App\Models\CustomerStatus;
use Illuminate\Database\Seeder;

class CustomerStatusSeeder extends Seeder
{
    /**
     * @var list<array{name: string, slug: string, color: string, sort_order: int, is_final: bool}>
     */
    private array $statuses = [
        ['name' => 'تحت المتابعة', 'slug' => 'under-follow-up', 'color' => 'warning', 'sort_order' => 1, 'is_final' => false],
        ['name' => 'تمت الزيارة', 'slug' => 'site-visit-done', 'color' => 'info', 'sort_order' => 2, 'is_final' => false],
        ['name' => 'دُفع الإيداع', 'slug' => 'deposit-paid', 'color' => 'primary', 'sort_order' => 3, 'is_final' => false],
        ['name' => 'تم التعاقد', 'slug' => 'contract-signed', 'color' => 'success', 'sort_order' => 4, 'is_final' => false],
        ['name' => 'مكتمل', 'slug' => 'completed', 'color' => 'success', 'sort_order' => 5, 'is_final' => true],
    ];

    public function run(): void
    {
        foreach ($this->statuses as $status) {
            CustomerStatus::firstOrCreate(
                ['slug' => $status['slug']],
                array_merge($status, ['is_active' => true])
            );
        }
    }
}
