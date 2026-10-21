<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotifikasiSupplier extends Model
{
    protected $table = 'notifikasi_supplier';
    protected $fillable = ['message_id', 'sku', 'nama', 'stok_sisa', 'stok_minimum', 'status'];
}