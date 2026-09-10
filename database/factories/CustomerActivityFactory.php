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
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory()->sales(),
            'activity_type' => fake()->randomElement(ActivityType::cases())->value,
            'old_status_id' => null,
            'new_status_id' => null,
            'notes' => fake()->optional(0.8)->sentence(),
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
