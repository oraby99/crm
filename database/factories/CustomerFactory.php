<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerNeed;
use App\Models\CustomerStatus;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $arabicDetails = [
            'مهتم جداً ومحتاج معاينة للموقع لشقة 150 متر',
            'بيسأل عن أسعار الـ HDF والأبواب الداخلية',
            'تواصل عبر واتساب وطلب كتالوج سابقة الأعمال',
            'تم إرسال المقايسة المبدئية وفي انتظار رد العميل',
            'موافق على السعر ومحتاج تحديد موعد المعاينة والمعاينة خشب',
            'عميل محول من إعلان فيسبوك وبيسأل عن تجليد الحوائط',
            'استفسار عن أسعار الـ SPC وضمان المنتجات',
            'طلب خصم إضافي على الكميات وجاري المتابعة مع مدير الفريق',
        ];

        return [
            'name' => fake('ar_SA')->name(),
            'phone' => '01'.fake()->randomElement(['0', '1', '2', '5']).fake()->numerify('########'),
            'whatsapp_phone' => fake()->boolean(70) ? ('01'.fake()->randomElement(['0', '1', '2', '5']).fake()->numerify('########')) : null,
            'platform_id' => Platform::inRandomOrder()->value('id') ?? Platform::factory(),
            'customer_need_id' => CustomerNeed::inRandomOrder()->value('id') ?? CustomerNeed::factory(),
            'details' => fake()->randomElement($arabicDetails),
            'status_id' => CustomerStatus::inRandomOrder()->value('id') ?? CustomerStatus::factory(),
            'next_follow_up_at' => fake()->boolean(60) ? fake()->dateTimeBetween('-3 days', '+10 days') : null,
        ];
    }

    /**
     * Customer that has not been contacted yet.
     */
    public function notContacted(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_follow_up_at' => null,
        ]);
    }

    /**
     * Customer with an overdue follow-up.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_follow_up_at' => fake()->dateTimeBetween('-14 days', '-1 day'),
        ]);
    }

    /**
     * Customer with today's follow-up.
     */
    public function followUpToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_follow_up_at' => today(),
        ]);
    }

    /**
     * Assign to a specific sales user.
     */
    public function forSales(User $sales): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_id' => $sales->id,
            'team_leader_id' => $sales->team_leader_id,
            'created_by' => $sales->id,
        ]);
    }
}
