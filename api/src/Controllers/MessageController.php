<?php
// api/src/Controllers/MessageController.php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shared\Database\DatabaseConnection;
use Shared\Messaging\RabbitMQClient;
use Shared\Logging\LoggerFactory;

class MessageController
{
    private $db;
    private $rabbitmq;
    private $logger;

    public function __construct(DatabaseConnection $db, RabbitMQClient $rabbitmq)
    {
        $this->db = $db;
        $this->rabbitmq = $rabbitmq;
        $this->logger = LoggerFactory::createLogger('message-controller');
    }

    public function createMessage(Request $request, Response $response): Response
    {
        try {
            $input = $request->getParsedBody();
            
            // Validate required fields
            if (!isset($input['action']) || empty($input['action'])) {
                $this->logger->warning('Message creation failed: missing action', ['input' => $input]);
                
                $data = [
                    'error' => 'Action is required',
                    'code' => 400
                ];
                
                $response->getBody()->write(json_encode($data));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if (!isset($input['data']) || !is_array($input['data'])) {
                $this->logger->warning('Message creation failed: missing or invalid data', ['input' => $input]);
                
                $data = [
                    'error' => 'Data field is required and must be an object',
                    'code' => 400
                ];
                
                $response->getBody()->write(json_encode($data));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Get queue name (optional, defaults to 'data_queue')
            $queueName = $input['queue'] ?? 'data_queue';

            $messageData = [
                'action' => $input['action'],
                'data' => $input['data'],
                'created_at' => date('c'),
                'source' => 'api'
            ];

            // Add optional fields
            if (isset($input['priority'])) {
                $messageData['priority'] = $input['priority'];
            }
            if (isset($input['metadata'])) {
                $messageData['metadata'] = $input['metadata'];
            }

            // Log message to database
            $messageId = $this->db->logMessage($queueName, $messageData, 'sent');

            // Publish message to queue
            $this->rabbitmq->publishMessage($queueName, $messageData);

            $this->logger->info('Message created and sent to queue', [
                'message_id' => $messageId,
                'action' => $input['action'],
                'queue' => $queueName
            ]);

            $data = [
                'status' => 'accepted',
                'message_id' => $messageId,
                'queue' => $queueName,
                'action' => $input['action'],
                'message' => 'Message has been queued for processing'
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withStatus(202)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Message creation failed', [
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

    public function getMessages(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->query('SELECT id, queue_name, message_data, status, created_at, processed_at, error_message FROM messages ORDER BY created_at DESC LIMIT 100');
            $messages = $stmt->fetchAll();
            
            $this->logger->info('Messages list requested', ['count' => count($messages)]);
            
            $data = [
                'messages' => $messages,
                'count' => count($messages)
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch messages', ['error' => $e->getMessage()]);
            
            $data = [
                'error' => 'Internal Server Error',
                'code' => 500
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getMessageById(Request $request, Response $response, array $args): Response
    {
        try {
            $messageId = $args['id'] ?? null;
            
            if (!$messageId || !is_numeric($messageId)) {
                $data = [
                    'error' => 'Invalid message ID',
                    'code' => 400
                ];
                
                $response->getBody()->write(json_encode($data));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare('SELECT id, queue_name, message_data, status, created_at, processed_at, error_message FROM messages WHERE id = ?');
            $stmt->execute([$messageId]);
            $message = $stmt->fetch();
            
            if (!$message) {
                $data = [
                    'error' => 'Message not found',
                    'code' => 404
                ];
                
                $response->getBody()->write(json_encode($data));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $this->logger->info('Message details requested', ['message_id' => $messageId]);
            
            $response->getBody()->write(json_encode($message));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch message', [
                'error' => $e->getMessage(),
                'message_id' => $messageId ?? null
            ]);
            
            $data = [
                'error' => 'Internal Server Error',
                'code' => 500
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}