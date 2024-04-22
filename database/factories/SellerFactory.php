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
            'email2' => $this->faker->boolean(15) ? $this->faker->unique()->safeEmail : null,
            'rating' => $this->faker->numberBetween(1,3),
            'notes'=> $this->faker->boolean(30) ? $this->faker->paragraph() : null
        ];
    }
}
