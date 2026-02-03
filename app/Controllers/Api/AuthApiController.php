<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Session;
use App\Core\Response;
use App\Core\Logger;
use App\Telegram\TelegramValidator;

/**
 * Auth API Controller
 * JSON API for authentication
 */
class AuthApiController
{
    private Database $db;
    private Session $session;
    private Logger $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * Register new user
     */
    public function register(): Response
    {
        $data = $_POST ?? [];
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $name = $data['name'] ?? '';

        // Validate
        $errors = $this->validateRegistration($name, $email, $password);
        
        if (!empty($errors)) {
            return Response::error(implode(', ', $errors), 400);
        }

        // Check email exists
        if ($this->db->exists('users', ['email' => $email])) {
            return Response::error('Email already registered', 400);
        }

        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $userId = $this->db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => 'user',
            'credits' => 0,
            'is_active' => 1,
        ]);

        $this->session->regenerate();
        $this->session->set('user_id', $userId);
        $this->session->set('user_role', 'user');

        $this->logger->info('User registered via API', ['user_id' => $userId]);

        return Response::success([
            'user_id' => $userId,
            'email' => $email,
            'name' => $name,
        ], 'Registration successful');
    }

    /**
     * Login user
     */
    public function login(): Response
    {
        $data = $_POST ?? [];
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return Response::error('Email and password required', 400);
        }

        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND is_active = 1",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return Response::error('Invalid credentials', 401);
        }

        $this->session->regenerate();
        $this->session->set('user_id', $user['id']);
        $this->session->set('user_role', $user['role']);

        $this->db->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ], ['id' => $user['id']]);

        return Response::success([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
        ], 'Login successful');
    }

    /**
     * Telegram login
     */
    public function telegramLogin(): Response
    {
        $data = $_POST ?? [];
        $initData = $data['initData'] ?? '';

        if (empty($initData)) {
            return Response::error('Telegram initData required', 400);
        }

        $validator = new TelegramValidator();
        $result = $validator->validateInitData($initData);

        if (!$result['valid']) {
            return Response::error('Invalid Telegram data: ' . $result['error'], 401);
        }

        $telegramUser = $result['user'];
        $telegramId = $telegramUser['id'] ?? null;

        if (!$telegramId) {
            return Response::error('Telegram user ID not found', 400);
        }

        // Find or create user
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE telegram_id = ?",
            [$telegramId]
        );

        if (!$user) {
            // Create new user
            $userId = $this->db->insert('users', [
                'name' => $telegramUser['first_name'] . ' ' . ($telegramUser['last_name'] ?? ''),
                'email' => 'tg_' . $telegramId . '@telegram.local',
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'telegram_id' => $telegramId,
                'telegram_username' => $telegramUser['username'] ?? null,
                'role' => 'user',
                'credits' => 0,
                'is_active' => 1,
            ]);

            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        }

        $this->session->regenerate();
        $this->session->set('user_id', $user['id']);
        $this->session->set('user_role', $user['role']);

        // Update chat ID for notifications
        if (isset($telegramUser['id'])) {
            $this->db->update('users', [
                'telegram_chat_id' => $telegramUser['id'],
            ], ['id' => $user['id']]);
        }

        $this->logger->info('User logged in via Telegram', ['user_id' => $user['id'], 'telegram_id' => $telegramId]);

        return Response::success([
            'user_id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'telegram_linked' => true,
        ], 'Telegram login successful');
    }

    /**
     * Logout
     */
    public function logout(): Response
    {
        $userId = $this->session->get('user_id');
        $this->session->destroy();

        return Response::success(null, 'Logged out successfully');
    }

    /**
     * Refresh token (placeholder)
     */
    public function refresh(): Response
    {
        // In production, implement JWT refresh
        return Response::error('Token refresh not implemented', 400);
    }

    /**
     * Forgot password
     */
    public function forgotPassword(): Response
    {
        $data = $_POST ?? [];
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return Response::error('Email required', 400);
        }

        $user = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ? AND is_active = 1",
            [$email]
        );

        if ($user) {
            // Create reset token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $this->db->insert('password_resets', [
                'user_id' => $user['id'],
                'token' => $token,
                'expires_at' => $expiresAt,
            ]);

            $this->logger->info('Password reset requested', ['user_id' => $user['id']]);
        }

        // Always return success to prevent email enumeration
        return Response::success(null, 'If an account exists, a reset link has been sent');
    }

    /**
     * Reset password
     */
    public function resetPassword(): Response
    {
        $data = $_POST ?? [];
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($token) || empty($password)) {
            return Response::error('Token and password required', 400);
        }

        if (strlen($password) < 8) {
            return Response::error('Password must be at least 8 characters', 400);
        }

        $tokenRecord = $this->db->fetchOne(
            "SELECT user_id FROM password_resets 
             WHERE token = ? AND used_at IS NULL AND expires_at > NOW()",
            [$token]
        );

        if (!$tokenRecord) {
            return Response::error('Invalid or expired token', 400);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $this->db->update('users', [
            'password_hash' => $passwordHash,
        ], ['id' => $tokenRecord['user_id']]);

        $this->db->update('password_resets', [
            'used_at' => date('Y-m-d H:i:s'),
        ], ['token' => $token]);

        return Response::success(null, 'Password reset successful');
    }

    /**
     * Validate registration
     */
    private function validateRegistration(string $name, string $email, string $password): array
    {
        $errors = [];

        if (strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        return $errors;
    }
}
