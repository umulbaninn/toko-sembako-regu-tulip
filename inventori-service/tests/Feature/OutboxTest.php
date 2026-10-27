<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Outbox;
use App\Services\RabbitPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    private function barangContoh(int $stok = 10): Barang
    {
        return Barang::create(['sku' => 'BRG-001', 'nama' => 'Beras 5kg', 'harga' => 65000, 'stok' => $stok, 'stok_minimum' => 5]);
    }

    private function jual(int $jumlah)
    {
        return $this->postJson('/api/barang/BRG-001/penyesuaian-stok', [
            'jumlah' => $jumlah, 'alasan' => 'penjualan', 'referensi' => 'TRX-UJI',
        ]);
    }

    // --- Bagian 1: pencatatan ke outbox -----------------------------------

    public function test_penjualan_mencatat_satu_pesan_tertunda_di_outbox(): void
    {
        $this->barangContoh(stok: 10);

        $this->jual(3)->assertOk();

        $this->assertSame(1, Outbox::count());
        $pesan = Outbox::first();
        $this->assertSame('stok.penjualan', $pesan->routing_key);
        $this->assertSame('StokDisesuaikan', $pesan->jenis);
        $this->assertSame(7, $pesan->payload['data']['stok_sisa']);
        $this->assertNull($pesan->dikirim_pada);
    }

    public function test_penjualan_yang_ditolak_tidak_mencatat_apa_pun(): void
    {
        $this->barangContoh(stok: 3);

        $this->jual(5)->assertStatus(409);

        $this->assertSame(0, Outbox::count());
    }

    public function test_bila_outbox_gagal_disimpan_stok_ikut_dibatalkan(): void
    {
        $this->barangContoh(stok: 10);

        Outbox::creating(fn () => throw new RuntimeException('disk penuh'));

        $this->jual(3)->assertStatus(500);

        $this->assertSame(10, Barang::first()->stok); // rollback: tidak ada perubahan setengah jadi
    }

    // --- Bagian 2: message relay -------------------------------------------

    public function test_relay_menerbitkan_pesan_tertunda_lalu_menandainya(): void
    {
        $this->barangContoh(stok: 10);
        $this->jual(3);
        $pesan = Outbox::first();

        $this->mock(RabbitPublisher::class, function ($mock) use ($pesan) {
            $mock->shouldReceive('publish')->once()->with(
                'stok.penjualan',
                Mockery::on(fn (array $data) => $data['data']['sku'] === 'BRG-001' && $data['data']['stok_sisa'] === 7),
                $pesan->message_id,
                'StokDisesuaikan',
            );
        });

        $this->artisan('outbox:relay')->assertSuccessful();

        $this->assertNotNull($pesan->fresh()->dikirim_pada);
    }

    public function test_relay_tidak_mengirim_ulang_pesan_yang_sudah_terkirim(): void
    {
        $this->barangContoh(stok: 10);
        $this->jual(3);
        Outbox::query()->update(['dikirim_pada' => now()]);

        $this->mock(RabbitPublisher::class, fn ($mock) => $mock->shouldNotReceive('publish'));

        $this->artisan('outbox:relay')->assertSuccessful();
    }

    public function test_bila_broker_mati_pesan_tetap_menunggu_dan_urutan_terjaga(): void
    {
        $this->barangContoh(stok: 10);
        $this->jual(1);
        $this->jual(2);

        $this->mock(RabbitPublisher::class, function ($mock) {
            // Hanya satu percobaan: setelah gagal, relay berhenti dan tidak melompati pesan pertama.
            $mock->shouldReceive('publish')->once()->andThrow(new RuntimeException('Connection refused'));
        });

        $this->artisan('outbox:relay')->assertSuccessful();

        $this->assertSame(2, Outbox::whereNull('dikirim_pada')->count());
    }
}