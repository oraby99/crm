<?php

namespace Database\Seeders;

use App\Models\CustomerStatus;
use Illuminate\Database\Seeder;

class CustomerStatusSeeder extends Seeder
{
    /**
     * @var list<array{name: string, color: string, is_final: bool}>
     */
    private array $statuses = [
        ['name' => 'تحت المتابعة', 'color' => 'warning', 'is_final' => false],
        ['name' => 'تمت الزيارة', 'color' => 'info', 'is_final' => false],
        ['name' => 'دُفع الإيداع', 'color' => 'primary', 'is_final' => false],
        ['name' => 'تم التعاقد', 'color' => 'success', 'is_final' => false],
        ['name' => 'مكتمل', 'color' => 'success', 'is_final' => true],
    ];

    public function run(): void
    {
        foreach ($this->statuses as $status) {
            CustomerStatus::firstOrCreate(
                ['name' => $status['name']],
                array_merge($status, ['is_active' => true])
            );
        }
    }
}
