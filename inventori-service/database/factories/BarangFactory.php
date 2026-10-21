<?php

namespace Database\Factories;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barang>
 */
class BarangFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => 'BRG-' . fake()->unique()->numerify('###'),
            'nama' => fake()->words(2, true),
            'harga' => 10000,
            'stok' => 100,
            'stok_minimum' => 10,
        ];
    }
}
