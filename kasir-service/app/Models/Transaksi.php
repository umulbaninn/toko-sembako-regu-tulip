<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $table = 'transaksi';
    protected $fillable = ['nomor', 'pelanggan', 'sku', 'nama_barang', 'jumlah', 'harga_satuan', 'total', 'sumber'];

    protected function casts(): array
    {
        return ['jumlah' => 'integer', 'harga_satuan' => 'integer', 'total' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'nomor';
    }
}