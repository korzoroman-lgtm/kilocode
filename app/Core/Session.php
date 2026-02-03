<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session Manager
 * Secure session handling with CSRF protection
 */
class Session
{
    private static ?self $instance = null;

    private function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = (int) Config::getInstance()->get('SESSION_LIFETIME', 3600);
            ini_set('session.gc_maxlifetime', (string) $lifetime);
            ini_set('session.cookie_lifetime', (string) $lifetime);
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '0'); // Set to '1' in production with HTTPS
            ini_set('session.use_strict_mode', '1');
            
            session_start();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set session value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Regenerate session ID
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Get current session ID
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Destroy session
     */
    public function destroy(): void
    {
        $this->clear();
        session_destroy();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Flash message - set message that will be shown once
     */
    public function flash(string $key, ?string $message = null): ?string
    {
        if ($message === null) {
            $value = $this->get('_flash_' . $key);
            $this->remove('_flash_' . $key);
            return $value;
        }
        $this->set('_flash_' . $key, $message);
        return null;
    }

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken(): string
    {
        if (!$this->has('_csrf_token')) {
            $token = bin2hex(random_bytes(32));
            $this->set('_csrf_token', $token);
        }
        return $this->get('_csrf_token');
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(?string $token): bool
    {
        if ($token === null) {
            return false;
        }
        return hash_equals($this->get('_csrf_token') ?? '', $token);
    }

    /**
     * Get CSRF token for forms
     */
    public function getCsrfToken(): string
    {
        return $this->generateCsrfToken();
    }

    /**
     * Store rate limit data
     */
    public function rateLimit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $rateLimitKey = '_rate_limit_' . $key;
        $now = time();
        
        $attempts = $this->get($rateLimitKey, []);
        
        // Remove old attempts outside the window
        $attempts = array_filter($attempts, fn($timestamp) => ($now - $timestamp) < $windowSeconds);
        
        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        // Add new attempt
        $attempts[] = $now;
        $this->set($rateLimitKey, $attempts);
        
        return true;
    }

    /**
     * Get remaining attempts for rate limit
     */
    public function getRemainingAttempts(string $key, int $maxAttempts, int $windowSeconds): int
    {
        $rateLimitKey = '_rate_limit_' . $key;
        $now = time();
        
        $attempts = $this->get($rateLimitKey, []);
        $attempts = array_filter($attempts, fn($timestamp) => ($now - $timestamp) < $windowSeconds);
        
        return max(0, $maxAttempts - count($attempts));
    }
}
