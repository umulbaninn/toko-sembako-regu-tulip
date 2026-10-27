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
use App\Models\Outbox;

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
            $b->refresh();

            // Transactional Outbox: event dicatat dalam transaksi yang SAMA dengan perubahan stok.
            // Keduanya tersimpan bersama, atau tidak sama sekali.
            Outbox::catat(new StokDisesuaikan(
                $b->sku, $b->nama, $data['alasan'], $data['jumlah'],
                $b->stok, $b->stok_minimum, $data['referensi'],
            ));

            return $b;
        });

        // Pengiriman ke RabbitMQ diurus command outbox:relay, bukan controller.
        return new BarangResource($barang);
    }
}