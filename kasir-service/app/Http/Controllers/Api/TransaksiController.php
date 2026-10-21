<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotifikasiSupplier;
use App\Models\Transaksi;
use App\Support\Problem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TransaksiController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'pelanggan' => ['required', 'string', 'max:100'],
            'sku' => ['required', 'regex:/^BRG-\d{3}$/'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'sumber' => ['nullable', 'in:whatsapp,langsung'],
        ]);

        $nomor = 'TRX-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));

        // Jalur SINKRON (RPC lewat REST) ke Inventori, dengan timeout
        try {
            $res = Http::baseUrl(config('services.inventori.url'))
                ->timeout(3)->acceptJson()
                ->post("/api/barang/{$data['sku']}/penyesuaian-stok", [
                    'jumlah' => $data['jumlah'],
                    'alasan' => 'penjualan',
                    'referensi' => $nomor,
                ]);
        } catch (ConnectionException $e) {
            return Problem::make(503, 'Layanan inventori tidak tersedia',
                'Transaksi tidak disimpan, silakan coba lagi beberapa saat.', 'inventori-tidak-tersedia');
        }

        if (in_array($res->status(), [404, 409, 422])) {
            // Teruskan Problem Details dari Inventori apa adanya
            return response()->json($res->json(), $res->status(), ['Content-Type' => 'application/problem+json']);
        }
        if ($res->failed()) {
            return Problem::make(502, 'Respons inventori tidak valid',
                'Inventori mengembalikan galat tak terduga.', 'inventori-galat');
        }

        $b = $res->json('data');
        $trx = Transaksi::create([
            'nomor' => $nomor,
            'pelanggan' => $data['pelanggan'],
            'sku' => $b['sku'],
            'nama_barang' => $b['nama'],
            'jumlah' => $data['jumlah'],
            'harga_satuan' => $b['harga'],
            'total' => $b['harga'] * $data['jumlah'],
            'sumber' => $data['sumber'] ?? 'langsung',
        ]);

        return response()->json(['data' => $trx], 201);
    }

    public function show(Transaksi $transaksi)
    {
        return response()->json(['data' => $transaksi]);
    }

    // Modul Laporan: membaca tabel yang sama dengan Kasir (shared database)
    public function ringkasan()
    {
        return response()->json(['data' => [
            'jumlah_transaksi' => Transaksi::count(),
            'total_penjualan' => (int) Transaksi::sum('total'),
        ]]);
    }

    public function notifikasi()
    {
        return response()->json(['data' => NotifikasiSupplier::orderByDesc('id')->get()]);
    }
}