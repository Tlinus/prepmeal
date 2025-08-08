<?php

declare(strict_types=1);

namespace PrepMeal\Core\Database;

use PDO;
use PDOException;
use Monolog\Logger;

class DatabaseConnection
{
    private PDO $connection;
    private Logger $logger;

    public function __construct(
        string $host,
        string $database,
        string $username,
        string $password,
        Logger $logger = null
    ) {
        $this->logger = $logger;
        
        try {
            $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            if ($this->logger) {
                $this->logger->info('Database connection established');
            }
        } catch (PDOException $e) {
            if ($this->logger) {
                $this->logger->error('Database connection failed: ' . $e->getMessage());
            }
            throw new PDOException('Database connection failed: ' . $e->getMessage());
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }
}
