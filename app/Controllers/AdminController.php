<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Logger;

/**
 * Admin Controller
 * Admin panel for managing users, videos, reports, and payments
 */
class AdminController
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
     * Admin dashboard
     */
    public function index(): Response
    {
        $stats = [
            'total_users' => $this->db->count('users'),
            'total_videos' => $this->db->count('videos'),
            'pending_reports' => $this->db->count('video_reports', ['status' => 'pending']),
            'pending_payments' => $this->db->count('payments', ['status' => 'pending']),
            'total_generations_today' => $this->db->count('generation_jobs', [
                'status' => 'succeeded',
            ]),
        ];

        // Recent activity
        $recentUsers = $this->db->fetchAll(
            "SELECT id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5"
        );

        $recentVideos = $this->db->fetchAll(
            "SELECT v.*, u.name as author_name 
             FROM videos v 
             JOIN users u ON v.user_id = u.id 
             ORDER BY v.created_at DESC LIMIT 5"
        );

        return Response::html($this->render('admin/index', [
            'title' => 'Admin Dashboard',
            'stats' => $stats,
            'recent_users' => $recentUsers,
            'recent_videos' => $recentVideos,
        ]));
    }

    /**
     * Users management
     */
    public function users(): Response
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $search = $_GET['search'] ?? '';

        $where = "1=1";
        $params = [];

        if (!empty($search)) {
            $where .= " AND (name LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM users WHERE {$where}",
            $params
        )['COUNT(*)'];

        $users = $this->db->fetchAll(
            "SELECT *, (SELECT COUNT(*) FROM videos WHERE user_id = users.id) as video_count
             FROM users WHERE {$where}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return Response::html($this->render('admin/users', [
            'title' => 'Users - Admin',
            'users' => $users,
            'page' => $page,
            'total_pages' => ceil($total / $perPage),
            'search' => $search,
        ]));
    }

    /**
     * View user details
     */
    public function viewUser(int $id): Response
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);

        if (!$user) {
            return Response::html($this->render('errors/404', ['title' => 'Not Found']), 404);
        }

        $videos = $this->db->fetchAll(
            "SELECT * FROM videos WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
            [$id]
        );

        $creditHistory = $this->db->fetchAll(
            "SELECT * FROM credit_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
            [$id]
        );

        return Response::html($this->render('admin/view-user', [
            'title' => $user['name'] . ' - Admin',
            'user' => $user,
            'videos' => $videos,
            'credit_history' => $creditHistory,
        ]));
    }

    /**
     * Videos management
     */
    public function videos(): Response
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $status = $_GET['status'] ?? '';
        $visibility = $_GET['visibility'] ?? '';

        $where = "1=1";
        $params = [];

        if (!empty($status)) {
            $where .= " AND status = ?";
            $params[] = $status;
        }

        if (!empty($visibility)) {
            $where .= " AND visibility = ?";
            $params[] = $visibility;
        }

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM videos WHERE {$where}",
            $params
        )['COUNT(*)'];

        $videos = $this->db->fetchAll(
            "SELECT v.*, u.name as author_name 
             FROM videos v 
             JOIN users u ON v.user_id = u.id 
             WHERE {$where}
             ORDER BY v.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return Response::html($this->render('admin/videos', [
            'title' => 'Videos - Admin',
            'videos' => $videos,
            'page' => $page,
            'total_pages' => ceil($total / $perPage),
            'filters' => ['status' => $status, 'visibility' => $visibility],
        ]));
    }

    /**
     * View video details
     */
    public function viewVideo(int $id): Response
    {
        $video = $this->db->fetchOne(
            "SELECT v.*, u.name as author_name, u.email as author_email
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE v.id = ?",
            [$id]
        );

        if (!$video) {
            return Response::html($this->render('errors/404', ['title' => 'Not Found']), 404);
        }

        $reports = $this->db->fetchAll(
            "SELECT r.*, u.name as reporter_name
             FROM video_reports r
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.video_id = ?
             ORDER BY r.created_at DESC",
            [$id]
        );

        return Response::html($this->render('admin/view-video', [
            'title' => 'Video #' . $id . ' - Admin',
            'video' => $video,
            'reports' => $reports,
        ]));
    }

    /**
     * Reports management
     */
    public function reports(): Response
    {
        $reports = $this->db->fetchAll(
            "SELECT r.*, v.title as video_title, u.name as reporter_name
             FROM video_reports r
             LEFT JOIN videos v ON r.video_id = v.id
             LEFT JOIN users u ON r.user_id = u.id
             ORDER BY r.created_at DESC
             LIMIT 50"
        );

        return Response::html($this->render('admin/reports', [
            'title' => 'Reports - Admin',
            'reports' => $reports,
        ]));
    }

    /**
     * Payments management
     */
    public function payments(): Response
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $status = $_GET['status'] ?? '';

        $where = "1=1";
        $params = [];

        if (!empty($status)) {
            $where .= " AND p.status = ?";
            $params[] = $status;
        }

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM payments p WHERE {$where}",
            $params
        )['COUNT(*)'];

        $payments = $this->db->fetchAll(
            "SELECT p.*, u.name as user_name, u.email as user_email
             FROM payments p
             JOIN users u ON p.user_id = u.id
             WHERE {$where}
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return Response::html($this->render('admin/payments', [
            'title' => 'Payments - Admin',
            'payments' => $payments,
            'page' => $page,
            'total_pages' => ceil($total / $perPage),
            'filters' => ['status' => $status],
        ]));
    }

    /**
     * Admin settings
     */
    public function settings(): Response
    {
        return Response::html($this->render('admin/settings', [
            'title' => 'Settings - Admin',
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
        include $viewPath . 'admin/layout.php';
        include $viewPath . $template . '.php';
        include $viewPath . 'layouts/footer.php';
        
        return ob_get_clean();
    }
}
