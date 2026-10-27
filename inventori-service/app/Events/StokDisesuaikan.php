<?php

namespace App\Events;

use Illuminate\Support\Str;

class StokDisesuaikan
{

    public readonly string $messageId;
    public readonly string $terjadiPada;

    public function __construct(
        public readonly string $sku,
        public readonly string $nama,
        public readonly string $alasan,
        public readonly int $jumlah,
        public readonly int $stokSisa,
        public readonly int $stokMinimum,
        public readonly string $referensi,
    ) {
        $this->messageId = (string) Str::uuid();
        $this->terjadiPada = now()->toIso8601String();
    }

    public function routingKey(): string
    {
        return 'stok.' . $this->alasan;
    }

    public function toPayload(): array
    {
        return [
            'message_id' => $this->messageId,
            'event' => 'StokDisesuaikan',
            'occurred_at' => $this->terjadiPada,
            'data' => [
                'sku' => $this->sku,
                'nama' => $this->nama,
                'alasan' => $this->alasan,
                'jumlah' => $this->jumlah,
                'stok_sisa' => $this->stokSisa,
                'stok_minimum' => $this->stokMinimum,
                'referensi' => $this->referensi,
            ],
        ];
    }
}