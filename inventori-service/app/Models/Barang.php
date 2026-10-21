<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;

    protected $table = 'barang';
    protected $fillable = ['sku', 'nama', 'harga', 'stok', 'stok_minimum'];

    protected function casts(): array
    {
        return ['harga' => 'integer', 'stok' => 'integer', 'stok_minimum' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'sku';
    }
}