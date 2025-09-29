<?php
// api/src/Controllers/UserController.php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shared\Database\DatabaseConnection;
use Shared\Messaging\RabbitMQClient;
use Shared\Logging\LoggerFactory;

class UserController
{
    private $db;
    private $rabbitmq;
    private $logger;

    public function __construct(DatabaseConnection $db, RabbitMQClient $rabbitmq)
    {
        $this->db = $db;
        $this->rabbitmq = $rabbitmq;
        $this->logger = LoggerFactory::createLogger('user-controller');
    }

    public function createUser(Request $request, Response $response): Response
    {
        try {
            $input = $request->getParsedBody();
            
            if (!isset($input['name']) || empty($input['name'])) {
                $this->logger->warning('User creation failed: missing name', ['input' => $input]);
                
                $data = [
                    'error' => 'Name is required',
                    'code' => 400
                ];
                
                $response->getBody()->write(json_encode($data));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $messageData = [
                'action' => 'create_user',
                'data' => [
                    'name' => $input['name'],
                    'timestamp' => date('Y-m-d H:i:s')
                ],
                'created_at' => date('c')
            ];

            // Log message to database
            $messageId = $this->db->logMessage('data_queue', $messageData, 'sent');

            // Publish message to queue
            $this->rabbitmq->publishMessage('data_queue', $messageData);

            $this->logger->info('User creation request sent to queue', [
                'message_id' => $messageId,
                'user_name' => $input['name']
            ]);

            $data = [
                'status' => 'accepted',
                'message_id' => $messageId,
                'message' => 'User creation request has been queued'
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withStatus(202)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('User creation failed', [
                'error' => $e->getMessage(),
                'input' => $input ?? null
            ]);
            
            $data = [
                'error' => 'Internal Server Error',
                'code' => 500
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getUsers(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->query('SELECT id, name, created_at FROM users ORDER BY created_at DESC LIMIT 100');
            $users = $stmt->fetchAll();
            
            $this->logger->info('Users list requested', ['count' => count($users)]);
            
            $data = [
                'users' => $users,
                'count' => count($users)
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch users', ['error' => $e->getMessage()]);
            
            $data = [
                'error' => 'Internal Server Error',
                'code' => 500
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
