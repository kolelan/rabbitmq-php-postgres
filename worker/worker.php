<?php
// worker/worker.php

// Configure error reporting for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

use Shared\Messaging\RabbitMQClient;
use Shared\Database\DatabaseConnection;
use Shared\Logging\LoggerFactory;
use PhpAmqpLib\Message\AMQPMessage;
use Monolog\Logger;
use RuntimeException;

class DataWorker
{
    private $rabbitmq;
    private $db;
    private $logger;

    public function __construct()
    {
        // Инициализация логгера
        $this->logger = LoggerFactory::createLogger('php-worker');

        // Конфигурация RabbitMQ
        $rabbitConfig = [
            'host' => getenv('RABBITMQ_HOST') ?: 'localhost',
            'port' => 5672,
            'user' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
            'vhost' => '/'
        ];

        // Конфигурация базы данных
        $dbHost = 'postgres';
        $dbName = getenv('POSTGRES_DB') ?: 'myapp';
        $dbUser = getenv('POSTGRES_USER') ?: 'user';
        $dbPass = getenv('POSTGRES_PASSWORD') ?: 'password';

        $dsn = "pgsql:host={$dbHost};dbname={$dbName}";

        $this->rabbitmq = new RabbitMQClient($rabbitConfig);
        $this->db = new DatabaseConnection($dsn, $dbUser, $dbPass);

        $this->logger->info('Worker initialized', [
            'rabbitmq_host' => $rabbitConfig['host'],
            'database_host' => $dbHost
        ]);
    }

    public function start(): void
    {
        try {
            $this->rabbitmq->connect();
            $this->rabbitmq->declareQueue('data_queue');

            $this->logger->info('Worker started and waiting for messages');

            $callback = function (AMQPMessage $msg) {
                $messageId = null;
                $startTime = microtime(true);

                try {
                    $data = json_decode($msg->getBody(), true);

                    $this->logger->info('Message received', [
                        'message_body' => $data,
                        'message_size' => strlen($msg->getBody())
                    ]);

                    // Логируем получение сообщения
                    $messageId = $this->db->logMessage('data_queue', $data, 'processing');

                    // Обработка сообщения
                    $this->processMessage($data);

                    // Обновляем статус на "обработано"
                    $this->db->updateMessageStatus($messageId, 'processed');

                    $processingTime = microtime(true) - $startTime;

                    $this->logger->info('Message processed successfully', [
                        'message_id' => $messageId,
                        'processing_time' => round($processingTime, 3)
                    ]);

                    // Подтверждение обработки
                    $msg->ack();

                } catch (Exception $e) {
                    $processingTime = microtime(true) - $startTime;

                    $this->logger->error('Message processing failed', [
                        'error_message' => $e->getMessage(),
                        'message_id' => $messageId,
                        'processing_time' => round($processingTime, 3),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Логируем ошибку
                    if ($messageId) {
                        $this->db->updateMessageStatus($messageId, 'error', $e->getMessage());
                    } else {
                        $data = json_decode($msg->getBody(), true);
                        $this->db->logMessage('data_queue', $data, 'error', $e->getMessage());
                    }
                }
            };

            $this->rabbitmq->consume('data_queue', $callback);

        } catch (Exception $e) {
            $this->logger->error('Worker connection failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function processMessage(array $data): void
    {
        if ($data['action'] === 'create_user') {
            $name = $data['data']['name'];

            $this->logger->info('Processing user creation', ['name' => $name]);

            $success = $this->db->insertUser($name);

            if (!$success) {
                throw new RuntimeException("Failed to add user: " . $name);
            }

            $this->logger->info('User created successfully', ['name' => $name]);

        } else {
            throw new RuntimeException("Unknown action: " . $data['action']);
        }
    }
}

// Бесконечный цикл для переподключения при ошибках
$attempt = 0;
while (true) {
    try {
        $attempt++;
        $worker = new DataWorker();
        $worker->start();
        $attempt = 0; // Сброс счетчика при успешном запуске
    } catch (Exception $e) {
        $logger = LoggerFactory::createLogger('php-worker-bootstrap');
        $waitTime = min(30, pow(2, $attempt)); // Экспоненциальная задержка

        $logger->warning('Worker restarting after error', [
            'attempt' => $attempt,
            'wait_time' => $waitTime,
            'error' => $e->getMessage()
        ]);

        sleep($waitTime);
    }
}