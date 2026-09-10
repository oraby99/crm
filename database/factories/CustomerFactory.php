<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerNeed;
use App\Models\CustomerStatus;
use App\Models\Platform;
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
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'whatsapp_phone' => fake()->optional(0.6)->numerify('01#########'),
            'platform_id' => Platform::inRandomOrder()->value('id'),
            'customer_need_id' => CustomerNeed::inRandomOrder()->value('id'),
            'details' => fake()->optional(0.7)->paragraph(),
            'status_id' => CustomerStatus::inRandomOrder()->value('id'),
            'next_follow_up_at' => fake()->optional(0.5)->dateTimeBetween('-3 days', '+14 days'),
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
}
