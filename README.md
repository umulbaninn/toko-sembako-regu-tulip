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

## Hasil uji skenario outbox
Diisi dari hasil uji nyata (terminal A: konsumen, B: relay, C: curl).

| Skenario | Yang diharapkan | Yang terjadi | Sesuai? |
|---|---|---|---|
| RabbitMQ mati saat penjualan | API tetap 201, pesan menunggu di outbox, terkirim setelah RabbitMQ menyala | | |
| Relay mengirim ulang | Konsumen mencetak "Duplikat diabaikan", notifikasi tidak bertambah | | |
| Tiga penjualan saat RabbitMQ mati | Tiga pesan tertunda, terkirim berurutan dengan stok menurun | | |

## Proses bisnis dan compensating transaction
| Langkah | Layanan | Transaksi | Compensating transaction |
|---|---|---|---|
| 1 | Kasir | Terima permintaan dan validasi | Tidak ada |
| 2 | Inventori | Kurangi stok dan catat outbox | Penyesuaian stok `restok` (rencana Checkpoint 2) |
| 3 | Kasir | Simpan transaksi | Memanggil kompensasi langkah 2 (rencana Checkpoint 2) |
| 4 | Notifikasi Supplier | Catat notifikasi stok menipis | Tidak perlu (idempoten, dapat diulang dari DLQ) |

## Kontrak dan dokumen
- inventori-service/openapi.yaml dan kasir-service/openapi.yaml (OpenAPI 3.1.0, lolos Spectral)
- kontrak-event.md (event StokDisesuaikan)
- docs/ (diagram arsitektur dan laporan Checkpoint 1)

## Pembagian tugas
- Berlina Shobirah: penyesuaian stok, outbox dan relay, transaksi dan konsumen event, beserta test
- Alexa Agmelia Rahayu: CRUD barang, penanganan galat, openapi.yaml Inventori, detail transaksi
- Umul Banin: RabbitMQ, laporan dan model di Kasir, openapi.yaml Kasir, kontrak event, diagram, README