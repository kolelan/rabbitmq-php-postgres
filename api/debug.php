<?php
// api/debug.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...\n";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "Autoloader loaded\n";
    
    // Test database connection
    try {
        $pdo = new PDO('pgsql:host=postgres;dbname=myapp', 'user', 'password');
        echo "Database connection OK\n";
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n";
    }
    
    // Test RabbitMQ connection
    try {
        $rabbitConfig = [
            'host' => 'rabbitmq',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
            'vhost' => '/'
        ];
        
        $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
            $rabbitConfig['host'],
            $rabbitConfig['port'],
            $rabbitConfig['user'],
            $rabbitConfig['password'],
            $rabbitConfig['vhost']
        );
        echo "RabbitMQ connection OK\n";
        $connection->close();
    } catch (Exception $e) {
        echo "RabbitMQ error: " . $e->getMessage() . "\n";
    }
    
    // Test AppFactory
    try {
        echo "Testing AppFactory...\n";
        
        $app = \App\Factory\ApplicationFactory::create();
        echo "App created successfully\n";
    } catch (Exception $e) {
        echo "AppFactory error: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "Debug complete\n";
