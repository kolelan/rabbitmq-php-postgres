<?php
// api/src/Controllers/HealthController.php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shared\Database\DatabaseConnection;
use Shared\Logging\LoggerFactory;

class HealthController
{
    private $db;
    private $logger;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
        $this->logger = LoggerFactory::createLogger('health-controller');
    }

    public function healthCheck(Request $request, Response $response): Response
    {
        try {
            // Check database connection
            $pdo = $this->db->getPdo();
            $pdo->query('SELECT 1');
            
            $this->logger->info('Health check requested');
            
            $data = [
                'status' => 'healthy',
                'timestamp' => date('c'),
                'service' => 'php-api',
                'database' => 'connected',
                'version' => '1.0.0'
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Health check failed', ['error' => $e->getMessage()]);
            
            $data = [
                'status' => 'unhealthy',
                'timestamp' => date('c'),
                'service' => 'php-api',
                'error' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function apiDocumentation(Request $request, Response $response): Response
    {
        $this->logger->info('API documentation requested');
        
        $data = [
            'service' => 'RabbitMQ PHP PostgreSQL API',
            'version' => '1.0.0',
            'framework' => 'Slim Framework 4',
            'endpoints' => [
                'GET /' => 'API documentation',
                'GET /health' => 'Health check',
                'POST /users' => 'Create a new user (queued)',
                'GET /users' => 'List all users',
                'POST /messages' => 'Create a custom message (queued)',
                'GET /messages' => 'List all messages',
                'GET /messages/{id}' => 'Get message by ID'
            ],
            'examples' => [
                'create_user' => [
                    'method' => 'POST',
                    'url' => '/users',
                    'body' => ['name' => 'John Doe']
                ],
                'create_message' => [
                    'method' => 'POST',
                    'url' => '/messages',
                    'body' => [
                        'action' => 'send_email',
                        'data' => [
                            'to' => 'user@example.com',
                            'subject' => 'Welcome',
                            'body' => 'Welcome to our service!'
                        ],
                        'queue' => 'email_queue',
                        'priority' => 'high'
                    ]
                ],
                'get_users' => [
                    'method' => 'GET',
                    'url' => '/users'
                ],
                'get_messages' => [
                    'method' => 'GET',
                    'url' => '/messages'
                ]
            ]
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
