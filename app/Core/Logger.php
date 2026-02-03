<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Logger
 * Simple file-based logger with log levels
 */
class Logger
{
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4,
    ];

    private static ?self $instance = null;
    private string $logPath;
    private string $level;
    private bool $initialized = false;
    private mixed $fileHandle = null;

    private function __construct(string $logPath, string $level = 'debug')
    {
        $this->logPath = rtrim($logPath, '/');
        $this->level = strtolower($level);
        $this->initialize();
    }

    public static function getInstance(string $logPath = null, string $level = null): self
    {
        if (self::$instance === null) {
            $config = Config::getInstance();
            $logPath = $logPath ?? $config->get('LOG_PATH', './storage/logs');
            $level = $level ?? $config->get('LOG_LEVEL', 'debug');
            self::$instance = new self($logPath, $level);
        }
        return self::$instance;
    }

    /**
     * Reset instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null && self::$instance->fileHandle !== null) {
            fclose(self::$instance->fileHandle);
        }
        self::$instance = null;
    }

    /**
     * Initialize log directory and file
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Create log directory if it doesn't exist
        if (!is_dir($this->logPath)) {
            if (!mkdir($this->logPath, 0755, true)) {
                throw new RuntimeException('Cannot create log directory: ' . $this->logPath);
            }
        }

        $logFile = $this->logPath . '/app.log';
        $this->fileHandle = fopen($logFile, 'a');
        
        if ($this->fileHandle === false) {
            throw new RuntimeException('Cannot open log file: ' . $logFile);
        }

        $this->initialized = true;
    }

    /**
     * Log message with level
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);

        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $logMessage = sprintf(
            "[%s] %-8s: %s%s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextStr
        );

        $this->write($logMessage);
    }

    /**
     * Debug level log
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Info level log
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Warning level log
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Error level log
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Critical level log
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Write message to log file
     */
    private function write(string $message): void
    {
        if ($this->fileHandle === null) {
            return;
        }

        fwrite($this->fileHandle, $message);
        
        // Also output to stderr in CLI mode for debugging
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $message);
        }
    }

    /**
     * Check if we should log this level
     */
    private function shouldLog(string $level): bool
    {
        $levelValue = self::LEVELS[$level] ?? 0;
        $minLevelValue = self::LEVELS[$this->level] ?? 0;
        return $levelValue >= $minLevelValue;
    }

    /**
     * Get log file path
     */
    public function getLogPath(): string
    {
        return $this->logPath . '/app.log';
    }

    /**
     * Read last N lines from log
     */
    public function tail(int $lines = 50): array
    {
        $logFile = $this->logPath . '/app.log';
        
        if (!file_exists($logFile)) {
            return [];
        }

        $fileContent = file_get_contents($logFile);
        $linesArr = explode("\n", $fileContent);
        $tail = array_slice($linesArr, -$lines);
        
        return array_filter($tail, fn($line) => !empty(trim($line)));
    }

    /**
     * Clear log file
     */
    public function clear(): void
    {
        if ($this->fileHandle !== null) {
            ftruncate($this->fileHandle, 0);
            rewind($this->fileHandle);
        }
    }

    public function __destruct()
    {
        if ($this->fileHandle !== null) {
            fclose($this->fileHandle);
        }
    }
}
