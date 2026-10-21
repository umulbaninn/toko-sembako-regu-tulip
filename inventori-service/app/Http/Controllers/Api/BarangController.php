<?php

namespace App\Http\Controllers\Api;

use App\Events\StokDisesuaikan;
use App\Exceptions\StokTidakCukup;
use App\Http\Controllers\Controller;
use App\Http\Requests\PenyesuaianStokRequest;
use App\Http\Requests\TambahBarangRequest;
use App\Http\Resources\BarangResource;
use App\Models\Barang;
use Illuminate\Support\Facades\DB;

class BarangController extends Controller
{
    public function index()
    {
        return BarangResource::collection(Barang::orderBy('sku')->get());
    }

    public function store(TambahBarangRequest $request)
    {
        $barang = Barang::create($request->validated());
        $barang->refresh(); // muat nilai default dari database (stok = 0)

        return (new BarangResource($barang))->response()
            ->setStatusCode(201)
            ->header('Location', url("/api/barang/{$barang->sku}"));
    }

    public function show(Barang $barang)
    {
        return new BarangResource($barang);
    }

    public function sesuaikan(PenyesuaianStokRequest $request, Barang $barang)
    {
        $data = $request->validated();

        $barang = DB::transaction(function () use ($barang, $data) {
            $b = Barang::lockForUpdate()->findOrFail($barang->id);
            $baru = $data['alasan'] === 'penjualan'
                ? $b->stok - $data['jumlah']
                : $b->stok + $data['jumlah'];

            if ($baru < 0) {
                throw new StokTidakCukup($b->nama, $b->stok, $data['jumlah']);
            }

            $b->update(['stok' => $baru]);
            return $b;
        });

        // Dipanggil hanya setelah transaksi DB berhasil
        StokDisesuaikan::dispatch(
            $barang->sku, $barang->nama, $data['alasan'], $data['jumlah'],
            $barang->stok, $barang->stok_minimum, $data['referensi'],
        );

        return new BarangResource($barang);
    }
}