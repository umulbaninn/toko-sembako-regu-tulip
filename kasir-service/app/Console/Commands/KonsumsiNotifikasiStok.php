<?php

namespace App\Console\Commands;

use App\Models\NotifikasiSupplier;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class KonsumsiNotifikasiStok extends Command
{
    protected $signature = 'notifikasi:konsumsi';
    protected $description = 'Konsumsi event stok dari RabbitMQ dan catat notifikasi supplier';

    public function handle(): int
    {
        $c = config('services.rabbitmq');
        $conn = new AMQPStreamConnection($c['host'], $c['port'], $c['user'], $c['password']);
        $ch = $conn->channel();

        // Topologi
        $ch->exchange_declare('sembako.events', 'topic', false, true, false);
        $ch->exchange_declare('sembako.dlx', 'fanout', false, true, false);
        $ch->queue_declare('notifikasi.stok.dlq', false, true, false, false);
        $ch->queue_bind('notifikasi.stok.dlq', 'sembako.dlx');
        $ch->queue_declare('notifikasi.stok', false, true, false, false, false,
            new AMQPTable(['x-dead-letter-exchange' => 'sembako.dlx']));
        $ch->queue_bind('notifikasi.stok', 'sembako.events', 'stok.*');

        $ch->basic_qos(0, 1, false); // prefetch 1
        $ch->basic_consume('notifikasi.stok', '', false, false, false, false,
            fn (AMQPMessage $pesan) => $this->proses($pesan));

        $this->info('Menunggu pesan di antrean notifikasi.stok ... (Ctrl+C untuk berhenti)');
        while ($ch->is_consuming()) {
            $ch->wait();
        }

        $ch->close();
        $conn->close();
        return self::SUCCESS;
    }

    private function proses(AMQPMessage $pesan): void
    {
        // 1. Pesan rusak: tolak ke DLQ, jangan mematikan konsumen
        try {
            $isi = json_decode($pesan->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (empty($isi['message_id']) || empty($isi['data']['sku'])) {
                throw new \InvalidArgumentException('message_id atau data.sku kosong');
            }
        } catch (\Throwable $e) {
            $this->warn('Pesan rusak, dikirim ke DLQ: ' . $e->getMessage());
            $pesan->nack(false);
            return;
        }

        // 2. Proses normal, idempoten lewat message_id unik
        try {
            $d = $isi['data'];
            if ($d['stok_sisa'] <= $d['stok_minimum']) {
                $n = NotifikasiSupplier::firstOrCreate(
                    ['message_id' => $isi['message_id']],
                    ['sku' => $d['sku'], 'nama' => $d['nama'], 'stok_sisa' => $d['stok_sisa'], 'stok_minimum' => $d['stok_minimum']],
                );
                $this->info(($n->wasRecentlyCreated ? 'Notifikasi dibuat: ' : 'Duplikat diabaikan: ')
                    . "{$d['nama']} sisa {$d['stok_sisa']}");
            } else {
                $this->line("Stok {$d['nama']} aman ({$d['stok_sisa']}), tidak ada notifikasi.");
            }
            $pesan->ack();
        } catch (\Throwable $e) {
            report($e);
            $pesan->nack(! $pesan->isRedelivered()); // coba ulang sekali, lalu ke DLQ
        }
    }
}