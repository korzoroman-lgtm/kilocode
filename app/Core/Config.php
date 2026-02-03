<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Configuration Manager
 * Loads configuration from .env file and provides access to config values
 */
class Config
{
    private static ?self $instance = null;
    private array $config = [];
    private string $envPath;

    private function __construct(string $envPath = null)
    {
        $this->envPath = $envPath ?? dirname(__DIR__, 2) . '/.env';
        $this->loadConfig();
    }

    public static function getInstance(string $envPath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($envPath);
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
     * Load configuration from .env file
     */
    private function loadConfig(): void
    {
        if (!file_exists($this->envPath)) {
            throw new RuntimeException('.env file not found at: ' . $this->envPath);
        }

        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Parse key=value pairs
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
                    $value = $matches[1];
                }

                $this->config[$key] = $value;
            }
        }

        // Set default timezone
        date_default_timezone_set($this->get('APP_TIMEZONE', 'UTC'));
    }

    /**
     * Get configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get all configuration values
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Get database configuration
     */
    public function getDatabaseConfig(): array
    {
        return [
            'host' => $this->get('DB_HOST', 'localhost'),
            'port' => $this->get('DB_PORT', '3306'),
            'database' => $this->get('DB_DATABASE', ''),
            'username' => $this->get('DB_USERNAME', ''),
            'password' => $this->get('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ];
    }

    /**
     * Get DSN string for PDO
     */
    public function getDsn(): string
    {
        $config = $this->getDatabaseConfig();
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
    }

    /**
     * Get application environment
     */
    public function isDebug(): bool
    {
        return $this->get('APP_DEBUG', 'false') === 'true';
    }

    /**
     * Get application environment (development/production)
     */
    public function getEnvironment(): string
    {
        return $this->get('APP_ENV', 'production');
    }
}
