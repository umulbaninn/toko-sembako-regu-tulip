<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitPublisher
{
    public const EXCHANGE = 'sembako.events';

    public function publish(string $routingKey, array $payload): void
    {
        $c = config('services.rabbitmq');
        $conn = new AMQPStreamConnection($c['host'], $c['port'], $c['user'], $c['password']);
        $ch = $conn->channel();

        $ch->exchange_declare(self::EXCHANGE, 'topic', false, true, false);

        $pesan = new AMQPMessage(json_encode($payload, JSON_UNESCAPED_UNICODE), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $payload['message_id'],
        ]);
        $ch->basic_publish($pesan, self::EXCHANGE, $routingKey);

        $ch->close();
        $conn->close();
    }
}