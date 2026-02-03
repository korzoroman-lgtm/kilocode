<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;

/**
 * User API Controller
 * JSON API for user management
 */
class UserApiController
{
    private Database $db;
    private Session $session;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
    }

    /**
     * Get current user
     */
    public function me(): Response
    {
        $userId = $this->session->get('user_id');

        $user = $this->db->fetchOne(
            "SELECT id, email, name, avatar, role, credits, telegram_id, 
                    telegram_notifications, created_at
             FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            return Response::error('User not found', 404);
        }

        return Response::success(['user' => $user]);
    }

    /**
     * Update current user
     */
    public function updateMe(): Response
    {
        $userId = $this->session->get('user_id');
        $data = $_POST ?? [];

        $updates = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (strlen($name) < 2) {
                return Response::error('Name must be at least 2 characters', 400);
            }
            $updates['name'] = $name;
        }

        if (isset($data['telegram_notifications'])) {
            $updates['telegram_notifications'] = (bool) $data['telegram_notifications'];
        }

        if (!empty($updates)) {
            $this->db->update('users', $updates, ['id' => $userId]);
        }

        return Response::success(['updated' => true], 'Profile updated');
    }

    /**
     * Get user credits
     */
    public function credits(): Response
    {
        $userId = $this->session->get('user_id');

        $user = $this->db->fetchOne(
            "SELECT credits FROM users WHERE id = ?",
            [$userId]
        );

        return Response::success([
            'credits' => $user['credits'] ?? 0,
        ]);
    }

    /**
     * Public credits check (for quick display)
     */
    public function publicCredits(): Response
    {
        // Check if user is logged in via session
        if (!$this->session->has('user_id')) {
            return Response::success(['credits' => 0, 'logged_in' => false]);
        }

        $userId = $this->session->get('user_id');
        $user = $this->db->fetchOne(
            "SELECT credits FROM users WHERE id = ?",
            [$userId]
        );

        return Response::success([
            'credits' => $user['credits'] ?? 0,
            'logged_in' => true,
        ]);
    }

    /**
     * View public user profile
     */
    public function view(int $id): Response
    {
        $user = $this->db->fetchOne(
            "SELECT id, name, avatar, created_at FROM users WHERE id = ? AND is_active = 1",
            [$id]
        );

        if (!$user) {
            return Response::error('User not found', 404);
        }

        return Response::success(['user' => $user]);
    }

    /**
     * Get user's public videos
     */
    public function userVideos(int $id): Response
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $videos = $this->db->fetchAll(
            "SELECT id, title, thumbnail, format, view_count, like_count, created_at
             FROM videos 
             WHERE user_id = ? AND visibility = 'public' AND status = 'completed'
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [$id, $perPage, $offset]
        );

        $total = $this->db->count('videos', [
            'user_id' => $id,
            'visibility' => 'public',
            'status' => 'completed',
        ]);

        return Response::success([
            'videos' => $videos,
            'page' => $page,
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    /**
     * API status check
     */
    public function status(): Response
    {
        return Response::success([
            'api' => 'photo2video',
            'version' => '1.0.0',
            'status' => 'operational',
            'timestamp' => date('c'),
        ]);
    }
}
