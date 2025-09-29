<?php
// console/console.php

// Configure error reporting for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

use Shared\Messaging\RabbitMQClient;
use Shared\Database\DatabaseConnection;
use Shared\Logging\LoggerFactory;

class ConsoleProducer
{
    private $rabbitmq;
    private $db;
    private $logger;

    public function __construct()
    {
        // Инициализация логгера
        $this->logger = LoggerFactory::createLogger('php-console');

        $config = [
            'host' => getenv('RABBITMQ_HOST') ?: 'localhost',
            'port' => 5672,
            'user' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
            'vhost' => '/'
        ];

        // Подключение к базе данных для логирования
        $dbHost = 'postgres';
        $dbName = getenv('POSTGRES_DB') ?: 'myapp';
        $dbUser = getenv('POSTGRES_USER') ?: 'user';
        $dbPass = getenv('POSTGRES_PASSWORD') ?: 'password';
        $dsn = "pgsql:host={$dbHost};dbname={$dbName}";

        $this->rabbitmq = new RabbitMQClient($config);
        $this->db = new DatabaseConnection($dsn, $dbUser, $dbPass);

        $this->rabbitmq->connect();
        $this->rabbitmq->declareQueue('data_queue');

        $this->logger->info('Console producer initialized');
    }

    public function run(): void
    {
        $this->logger->info('Console producer started');

        echo "PHP Console Data Producer\n";
        echo "Введите 'quit' для выхода\n\n";

        $messageCount = 0;

        while (true) {
            echo "Введите данные для добавления в БД: ";
            $input = trim(fgets(STDIN));

            if ($input === 'quit') {
                break;
            }

            if (!empty($input)) {
                $messageCount++;

                $data = [
                    'action' => 'create_user',
                    'data' => [
                        'name' => $input,
                        'timestamp' => date('Y-m-d H:i:s')
                    ],
                    'created_at' => date('c')
                ];

                // Логируем отправку сообщения
                $messageId = $this->db->logMessage('data_queue', $data, 'sent');

                // Отправляем в очередь
                $this->rabbitmq->publishMessage('data_queue', $data);

                $this->logger->info('Message sent to queue', [
                    'message_id' => $messageId,
                    'message_count' => $messageCount,
                    'user_input' => $input
                ]);

                echo "Данные отправлены в очередь (ID: {$messageId}): " . $input . "\n";
            }
        }

        $this->rabbitmq->close();

        $this->logger->info('Console producer stopped', [
            'total_messages_sent' => $messageCount
        ]);

        echo "Работа завершена. Отправлено сообщений: {$messageCount}\n";
    }
}

// Запуск приложения
try {
    $producer = new ConsoleProducer();
    $producer->run();
} catch (Exception $e) {
    $logger = LoggerFactory::createLogger('php-console-bootstrap');
    $logger->error('Console producer failed to start', [
        'error' => $e->getMessage()
    ]);
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}