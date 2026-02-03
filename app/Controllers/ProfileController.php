<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;

/**
 * Profile Controller
 * User public profiles
 */
class ProfileController
{
    private Database $db;
    private Session $session;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
    }

    /**
     * View user profile
     */
    public function view(string $username): Response
    {
        $user = $this->db->fetchOne(
            "SELECT id, name, avatar, created_at FROM users WHERE name = ? AND is_active = 1",
            [$username]
        );

        if (!$user) {
            return Response::html($this->render('errors/404', ['title' => 'Not Found']), 404);
        }

        $videos = $this->db->fetchAll(
            "SELECT * FROM videos 
             WHERE user_id = ? AND visibility = 'public' AND status = 'completed'
             ORDER BY created_at DESC LIMIT 24",
            [$user['id']]
        );

        $stats = [
            'total_videos' => $this->db->count('videos', ['user_id' => $user['id'], 'status' => 'completed']),
            'total_views' => (int) $this->db->fetchOne(
                "SELECT SUM(view_count) as total FROM videos WHERE user_id = ?",
                [$user['id']]
            )['total'] ?? 0,
            'total_likes' => (int) $this->db->fetchOne(
                "SELECT COUNT(*) as total FROM video_likes vl
                 JOIN videos v ON vl.video_id = v.id
                 WHERE v.user_id = ?",
                [$user['id']]
            )['total'] ?? 0,
        ];

        return Response::html($this->render('profile/view', [
            'title' => $user['name'] . ' - Photo2Video',
            'profile_user' => $user,
            'videos' => $videos,
            'stats' => $stats,
            'is_owner' => $this->session->get('user_id') === $user['id'],
        ]));
    }

    /**
     * View user's videos
     */
    public function videos(string $username): Response
    {
        $user = $this->db->fetchOne(
            "SELECT id, name, avatar FROM users WHERE name = ? AND is_active = 1",
            [$username]
        );

        if (!$user) {
            return Response::html($this->render('errors/404', ['title' => 'Not Found']), 404);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 24;
        $offset = ($page - 1) * $perPage;

        $videos = $this->db->fetchAll(
            "SELECT * FROM videos 
             WHERE user_id = ? AND visibility = 'public' AND status = 'completed'
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [$user['id'], $perPage, $offset]
        );

        $total = $this->db->count('videos', ['user_id' => $user['id'], 'status' => 'completed']);
        $totalPages = ceil($total / $perPage);

        return Response::html($this->render('profile/videos', [
            'title' => $user['name'] . "'s Videos - Photo2Video",
            'profile_user' => $user,
            'videos' => $videos,
            'page' => $page,
            'total_pages' => $totalPages,
        ]));
    }

    /**
     * Render view with layout
     */
    private function render(string $template, array $data = []): string
    {
        $viewPath = dirname(__DIR__) . '/View/';
        
        ob_start();
        
        include $viewPath . 'layouts/header.php';
        include $viewPath . $template . '.php';
        include $viewPath . 'layouts/footer.php';
        
        return ob_get_clean();
    }
}
