# Sistem Integrasi Toko Grosir Sembako (Regu Tulip)

Proyek akhir Integrasi Sistem dan Komputasi Awan (25IF1504), STMIK Bandung.
Anggota: Alexa Agmelia Rahayu (1224016), Berlina Shobirah (1224008), Umul Banin (1224023)

## Arsitektur singkat
- inventori-service (Laravel, port 8001): barang dan stok. Perubahan stok dan pencatatan event ke tabel `outbox` terjadi dalam satu transaksi database (transactional outbox).
- kasir-service (Laravel, port 8002): transaksi, laporan, dan konsumen event notifikasi supplier.
- RabbitMQ (satu compose dengan Inventori): AMQP 5672, UI http://localhost:15672 (guest/guest).
- Kasir memanggil Inventori lewat REST sinkron (timeout 3 detik).
- Inventori mencatat event di outbox, command `outbox:relay` menerbitkannya ke RabbitMQ, dan Kasir mengonsumsinya (idempoten lewat `message_id`; pesan rusak masuk DLQ).

## Prasyarat
Docker Desktop (integrasi WSL2 aktif) dan WSL2 Ubuntu.

## Menjalankan dari nol
1. Inventori (sekaligus RabbitMQ):

       cd inventori-service
       cp .env.example .env
       docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app composer:2 install --ignore-platform-reqs
       ./vendor/bin/sail up -d
       ./vendor/bin/sail artisan key:generate
       ./vendor/bin/sail artisan migrate --seed

2. Kasir:

       cd ../kasir-service
       cp .env.example .env
       docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app composer:2 install --ignore-platform-reqs
       ./vendor/bin/sail up -d
       ./vendor/bin/sail artisan key:generate
       ./vendor/bin/sail artisan migrate

3. Dua proses latar belakang (terminal terpisah). Jalankan konsumen lebih dulu supaya antrean terbentuk:

       cd kasir-service && ./vendor/bin/sail artisan notifikasi:konsumsi
       cd inventori-service && ./vendor/bin/sail artisan outbox:relay --terus

## Mencoba cepat

    curl -s http://localhost:8001/api/barang -H "Accept: application/json"
    curl -s -X POST http://localhost:8002/api/transaksi \
      -H "Content-Type: application/json" -H "Accept: application/json" \
      -d '{"pelanggan":"Warung Bu Ani","sku":"BRG-002","jumlah":3}'

## Test

    cd inventori-service && ./vendor/bin/sail artisan test
    cd kasir-service && ./vendor/bin/sail artisan test

`OutboxTest` memuat 6 test: pencatatan ke outbox (3) dan message relay (3).

## Kontrak Event
Event menyatakan sesuatu yang sudah terjadi, sehingga dinamai dalam bentuk lampau.

### Ringkasan

| Atribut | Isi |
| --- | --- |
| Nama event | `StokDisesuaikan` |
| Penerbit | Inventori Service (command `outbox:relay`, membaca tabel `outbox`) |
| Penerima | Notifikasi Supplier (command `notifikasi:konsumsi` di kasir-service) |
| Exchange | `sembako.events` (tipe `topic`, durable) |
| Routing key | `stok.penjualan` (alasan = penjualan), `stok.restok` (alasan = restok) |
| Antrean | `notifikasi.stok` (durable, terikat ke exchange dengan pola `stok.*`) |
| Dead letter exchange | `sembako.dlx` (tipe `fanout`, durable) |
| Dead letter queue | `notifikasi.stok.dlq` (terikat ke `sembako.dlx`) |
| Dipicu saat | Penyesuaian stok berhasil (`POST /api/barang/{sku}/penyesuaian-stok`). Event dicatat di tabel `outbox` dalam transaksi database yang sama dengan perubahan stok. |
| Format pesan | JSON (`content_type: application/json`), pesan persisten |
| Properti AMQP | `message_id` (sama dengan field `message_id`), `type` = `StokDisesuaikan` |

### Field pesan

| Field | Tipe | Wajib | Keterangan | Contoh |
| --- | --- | --- | --- | --- |
| `message_id` | string (UUID) | ya | Unik per pesan, kunci idempotensi konsumen | `3f2c9a7e-...` |
| `event` | string | ya | Nama event | `StokDisesuaikan` |
| `occurred_at` | string (ISO 8601) | ya | Waktu kejadian | `2026-10-21T10:00:00+07:00` |
| `data.sku` | string | ya | Kode barang, pola `BRG-nnn` | `BRG-002` |
| `data.nama` | string | ya | Nama barang | `Minyak Goreng 2L` |
| `data.alasan` | string | ya | `penjualan` atau `restok` | `penjualan` |
| `data.jumlah` | integer | ya | Jumlah yang disesuaikan (minimal 1) | `3` |
| `data.stok_sisa` | integer | ya | Stok setelah penyesuaian | `9` |
| `data.stok_minimum` | integer | ya | Batas stok minimum barang | `10` |
| `data.referensi` | string | ya | Nomor transaksi atau nomor pesanan | `TRX-20261021-AB12C` |

### Contoh pesan

```json
{
  "message_id": "3f2c9a7e-5b1d-4c8a-9e21-7a6d0f4b8c12",
  "event": "StokDisesuaikan",
  "occurred_at": "2026-10-21T10:00:00+07:00",
  "data": {
    "sku": "BRG-002",
    "nama": "Minyak Goreng 2L",
    "alasan": "penjualan",
    "jumlah": 3,
    "stok_sisa": 9,
    "stok_minimum": 10,
    "referensi": "TRX-20261021-AB12C"
  }
}
```

### Aturan pemrosesan

- **Notifikasi supplier** hanya dibuat jika `data.stok_sisa <= data.stok_minimum`. Selain itu pesan hanya di-ack.
- **Idempoten.** Relay menjamin pengiriman minimal satu kali (at-least-once), sehingga pesan bisa terkirim lebih dari sekali. Konsumen mencatat notifikasi dengan `message_id` yang unik, jadi duplikat diabaikan.
- **Pesan rusak** (JSON tidak valid, atau `message_id` atau `data.sku` kosong) ditolak ke `notifikasi.stok.dlq` lewat `sembako.dlx`, dan konsumen tetap berjalan.
- **Galat tak terduga** saat memproses: pesan dikembalikan ke antrean satu kali, lalu dikirim ke DLQ bila gagal lagi.
- **Urutan menyalakan:** jalankan konsumen lebih dulu, karena konsumen yang membuat antrean `notifikasi.stok` dan mengikatnya ke exchange.

- inventori-service/openapi.yaml dan kasir-service/openapi.yaml (OpenAPI 3.1.0, lolos Spectral)

## Pembagian tugas
- Berlina Shobirah: penyesuaian stok, outbox dan relay, transaksi dan konsumen event, beserta test
- Alexa Agmelia Rahayu: CRUD barang, penanganan galat, openapi.yaml Inventori, detail transaksi
- Umul Banin: RabbitMQ, laporan dan model di Kasir, openapi.yaml Kasir, kontrak event, diagram