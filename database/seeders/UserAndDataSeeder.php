<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomerNeed;
use App\Models\CustomerStatus;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserAndDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@crm.test'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );

        // ── Team Leaders ─────────────────────────────────────────
        $leader1 = User::firstOrCreate(
            ['email' => 'leader1@crm.test'],
            [
                'name' => 'محمد مدير الفريق',
                'password' => Hash::make('password'),
                'role' => UserRole::TeamLeader,
                'is_active' => true,
            ]
        );

        $leader2 = User::firstOrCreate(
            ['email' => 'leader2@crm.test'],
            [
                'name' => 'أحمد مدير الفريق',
                'password' => Hash::make('password'),
                'role' => UserRole::TeamLeader,
                'is_active' => true,
            ]
        );

        // ── Sales Employees ──────────────────────────────────────
        $salesUnderLeader1 = [];
        foreach (['علي مندوب', 'سارة مندوبة', 'خالد مندوب'] as $index => $name) {
            $salesUnderLeader1[] = User::firstOrCreate(
                ['email' => 'sales'.($index + 1).'@crm.test'],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => UserRole::Sales,
                    'team_leader_id' => $leader1->id,
                    'is_active' => true,
                ]
            );
        }

        $salesUnderLeader2 = [];
        foreach (['فاطمة مندوبة', 'عمر مندوب'] as $index => $name) {
            $salesUnderLeader2[] = User::firstOrCreate(
                ['email' => 'sales'.($index + 4).'@crm.test'],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => UserRole::Sales,
                    'team_leader_id' => $leader2->id,
                    'is_active' => true,
                ]
            );
        }

        // ── Sample Customers ─────────────────────────────────────
        $allSales = array_merge($salesUnderLeader1, $salesUnderLeader2);
        $platforms = Platform::all();
        $needs = CustomerNeed::all();
        $statuses = CustomerStatus::all();

        if ($platforms->isEmpty() || $needs->isEmpty() || $statuses->isEmpty()) {
            $this->command->warn('Skipping customer seeding — run PlatformSeeder, CustomerNeedSeeder, CustomerStatusSeeder first.');

            return;
        }

        /** @var User $salesUser */
        foreach ($allSales as $salesUser) {
            for ($i = 0; $i < 4; $i++) {
                $createdDate = now()->subDays(rand(1, 10))->subHours(rand(1, 12));

                $customer = Customer::factory()->forSales($salesUser)->create([
                    'phone' => '010'.str_pad((string) ($salesUser->id * 100 + $i), 8, '0', STR_PAD_LEFT),
                    'created_at' => $createdDate,
                    'updated_at' => $createdDate,
                ]);

                // Add 1-2 activities per customer with realistic first response delay (15 mins to 5 hours after creation)
                $activityCount = rand(1, 2);
                $firstActDate = (clone $createdDate)->addMinutes(rand(30, 240));

                for ($j = 0; $j < $activityCount; $j++) {
                    $actDate = $j === 0 ? $firstActDate : (clone $firstActDate)->addHours(rand(4, 24));

                    CustomerActivity::withoutGlobalScopes()->create([
                        'customer_id' => $customer->id,
                        'user_id' => $salesUser->id,
                        'activity_type' => fake()->randomElement(ActivityType::cases())->value,
                        'notes' => fake()->optional(0.7)->sentence(),
                        'follow_up_date' => fake()->optional(0.3)->dateTimeBetween('now', '+7 days'),
                        'created_at' => $actDate,
                        'updated_at' => $actDate,
                    ]);
                }
            }
        }

        $this->command->info('✅ Seeded: 1 admin, 2 team leaders, 5 sales, 20 sample customers with activities.');
        $this->command->info('');
        $this->command->info('Login credentials:');
        $this->command->info('  admin@crm.test         / password  (مدير النظام)');
        $this->command->info('  leader1@crm.test       / password  (محمد مدير الفريق)');
        $this->command->info('  leader2@crm.test       / password  (أحمد مدير الفريق)');
        $this->command->info('  sales1@crm.test        / password  (علي مندوب)');
        $this->command->info('  sales2@crm.test        / password  (سارة مندوبة)');
    }
}
