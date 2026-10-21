# Sistem Integrasi Toko Grosir Sembako (Regu Tulip)

Anggota dan tugas:
- Alexa Agmelia Rahayu (1224016): Inventori Service, openapi Inventori
- Berlina Shobirah (1224008): Kasir Service, test Kasir
- Umul Banin (1224023): RabbitMQ, konsumen, kontrak event, dokumen, README

## Prasyarat
Docker Desktop, WSL2 Ubuntu

## Menjalankan dari nol
1. Inventori: port 80 (http://localhost):
   cd inventori-service && cp .env.example .env
   docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
   ./vendor/bin/sail up -d
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate --seed
2. Kasir, port 8081: ulangi langkah yang sama di kasir-service (tanpa --seed)
3. Konsumen: cd kasir-service && ./vendor/bin/sail artisan notifikasi:konsumsi
4. RabbitMQ UI: http://localhost:15672.

## Test
./vendor/bin/sail artisan test   (jalankan di tiap proyek)

## Kontrak
inventori-service/openapi.yaml, kasir-service/openapi.yaml, kontrak-event.md
RabbitMQ UI: http://localhost:15672 (guest/guest)