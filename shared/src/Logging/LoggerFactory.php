<?php
// shared/src/Logging/LoggerFactory.php

namespace Shared\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\MemoryUsageProcessor;

class LoggerFactory
{
    public static function createLogger(string $name, string $level = Logger::INFO): Logger
    {
        $logger = new Logger($name);

        // Добавляем процессоры
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new ProcessIdProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(function ($record) {
            $record['extra']['host'] = gethostname();
            return $record;
        });

        // JSON форматтер для структурированного логирования
        $formatter = new JsonFormatter();

        // Handler для stdout (для Docker логов)
        $streamHandler = new StreamHandler('php://stdout', $level);
        $streamHandler->setFormatter($formatter);
        $logger->pushHandler($streamHandler);

        // Дополнительный handler для файлов (опционально)
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $fileHandler = new StreamHandler($logDir . '/app.log', $level);
        $fileHandler->setFormatter($formatter);
        $logger->pushHandler($fileHandler);

        return $logger;
    }
}