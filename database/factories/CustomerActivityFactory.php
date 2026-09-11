<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomerStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerActivity>
 */
class CustomerActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
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

        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory()->sales(),
            'activity_type' => fake()->randomElement(ActivityType::cases())->value,
            'old_status_id' => null,
            'new_status_id' => null,
            'notes' => fake()->optional(0.85)->randomElement($arabicNotes),
            'follow_up_date' => fake()->optional(0.4)->dateTimeBetween('now', '+14 days'),
        ];
    }

    /**
     * A status change activity.
     */
    public function statusChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => ActivityType::StatusChange->value,
            'old_status_id' => CustomerStatus::inRandomOrder()->value('id'),
            'new_status_id' => CustomerStatus::inRandomOrder()->value('id'),
        ]);
    }
}
