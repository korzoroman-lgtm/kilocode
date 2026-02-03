<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database Connection Manager
 * Singleton pattern for PDO connection
 */
class Database
{
    private static ?self $instance = null;
    private PDO $connection;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    public static function getInstance(array $config = null): self
    {
        if (self::$instance === null) {
            $config = $config ?? Config::getInstance()->getDatabaseConfig();
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Reset instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Create PDO connection
     */
    private function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4'
        );

        try {
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Execute query and return statement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch single row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert row and return last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update rows
     */
    public function update(string $table, array $data, array $where): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "`{$column}` = ?";
        }
        $set = implode(', ', $setParts);

        $whereParts = [];
        $whereValues = [];
        foreach ($where as $column => $value) {
            $whereParts[] = "`{$column}` = ?";
            $whereValues[] = $value;
        }
        $whereClause = implode(' AND ', $whereParts);

        $sql = "UPDATE `{$table}` SET {$set} WHERE {$whereClause}";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereValues));

        return $stmt->rowCount();
    }

    /**
     * Delete rows
     */
    public function delete(string $table, array $where): int
    {
        $whereParts = [];
        $whereValues = [];
        foreach ($where as $column => $value) {
            $whereParts[] = "`{$column}` = ?";
            $whereValues[] = $value;
        }
        $whereClause = implode(' AND ', $whereParts);

        $sql = "DELETE FROM `{$table}` WHERE {$whereClause}";
        $stmt = $this->query($sql, $whereValues);

        return $stmt->rowCount();
    }

    /**
     * Count rows
     */
    public function count(string $table, array $where = []): int
    {
        if (empty($where)) {
            $sql = "SELECT COUNT(*) FROM `{$table}`";
            return (int) $this->fetchOne($sql)['COUNT(*)'];
        }

        $whereParts = [];
        $whereValues = [];
        foreach ($where as $column => $value) {
            $whereParts[] = "`{$column}` = ?";
            $whereValues[] = $value;
        }
        $whereClause = implode(' AND ', $whereParts);

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$whereClause}";
        return (int) $this->fetchOne($sql, $whereValues)['COUNT(*)'];
    }

    /**
     * Check if row exists
     */
    public function exists(string $table, array $where): bool
    {
        return $this->count($table, $where) > 0;
    }
}
