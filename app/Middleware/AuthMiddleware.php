<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Session;
use App\Core\Response;

/**
 * Authentication Middleware
 * Protects routes requiring authentication
 */
class AuthMiddleware
{
    /**
     * Check if user is authenticated
     */
    public function handle(array $request, callable $next): mixed
    {
        $session = Session::getInstance();
        
        // Check if user is logged in
        if (!$session->has('user_id')) {
            // Check for API request
            if ($this->isApiRequest($request)) {
                return Response::error('Unauthorized. Please login.', 401);
            }
            
            // Redirect to login page
            $loginUrl = '/login?redirect=' . urlencode($request['uri'] ?? '/');
            return Response::redirect($loginUrl, 302);
        }

        // Get user from database
        $userId = $session->get('user_id');
        $user = $this->getUser($userId);

        if (!$user) {
            // User no longer exists, logout and redirect
            $session->destroy();
            
            if ($this->isApiRequest($request)) {
                return Response::error('User not found', 401);
            }
            
            return Response::redirect('/login');
        }

        // Check if user is active
        if (!$user['is_active']) {
            $session->destroy();
            
            if ($this->isApiRequest($request)) {
                return Response::error('Account is disabled', 401);
            }
            
            return Response::redirect('/login');
        }

        // Add user to request
        $request['user'] = $user;

        return $next($request);
    }

    /**
     * Check if user is admin
     */
    public function adminOnly(array $request, callable $next): mixed
    {
        // First check auth
        $result = $this->handle($request, fn($r) => $r);
        
        if ($result instanceof Response) {
            return $result;
        }

        // Check admin role
        $user = $request['user'] ?? null;
        
        if ($user === null || $user['role'] !== 'admin') {
            if ($this->isApiRequest($request)) {
                return Response::error('Forbidden. Admin access required.', 403);
            }
            
            return Response::redirect('/dashboard');
        }

        // Continue with next middleware
        $nextRequest = $request;
        return $next($nextRequest);
    }

    /**
     * Check if request is API
     */
    private function isApiRequest(array $request): bool
    {
        return str_starts_with($request['uri'] ?? '', '/api');
    }

    /**
     * Get user from database
     */
    private function getUser(int $userId): ?array
    {
        $db = Database::getInstance();
        
        return $db->fetchOne(
            "SELECT id, email, name, avatar, role, credits, is_active, telegram_id 
             FROM users WHERE id = ?",
            [$userId]
        );
    }
}
