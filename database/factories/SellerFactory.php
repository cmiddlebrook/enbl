<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Seller>
 */
class SellerFactory extends Factory
{

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'email2' => $this->faker->boolean(10) ? $this->faker->unique()->safeEmail : null,
            'notes'=> $this->faker->boolean(20) ? $this->faker->paragraph() : null
        ];
    }
}
