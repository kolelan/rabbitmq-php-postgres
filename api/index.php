<?php
// api/index.php

// Configure error reporting for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

use Shared\Messaging\RabbitMQClient;
use Shared\Database\DatabaseConnection;
use Shared\Logging\LoggerFactory;

class ApiServer
{
    private $rabbitmq;
    private $db;
    private $logger;

    public function __construct()
    {
        // Инициализация логгера
        $this->logger = LoggerFactory::createLogger('php-api');

        $config = [
            'host' => getenv('RABBITMQ_HOST') ?: 'localhost',
            'port' => 5672,
            'user' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
            'vhost' => '/'
        ];

        // Подключение к базе данных
        $dbHost = 'postgres';
        $dbName = getenv('POSTGRES_DB') ?: 'myapp';
        $dbUser = getenv('POSTGRES_USER') ?: 'user';
        $dbPass = getenv('POSTGRES_PASSWORD') ?: 'password';
        $dsn = "pgsql:host={$dbHost};dbname={$dbName}";

        $this->rabbitmq = new RabbitMQClient($config);
        $this->db = new DatabaseConnection($dsn, $dbUser, $dbPass);

        $this->rabbitmq->connect();
        $this->rabbitmq->declareQueue('data_queue');

        $this->logger->info('API server initialized');
    }

    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        $this->logger->info('API request received', [
            'method' => $method,
            'path' => $path
        ]);

        header('Content-Type: application/json');

        try {
            switch ($method) {
                case 'POST':
                    if ($path === '/users') {
                        $this->createUser();
                    } else {
                        $this->sendError(404, 'Not Found');
                    }
                    break;
                case 'GET':
                    if ($path === '/health') {
                        $this->healthCheck();
                    } else {
                        $this->sendError(404, 'Not Found');
                    }
                    break;
                default:
                    $this->sendError(405, 'Method Not Allowed');
            }
        } catch (Exception $e) {
            $this->logger->error('API request failed', [
                'error' => $e->getMessage(),
                'method' => $method,
                'path' => $path
            ]);
            $this->sendError(500, 'Internal Server Error');
        }
    }

    private function createUser(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || empty($input['name'])) {
            $this->sendError(400, 'Name is required');
            return;
        }

        $data = [
            'action' => 'create_user',
            'data' => [
                'name' => $input['name'],
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'created_at' => date('c')
        ];

        // Логируем отправку сообщения
        $messageId = $this->db->logMessage('data_queue', $data, 'sent');

        // Отправляем в очередь
        $this->rabbitmq->publishMessage('data_queue', $data);

        $this->logger->info('User creation request sent to queue', [
            'message_id' => $messageId,
            'user_name' => $input['name']
        ]);

        http_response_code(202);
        echo json_encode([
            'status' => 'accepted',
            'message_id' => $messageId,
            'message' => 'User creation request has been queued'
        ]);
    }

    private function healthCheck(): void
    {
        http_response_code(200);
        echo json_encode([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'service' => 'php-api'
        ]);
    }

    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode([
            'error' => $message,
            'code' => $code
        ]);
    }
}

// Запуск API сервера
try {
    $api = new ApiServer();
    $api->handleRequest();
} catch (Exception $e) {
    $logger = LoggerFactory::createLogger('php-api-bootstrap');
    $logger->error('API server failed to start', [
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal Server Error',
        'code' => 500
    ]);
}
