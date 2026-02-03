<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\Response;
use App\Core\Logger;
use App\Core\Config;

/**
 * Authentication Controller
 * Handles user registration, login, logout, and password management
 */
class AuthController
{
    private Database $db;
    private Session $session;
    private Logger $logger;
    private Config $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
        $this->logger = Logger::getInstance();
        $this->config = Config::getInstance();
    }

    /**
     * Show login form
     */
    public function showLogin(): Response
    {
        if ($this->session->has('user_id')) {
            return Response::redirect('/dashboard');
        }

        return Response::html($this->render('auth/login', [
            'title' => 'Login',
            'csrf_token' => $this->session->getCsrfToken(),
        ]));
    }

    /**
     * Process login request
     */
    public function login(): Response
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // Validate input
        if (empty($email) || empty($password)) {
            $this->session->flash('error', 'Please enter email and password');
            return Response::redirect('/login');
        }

        // Find user
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND is_active = 1",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logger->warning('Failed login attempt', ['email' => $email]);
            $this->session->flash('error', 'Invalid email or password');
            return Response::redirect('/login');
        }

        // Regenerate session for security
        $this->session->regenerate();

        // Set session data
        $this->session->set('user_id', $user['id']);
        $this->session->set('user_role', $user['role']);
        $this->session->set('last_activity', time());

        // Update last login
        $this->db->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ], ['id' => $user['id']]);

        $this->logger->info('User logged in', ['user_id' => $user['id']]);

        // Redirect
        $redirect = $_GET['redirect'] ?? '/dashboard';
        return Response::redirect($redirect);
    }

    /**
     * Show registration form
     */
    public function showRegister(): Response
    {
        if ($this->session->has('user_id')) {
            return Response::redirect('/dashboard');
        }

        return Response::html($this->render('auth/register', [
            'title' => 'Create Account',
            'csrf_token' => $this->session->getCsrfToken(),
        ]));
    }

    /**
     * Process registration
     */
    public function register(): Response
    {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Validate
        $errors = $this->validateRegistration($name, $email, $password, $passwordConfirm);
        
        if (!empty($errors)) {
            $this->session->flash('error', implode('<br>', $errors));
            return Response::redirect('/register');
        }

        // Check if email exists
        if ($this->db->exists('users', ['email' => $email])) {
            $this->session->flash('error', 'Email already registered');
            return Response::redirect('/register');
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
            'email_verified' => 0,
        ]);

        // Log user in
        $this->session->regenerate();
        $this->session->set('user_id', $userId);
        $this->session->set('user_role', 'user');

        $this->logger->info('New user registered', ['user_id' => $userId, 'email' => $email]);

        // Send verification email (placeholder)
        $this->sendVerificationEmail($userId, $email);

        $this->session->flash('success', 'Welcome! Your account has been created.');
        return Response::redirect('/dashboard');
    }

    /**
     * Logout user
     */
    public function logout(): Response
    {
        $userId = $this->session->get('user_id');
        
        $this->session->destroy();
        
        $this->logger->info('User logged out', ['user_id' => $userId]);

        return Response::redirect('/');
    }

    /**
     * Verify email
     */
    public function verifyEmail(string $token): Response
    {
        // Find token
        $tokenRecord = $this->db->fetchOne(
            "SELECT user_id FROM email_verifications WHERE token = ? AND expires_at > NOW()",
            [$token]
        );

        if (!$tokenRecord) {
            $this->session->flash('error', 'Invalid or expired verification link');
            return Response::redirect('/');
        }

        // Update user
        $this->db->update('users', [
            'email_verified' => 1,
        ], ['id' => $tokenRecord['user_id']]);

        // Delete token
        $this->db->delete('email_verifications', ['token' => $token]);

        $this->session->flash('success', 'Email verified successfully!');
        return Response::redirect('/dashboard');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(): Response
    {
        return Response::html($this->render('auth/forgot-password', [
            'title' => 'Forgot Password',
            'csrf_token' => $this->session->getCsrfToken(),
        ]));
    }

    /**
     * Process forgot password request
     */
    public function forgotPassword(): Response
    {
        $email = $_POST['email'] ?? '';

        if (empty($email)) {
            $this->session->flash('error', 'Please enter your email');
            return Response::redirect('/forgot-password');
        }

        // Find user
        $user = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ? AND is_active = 1",
            [$email]
        );

        if (!$user) {
            // Don't reveal if email exists
            $this->session->flash('success', 'If an account exists, a reset link has been sent.');
            return Response::redirect('/login');
        }

        // Create reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $this->db->insert('password_resets', [
            'user_id' => $user['id'],
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        // Send email (placeholder)
        $resetUrl = $this->config->get('APP_URL') . '/reset-password/' . $token;
        $this->logger->info('Password reset requested', ['user_id' => $user['id'], 'token' => $token]);

        $this->session->flash('success', 'If an account exists, a reset link has been sent.');
        return Response::redirect('/login');
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(string $token): Response
    {
        // Validate token
        $tokenRecord = $this->db->fetchOne(
            "SELECT user_id FROM password_resets 
             WHERE token = ? AND used_at IS NULL AND expires_at > NOW()",
            [$token]
        );

        if (!$tokenRecord) {
            $this->session->flash('error', 'Invalid or expired reset link');
            return Response::redirect('/forgot-password');
        }

        return Response::html($this->render('auth/reset-password', [
            'title' => 'Reset Password',
            'token' => $token,
            'csrf_token' => $this->session->getCsrfToken(),
        ]));
    }

    /**
     * Process password reset
     */
    public function resetPassword(string $token): Response
    {
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($password !== $passwordConfirm) {
            $this->session->flash('error', 'Passwords do not match');
            return Response::redirect('/reset-password/' . $token);
        }

        if (strlen($password) < 8) {
            $this->session->flash('error', 'Password must be at least 8 characters');
            return Response::redirect('/reset-password/' . $token);
        }

        // Get token record
        $tokenRecord = $this->db->fetchOne(
            "SELECT user_id FROM password_resets 
             WHERE token = ? AND used_at IS NULL AND expires_at > NOW()",
            [$token]
        );

        if (!$tokenRecord) {
            $this->session->flash('error', 'Invalid or expired reset link');
            return Response::redirect('/forgot-password');
        }

        // Update password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $this->db->update('users', [
            'password_hash' => $passwordHash,
        ], ['id' => $tokenRecord['user_id']]);

        // Mark token as used
        $this->db->update('password_resets', [
            'used_at' => date('Y-m-d H:i:s'),
        ], ['token' => $token]);

        $this->session->flash('success', 'Password reset successfully! Please login.');
        return Response::redirect('/login');
    }

    /**
     * Validate registration data
     */
    private function validateRegistration(string $name, string $email, string $password, string $passwordConfirm): array
    {
        $errors = [];

        if (strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match';
        }

        return $errors;
    }

    /**
     * Send verification email (placeholder)
     */
    private function sendVerificationEmail(int $userId, string $email): void
    {
        // In production, implement email sending
        $this->logger->info('Verification email placeholder', [
            'user_id' => $userId,
            'email' => $email,
        ]);
    }

    /**
     * Render view with layout
     */
    private function render(string $template, array $data = []): string
    {
        $viewPath = dirname(__DIR__) . '/View/';
        
        // Start output buffering
        ob_start();
        
        // Include header
        include $viewPath . 'layouts/header.php';
        
        // Include template
        include $viewPath . $template . '.php';
        
        // Include footer
        include $viewPath . 'layouts/footer.php';
        
        return ob_get_clean();
    }
}
