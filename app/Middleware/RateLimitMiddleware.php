<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\Response;
use App\Core\Logger;

/**
 * Rate Limiting Middleware
 * Prevents abuse by limiting request frequency
 */
class RateLimitMiddleware
{
    private Config $config;
    private Database $db;
    private Logger $logger;
    private int $windowSeconds;
    private int $maxAttempts;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
        $this->windowSeconds = (int) $this->config->get('RATE_LIMIT_WINDOW', 60);
        $this->maxAttempts = (int) $this->config->get('RATE_LIMIT_MAX', 10);
    }

    /**
     * Handle rate limiting for a specific key
     * 
     * @param string $key Rate limit key (e.g., 'auth', 'upload')
     */
    public function handle(array $request, callable $next, string $key = 'default'): mixed
    {
        $rateLimitKey = $this->buildKey($key, $request);
        
        // Check if rate limited
        if (!$this->checkRateLimit($rateLimitKey)) {
            $retryAfter = $this->getRetryAfter($rateLimitKey);
            
            $this->logger->warning('Rate limit exceeded', [
                'key' => $rateLimitKey,
                'retry_after' => $retryAfter,
            ]);

            if ($this->isApiRequest($request)) {
                return Response::json([
                    'error' => 'Too Many Requests',
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'retry_after' => $retryAfter,
                ], 429, [
                    'Retry-After' => (string) $retryAfter,
                    'X-RateLimit-Limit' => (string) $this->maxAttempts,
                    'X-RateLimit-Remaining' => '0',
                    'X-RateLimit-Reset' => (string) (time() + $retryAfter),
                ]);
            }

            // For web, show rate limit page
            return Response::html(
                $this->buildRateLimitPage($retryAfter),
                429
            );
        }

        // Record this attempt
        $this->recordAttempt($rateLimitKey);

        return $next($request);
    }

    /**
     * Build rate limit key from request
     */
    private function buildKey(string $type, array $request): string
    {
        $parts = [$type];

        // Use IP address
        $ip = $request['headers']['x-forwarded-for'] ?? 
              $request['headers']['x-real-ip'] ?? 
              $_SERVER['REMOTE_ADDR'] ?? 
              'unknown';
        $parts[] = substr(md5($ip), 0, 8);

        // Add user ID if logged in
        if (isset($request['session']) && $request['session']->has('user_id')) {
            $parts[] = 'u' . $request['session']->get('user_id');
        }

        return implode(':', $parts);
    }

    /**
     * Check if request is within rate limit
     */
    private function checkRateLimit(string $key): bool
    {
        $windowStart = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        
        $record = $this->db->fetchOne(
            "SELECT attempts FROM rate_limits 
             WHERE `key` = ? AND window_start > ?",
            [$key, $windowStart]
        );

        if (!$record) {
            return true;
        }

        return (int) $record['attempts'] < $this->maxAttempts;
    }

    /**
     * Record rate limit attempt
     */
    private function recordAttempt(string $key): void
    {
        $now = date('Y-m-d H:i:s');
        $windowStart = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        
        // Check if record exists for this window
        $existing = $this->db->fetchOne(
            "SELECT id, attempts FROM rate_limits 
             WHERE `key` = ? AND window_start > ?",
            [$key, $windowStart]
        );

        if ($existing) {
            // Update existing record
            $this->db->query(
                "UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?",
                [$existing['id']]
            );
        } else {
            // Create new record
            $this->db->query(
                "INSERT INTO rate_limits (`key`, attempts, window_start) VALUES (?, 1, ?)",
                [$key, $now]
            );
        }
    }

    /**
     * Get seconds until rate limit resets
     */
    private function getRetryAfter(string $key): int
    {
        $windowStart = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        
        $record = $this->db->fetchOne(
            "SELECT window_start FROM rate_limits 
             WHERE `key` = ? AND window_start > ?",
            [$key, $windowStart]
        );

        if (!$record) {
            return $this->windowSeconds;
        }

        $windowEnd = strtotime($record['window_start']) + $this->windowSeconds;
        return max(1, $windowEnd - time());
    }

    /**
     * Check if request is API
     */
    private function isApiRequest(array $request): bool
    {
        return str_starts_with($request['uri'] ?? '', '/api');
    }

    /**
     * Build rate limit HTML page
     */
    private function buildRateLimitPage(int $retryAfter): string
    {
        $seconds = $retryAfter;
        $minutes = ceil($seconds / 60);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Too Many Requests</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; }
        .container { padding: 2rem; }
        h1 { font-size: 3rem; color: #ff6b6b; margin: 0 0 1rem; }
        p { font-size: 1.2rem; color: #aaa; }
        .timer { font-size: 2rem; color: #4ecdc4; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>429</h1>
        <p>Too Many Requests</p>
        <p>Please wait {$minutes} minute(s) before trying again.</p>
    </div>
</body>
</html>
HTML;
    }
}
