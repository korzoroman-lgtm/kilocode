<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;

/**
 * Dashboard Controller
 * User dashboard and project management
 */
class DashboardController
{
    private Database $db;
    private Session $session;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
    }

    /**
     * Dashboard index
     */
    public function index(): Response
    {
        $userId = $this->session->get('user_id');

        // Get stats
        $stats = [
            'total_videos' => $this->db->count('videos', ['user_id' => $userId]),
            'completed_videos' => $this->db->count('videos', ['user_id' => $userId, 'status' => 'completed']),
            'credits' => $this->db->fetchOne("SELECT credits FROM users WHERE id = ?", [$userId])['credits'] ?? 0,
        ];

        // Get recent videos
        $recentVideos = $this->db->fetchAll(
            "SELECT * FROM videos WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
            [$userId]
        );

        // Get pending generations
        $pendingJobs = $this->db->fetchAll(
            "SELECT j.*, v.title as video_title, v.original_image
             FROM generation_jobs j
             JOIN videos v ON j.video_id = v.id
             WHERE j.user_id = ? AND j.status IN ('queued', 'processing')
             ORDER BY j.created_at DESC
             LIMIT 5",
            [$userId]
        );

        return Response::html($this->render('dashboard/index', [
            'title' => 'Dashboard - Photo2Video',
            'stats' => $stats,
            'recent_videos' => $recentVideos,
            'pending_jobs' => $pendingJobs,
            'user' => $this->getUser(),
        ]));
    }

    /**
     * Projects list
     */
    public function projects(): Response
    {
        $userId = $this->session->get('user_id');

        $projects = $this->db->fetchAll(
            "SELECT p.*, COUNT(v.id) as video_count
             FROM projects p
             LEFT JOIN videos v ON p.id = v.project_id
             WHERE p.user_id = ?
             GROUP BY p.id
             ORDER BY p.updated_at DESC",
            [$userId]
        );

        return Response::html($this->render('dashboard/projects', [
            'title' => 'My Projects - Photo2Video',
            'projects' => $projects,
        ]));
    }

    /**
     * New project page
     */
    public function newProject(): Response
    {
        return Response::html($this->render('dashboard/new-project', [
            'title' => 'New Project - Photo2Video',
            'csrf_token' => $this->session->getCsrfToken(),
        ]));
    }

    /**
     * View project
     */
    public function viewProject(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $project = $this->db->fetchOne(
            "SELECT * FROM projects WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$project) {
            return Response::html($this->render('errors/404', ['title' => 'Not Found']), 404);
        }

        $videos = $this->db->fetchAll(
            "SELECT * FROM videos WHERE project_id = ? ORDER BY created_at DESC",
            [$id]
        );

        return Response::html($this->render('dashboard/view-project', [
            'title' => $project['name'] . ' - Photo2Video',
            'project' => $project,
            'videos' => $videos,
        ]));
    }

    /**
     * Edit project
     */
    public function editProject(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $project = $this->db->fetchOne(
            "SELECT * FROM projects WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$project) {
            return Response::html($this->render('errors/404', ['title' => 'Not Found']), 404);
        }

        return Response::html($this->render('dashboard/edit-project', [
            'title' => 'Edit Project - Photo2Video',
            'project' => $project,
            'csrf_token' => $this->session->getCsrfToken(),
        ]));
    }

    /**
     * Generations list
     */
    public function generations(): Response
    {
        $userId = $this->session->get('user_id');

        $jobs = $this->db->fetchAll(
            "SELECT j.*, v.title as video_title, v.thumbnail
             FROM generation_jobs j
             JOIN videos v ON j.video_id = v.id
             WHERE j.user_id = ?
             ORDER BY j.created_at DESC
             LIMIT 50",
            [$userId]
        );

        return Response::html($this->render('dashboard/generations', [
            'title' => 'Generation History - Photo2Video',
            'jobs' => $jobs,
        ]));
    }

    /**
     * Credits page
     */
    public function credits(): Response
    {
        $userId = $this->session->get('user_id');

        // Get credit history
        $creditHistory = $this->db->fetchAll(
            "SELECT * FROM credit_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
            [$userId]
        );

        // Get user balance
        $user = $this->db->fetchOne("SELECT credits FROM users WHERE id = ?", [$userId]);

        return Response::html($this->render('dashboard/credits', [
            'title' => 'Credits - Photo2Video',
            'credits' => $user['credits'] ?? 0,
            'history' => $creditHistory,
        ]));
    }

    /**
     * Settings page
     */
    public function settings(): Response
    {
        $user = $this->getUser();

        return Response::html($this->render('dashboard/settings', [
            'title' => 'Settings - Photo2Video',
            'user' => $user,
            'csrf_token' => $this->session->getCsrfToken(),
        ]));
    }

    /**
     * Get current user
     */
    private function getUser(): array
    {
        $userId = $this->session->get('user_id');
        return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]) ?? [];
    }

    /**
     * Render view with layout
     */
    private function render(string $template, array $data = []): string
    {
        $viewPath = dirname(__DIR__) . '/View/';
        
        ob_start();
        
        include $viewPath . 'layouts/header.php';
        include $viewPath . 'dashboard/layout.php';
        include $viewPath . $template . '.php';
        include $viewPath . 'layouts/footer.php';
        
        return ob_get_clean();
    }
}
