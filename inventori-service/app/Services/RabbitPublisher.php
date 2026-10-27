<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitPublisher
{
    public const EXCHANGE = 'sembako.events';

    public function publish(string $routingKey, array $payload, ?string $messageId = null, ?string $jenis = null): void
    {
        $c = config('services.rabbitmq');
        $conn = new AMQPStreamConnection($c['host'], $c['port'], $c['user'], $c['password']);
        $ch = $conn->channel();

        $ch->exchange_declare(self::EXCHANGE, 'topic', false, true, false);

        $properti = array_filter([
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $messageId ?? ($payload['message_id'] ?? null),
            'type' => $jenis ?? ($payload['event'] ?? null),
        ], fn ($v) => $v !== null);

        $ch->basic_publish(
            new AMQPMessage(json_encode($payload, JSON_UNESCAPED_UNICODE), $properti),
            self::EXCHANGE,
            $routingKey,
        );

        $ch->close();
        $conn->close();
    }
}