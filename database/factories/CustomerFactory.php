<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            // company_name is optional but good to have
            'company_name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'currency' => 'USD',
            'address_line_1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'status' => CustomerStatus::Active,
        ];
    }
}
