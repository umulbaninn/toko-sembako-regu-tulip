<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransaksiApiTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = ['pelanggan' => 'Warung Bu Ani', 'sku' => 'BRG-001', 'jumlah' => 5, 'sumber' => 'whatsapp'];

    public function test_transaksi_berhasil_201(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            'sku' => 'BRG-001', 'nama' => 'Beras 5kg', 'harga' => 65000, 'stok' => 95, 'stok_minimum' => 20,
        ]], 200)]);

        $this->postJson('/api/transaksi', $this->payload)
            ->assertCreated()
            ->assertJsonPath('data.total', 325000);
        $this->assertDatabaseCount('transaksi', 1);
    }

    public function test_stok_tidak_cukup_diteruskan_409(): void
    {
        Http::fake(['*' => Http::response([
            'title' => 'Stok tidak mencukupi', 'status' => 409, 'detail' => 'Stok tersisa 2',
        ], 409)]);

        $res = $this->postJson('/api/transaksi', $this->payload);
        $res->assertStatus(409)->assertJsonPath('title', 'Stok tidak mencukupi');
        $this->assertStringContainsString('application/problem+json', $res->headers->get('Content-Type'));
        $this->assertDatabaseCount('transaksi', 0);
    }

    public function test_inventori_mati_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));

        $res = $this->postJson('/api/transaksi', $this->payload);
        $res->assertStatus(503);
        $this->assertStringContainsString('application/problem+json', $res->headers->get('Content-Type'));
        $this->assertDatabaseCount('transaksi', 0);
    }

    public function test_validasi_gagal_422(): void
    {
        $this->postJson('/api/transaksi', [])->assertStatus(422)->assertJsonValidationErrors(['pelanggan', 'sku', 'jumlah']);
    }

    public function test_laporan_ringkasan(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            'sku' => 'BRG-001', 'nama' => 'Beras 5kg', 'harga' => 65000, 'stok' => 95, 'stok_minimum' => 20,
        ]], 200)]);
        $this->postJson('/api/transaksi', $this->payload);

        $this->getJson('/api/laporan/ringkasan')
            ->assertOk()
            ->assertJsonPath('data.jumlah_transaksi', 1)
            ->assertJsonPath('data.total_penjualan', 325000);
    }
}