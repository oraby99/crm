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

        // ── Team Leader ──────────────────────────────────────────
        $leader = User::firstOrCreate(
            ['email' => 'leader1@crm.test'],
            [
                'name' => 'مدير الفريق',
                'password' => Hash::make('password'),
                'role' => UserRole::TeamLeader,
                'is_active' => true,
            ]
        );

        // ── Sales Employee ───────────────────────────────────────
        $sales = User::firstOrCreate(
            ['email' => 'sales1@crm.test'],
            [
                'name' => 'علي مندوب',
                'password' => Hash::make('password'),
                'role' => UserRole::Sales,
                'team_leader_id' => $leader->id,
                'is_active' => true,
            ]
        );

        // ── Sample Customers ─────────────────────────────────────
        $platforms = Platform::all();
        $needs = CustomerNeed::all();
        $statuses = CustomerStatus::all();

        if ($platforms->isEmpty() || $needs->isEmpty() || $statuses->isEmpty()) {
            $this->command->warn('Skipping customer seeding — run PlatformSeeder, CustomerNeedSeeder, CustomerStatusSeeder first.');

            return;
        }

        $arabicNotes = [
            'تم التواصل هاتفيًا والاتفاق على موعد معاينة',
            'طلب إرسال كشوفات الأسعار والكتالوج عبر واتساب',
            'العميل يفكر وجاري المتابعة الأسبوع القادم',
            'تم الاتصال بالعميل ولم يقم بالرد',
            'متابعة هاتفية بخصوص خصم المقايسة المبدئية',
            'تم إرسال نماذج سابقة الأعمال والتصاميم المطلوبة',
            'العميل متردد بين HDF و SPC وجاري شرح الفروق',
            'طلب تعديل في المقايسة المبدئية وإعادة الإرسال',
            'تم تحديد موعد لتوقيع العقد واستلام العربون',
            'مكالمة تذكيرية بموعد المعاينة المعتمد',
            'طلب الاستفسار عن الضمان وطريقة التركيب',
            'العميل مشغول وطلب الاتصال به غداً مساءً',
        ];

        for ($i = 1; $i <= 10; $i++) {
            $createdDate = now()->subDays(rand(1, 10))->subHours(rand(1, 12));

            $customer = Customer::factory()->forSales($sales)->create([
                'phone' => '010'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'created_at' => $createdDate,
                'updated_at' => $createdDate,
            ]);

            // Add 1-2 activities per customer with realistic first response delay (30 mins to 4 hours after creation)
            $activityCount = rand(1, 2);
            $firstActDate = (clone $createdDate)->addMinutes(rand(30, 240));

            for ($j = 0; $j < $activityCount; $j++) {
                $actDate = $j === 0 ? $firstActDate : (clone $firstActDate)->addHours(rand(4, 24));

                CustomerActivity::withoutGlobalScopes()->create([
                    'customer_id' => $customer->id,
                    'user_id' => $sales->id,
                    'activity_type' => fake()->randomElement(ActivityType::cases())->value,
                    'notes' => fake()->randomElement($arabicNotes),
                    'follow_up_date' => fake()->optional(0.3)->dateTimeBetween('now', '+7 days'),
                    'created_at' => $actDate,
                    'updated_at' => $actDate,
                ]);
            }
        }

        $this->command->info('✅ Seeded: 1 admin, 1 team leader, 1 sales, 10 sample customers with activities.');
        $this->command->info('');
        $this->command->info('Login credentials:');
        $this->command->info('  admin@crm.test         / password  (مدير النظام)');
        $this->command->info('  leader1@crm.test       / password  (مدير الفريق)');
        $this->command->info('  sales1@crm.test        / password  (علي مندوب)');
    }
}
