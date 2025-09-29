<?php
// shared/src/Messaging/RabbitMQClient.php

namespace Shared\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQClient
{
    private $connection;
    private $channel;
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['user'],
            $this->config['password'],
            $this->config['vhost'] ?? '/'
        );

        $this->channel = $this->connection->channel();
    }

    public function declareQueue(string $queueName): void
    {
        $this->channel->queue_declare(
            $queueName,
            false,
            true,  // durable
            false,
            false
        );
    }

    public function publishMessage(string $queueName, array $data): void
    {
        $message = new AMQPMessage(
            json_encode($data),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );

        $this->channel->basic_publish($message, '', $queueName);
    }

    public function consume(string $queueName, callable $callback): void
    {
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume(
            $queueName,
            '',
            false,
            false,
            false,
            false,
            $callback
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function close(): void
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
}