<?php
// shared/src/Database/DatabaseConnection.php

namespace Shared\Database;

use PDO;
use PDOException;

class DatabaseConnection
{
    private $pdo;

    public function __construct(string $dsn, string $username, string $password)
    {
        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    public function insertUser(string $name): bool
    {
        $sql = "INSERT INTO users (name, created_at) VALUES (:name, NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['name' => $name]);
    }

    public function logMessage(string $queueName, array $messageData, string $status = 'pending', string $errorMessage = null): int
    {
        $sql = "INSERT INTO messages (queue_name, message_data, status, created_at, error_message) 
                VALUES (:queue_name, :message_data, :status, NOW(), :error_message) 
                RETURNING id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'queue_name' => $queueName,
            'message_data' => json_encode($messageData),
            'status' => $status,
            'error_message' => $errorMessage
        ]);

        return $stmt->fetch()['id'];
    }

    public function updateMessageStatus(int $messageId, string $status, string $errorMessage = null): bool
    {
        $sql = "UPDATE messages 
                SET status = :status, 
                    processed_at = CASE WHEN :status = 'processed' THEN NOW() ELSE NULL END,
                    error_message = :error_message 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'status' => $status,
            'error_message' => $errorMessage,
            'id' => $messageId
        ]);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}