<?php

use App\Http\Controllers\Api\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::post('/transaksi', [TransaksiController::class, 'store']);
Route::get('/transaksi/{transaksi}', [TransaksiController::class, 'show']);
Route::get('/laporan/ringkasan', [TransaksiController::class, 'ringkasan']);
Route::get('/notifikasi-supplier', [TransaksiController::class, 'notifikasi']);