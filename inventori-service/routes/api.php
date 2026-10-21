<?php

use App\Http\Controllers\Api\BarangController;
use Illuminate\Support\Facades\Route;

Route::get('/barang', [BarangController::class, 'index']);
Route::post('/barang', [BarangController::class, 'store']);
Route::get('/barang/{barang}', [BarangController::class, 'show']);
Route::post('/barang/{barang}/penyesuaian-stok', [BarangController::class, 'sesuaikan']);