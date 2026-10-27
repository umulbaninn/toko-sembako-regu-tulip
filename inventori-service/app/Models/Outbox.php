<?php

namespace App\Models;

use App\Events\StokDisesuaikan;
use Illuminate\Database\Eloquent\Model;

class Outbox extends Model
{
    protected $table = 'outbox';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'dikirim_pada' => 'datetime'];
    }

    /** Catat event sebagai pesan tertunda. Panggil di DALAM transaksi bisnis. */
    public static function catat(StokDisesuaikan $event): self
    {
        return static::create([
            'message_id' => $event->messageId,
            'jenis' => 'StokDisesuaikan',
            'routing_key' => $event->routingKey(),
            'payload' => $event->toPayload(),
        ]);
    }
}