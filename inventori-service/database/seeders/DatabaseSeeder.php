<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Barang;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            ['sku' => 'BRG-001', 'nama' => 'Beras 5kg', 'harga' => 65000, 'stok' => 100, 'stok_minimum' => 20],
            ['sku' => 'BRG-002', 'nama' => 'Minyak Goreng 2L', 'harga' => 38000, 'stok' => 12, 'stok_minimum' => 10],
            ['sku' => 'BRG-003', 'nama' => 'Gula Pasir 1kg', 'harga' => 17000, 'stok' => 80, 'stok_minimum' => 15],
        ] as $b) {
            Barang::create($b);
        }
    }
}
