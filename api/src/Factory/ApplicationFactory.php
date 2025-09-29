<?php
// api/src/App/AppFactory.php

namespace App\Factory;

use Slim\Factory\AppFactory;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Middleware\ErrorMiddleware;
use Shared\Messaging\RabbitMQClient;
use Shared\Database\DatabaseConnection;
use Shared\Logging\LoggerFactory;
use App\Controllers\UserController;
use App\Controllers\MessageController;
use App\Controllers\HealthController;

class ApplicationFactory
{
    public static function create()
    {
        $app = AppFactory::create();

        // Add middleware
        $app->addBodyParsingMiddleware();
        $app->addErrorMiddleware(true, true, true);

        // Initialize services
        $services = self::initializeServices();

        // Register routes
        self::registerRoutes($app, $services);

        return $app;
    }

    private static function initializeServices(): array
    {
        $logger = LoggerFactory::createLogger('php-api');

        // RabbitMQ configuration
        $rabbitConfig = [
            'host' => getenv('RABBITMQ_HOST') ?: 'localhost',
            'port' => 5672,
            'user' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
            'vhost' => '/'
        ];

        // Database configuration
        $dbHost = 'postgres';
        $dbName = getenv('POSTGRES_DB') ?: 'myapp';
        $dbUser = getenv('POSTGRES_USER') ?: 'user';
        $dbPass = getenv('POSTGRES_PASSWORD') ?: 'password';
        $dsn = "pgsql:host={$dbHost};dbname={$dbName}";

        // Initialize RabbitMQ and Database
        $rabbitmq = new RabbitMQClient($rabbitConfig);
        $db = new DatabaseConnection($dsn, $dbUser, $dbPass);

        try {
            $rabbitmq->connect();
            $rabbitmq->declareQueue('data_queue');
            $logger->info('API server initialized successfully');
        } catch (\Exception $e) {
            $logger->error('Failed to initialize API server', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'logger' => $logger,
            'db' => $db,
            'rabbitmq' => $rabbitmq
        ];
    }

    private static function registerRoutes($app, array $services)
    {
        // Initialize controllers
        $userController = new UserController($services['db'], $services['rabbitmq']);
        $messageController = new MessageController($services['db'], $services['rabbitmq']);
        $healthController = new HealthController($services['db']);

        // API documentation
        $app->get('/', [$healthController, 'apiDocumentation']);

        // Health check
        $app->get('/health', [$healthController, 'healthCheck']);

        // User routes
        $app->post('/users', [$userController, 'createUser']);
        $app->get('/users', [$userController, 'getUsers']);

        // Message routes
        $app->post('/messages', [$messageController, 'createMessage']);
        $app->get('/messages', [$messageController, 'getMessages']);
        $app->get('/messages/{id}', [$messageController, 'getMessageById']);
    }
}
