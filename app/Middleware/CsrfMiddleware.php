<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Session;
use App\Core\Response;

/**
 * CSRF Protection Middleware
 * Validates CSRF tokens for POST/PUT/DELETE requests
 */
class CsrfMiddleware
{
    private const TOKEN_HEADER = 'X-CSRF-Token';
    private const TOKEN_PARAM = 'csrf_token';

    /**
     * Validate CSRF token
     */
    public function handle(array $request, callable $next): mixed
    {
        // Skip for GET, HEAD, OPTIONS requests
        $method = $request['method'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        $session = Session::getInstance();
        $token = $this->getTokenFromRequest($request);

        if (!$session->validateCsrfToken($token)) {
            if ($this->isApiRequest($request)) {
                return Response::error('Invalid CSRF token', 419);
            }

            // For web, set flash message and redirect back
            $session->flash('error', 'Security token expired. Please try again.');
            return Response::redirect($request['uri'] ?? '/', 302);
        }

        // Regenerate token after validation (optional, for extra security)
        // $session->generateCsrfToken();

        return $next($request);
    }

    /**
     * Get token from request (header, body, or query)
     */
    private function getTokenFromRequest(array $request): ?string
    {
        // Check header first
        $headers = $request['headers'] ?? [];
        if (isset($headers[self::TOKEN_HEADER])) {
            return $headers[self::TOKEN_HEADER];
        }

        // Check request body
        $body = $request['body'] ?? [];
        if (isset($body[self::TOKEN_PARAM])) {
            return $body[self::TOKEN_PARAM];
        }

        // Check query parameters
        $query = $request['query'] ?? [];
        if (isset($query[self::TOKEN_PARAM])) {
            return $query[self::TOKEN_PARAM];
        }

        return null;
    }

    /**
     * Check if request is API
     */
    private function isApiRequest(array $request): bool
    {
        return str_starts_with($request['uri'] ?? '', '/api');
    }
}
