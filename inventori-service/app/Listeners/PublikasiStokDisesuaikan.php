<?php

namespace App\Listeners;

use App\Events\StokDisesuaikan;
use App\Services\RabbitPublisher;

class PublikasiStokDisesuaikan
{
    public function __construct(private RabbitPublisher $publisher) {}

    public function handle(StokDisesuaikan $event): void
    {
        try {
            $this->publisher->publish($event->routingKey(), $event->toPayload());
        } catch (\Throwable $e) {
            report($e); // broker mati tidak boleh menggagalkan penjualan yang sudah tersimpan
        }
    }
}