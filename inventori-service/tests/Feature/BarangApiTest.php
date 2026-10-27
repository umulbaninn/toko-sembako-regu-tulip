<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangApiTest extends TestCase
{
    use RefreshDatabase;

    private function problem($res): void
    {
        $this->assertStringContainsString('application/problem+json', $res->headers->get('Content-Type'));
    }

    public function test_daftar_barang_200(): void
    {
        Barang::factory()->count(3)->create();
        $this->getJson('/api/barang')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_tambah_barang_201(): void
    {
        $this->postJson('/api/barang', ['sku' => 'BRG-009', 'nama' => 'Kopi', 'harga' => 5000])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'BRG-009')
            ->assertJsonPath('data.stok', 0);
    }

    public function test_tambah_barang_tanpa_nama_422(): void
    {
        $res = $this->postJson('/api/barang', ['sku' => 'BRG-009', 'harga' => 5000]);
        $res->assertStatus(422)->assertJsonValidationErrors(['nama']);
        $this->problem($res);
    }

    public function test_barang_tidak_ada_404(): void
    {
        $res = $this->getJson('/api/barang/BRG-999');
        $res->assertStatus(404);
        $this->problem($res);
    }

    public function test_penjualan_mengurangi_stok_dan_mencatat_outbox(): void
    {
        Barang::factory()->create(['sku' => 'BRG-001', 'stok' => 12, 'stok_minimum' => 10]);

        $this->postJson('/api/barang/BRG-001/penyesuaian-stok', [
            'jumlah' => 3, 'alasan' => 'penjualan', 'referensi' => 'TRX-1',
        ])->assertOk()->assertJsonPath('data.stok', 9);

        $this->assertDatabaseHas('barang', ['sku' => 'BRG-001', 'stok' => 9]);
        $this->assertSame(1, Outbox::count());
    }

    public function test_restok_menambah_stok_dan_mencatat_outbox(): void
    {
        Barang::factory()->create(['sku' => 'BRG-001', 'stok' => 10]);

        $this->postJson('/api/barang/BRG-001/penyesuaian-stok', [
            'jumlah' => 5, 'alasan' => 'restok', 'referensi' => 'PO-1',
        ])->assertOk()->assertJsonPath('data.stok', 15);

        $this->assertDatabaseHas('barang', ['sku' => 'BRG-001', 'stok' => 15]);
        $this->assertSame(1, Outbox::count());
    }

    public function test_stok_tidak_cukup_409_dan_tidak_mencatat_outbox(): void
    {
        Barang::factory()->create(['sku' => 'BRG-001', 'stok' => 2]);

        $res = $this->postJson('/api/barang/BRG-001/penyesuaian-stok', [
            'jumlah' => 5, 'alasan' => 'penjualan', 'referensi' => 'TRX-2',
        ]);

        $res->assertStatus(409)->assertJsonPath('title', 'Stok tidak mencukupi');
        $this->problem($res);
        $this->assertDatabaseHas('barang', ['sku' => 'BRG-001', 'stok' => 2]);
        $this->assertSame(0, Outbox::count());
    }

    public function test_jumlah_nol_ditolak_422(): void
    {
        Barang::factory()->create(['sku' => 'BRG-001']);

        $this->postJson('/api/barang/BRG-001/penyesuaian-stok', [
            'jumlah' => 0, 'alasan' => 'penjualan', 'referensi' => 'TRX-3',
        ])->assertStatus(422)->assertJsonValidationErrors(['jumlah']);

        $this->assertSame(0, Outbox::count());
    }
}