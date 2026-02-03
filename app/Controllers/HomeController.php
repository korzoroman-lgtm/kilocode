<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;

/**
 * Home Controller
 * Handles landing page and static pages
 */
class HomeController
{
    private Database $db;
    private Session $session;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
    }

    /**
     * Landing page
     */
    public function index(): Response
    {
        // Get featured videos for showcase
        $featuredVideos = $this->getFeaturedVideos(6);

        // Get stats
        $stats = $this->getStats();

        return Response::html($this->render('home/index', [
            'title' => 'Photo2Video - Turn Photos into Amazing Videos',
            'featured_videos' => $featuredVideos,
            'stats' => $stats,
            'is_logged_in' => $this->session->has('user_id'),
        ]));
    }

    /**
     * Features page
     */
    public function features(): Response
    {
        return Response::html($this->render('home/features', [
            'title' => 'Features - Photo2Video',
        ]));
    }

    /**
     * Pricing page
     */
    public function pricing(): Response
    {
        return Response::html($this->render('home/pricing', [
            'title' => 'Pricing - Photo2Video',
        ]));
    }

    /**
     * About page
     */
    public function about(): Response
    {
        return Response::html($this->render('home/about', [
            'title' => 'About - Photo2Video',
        ]));
    }

    /**
     * Get featured videos
     */
    private function getFeaturedVideos(int $limit): array
    {
        return $this->db->fetchAll(
            "SELECT v.*, u.name as author_name, u.avatar as author_avatar
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE v.visibility = 'public' AND v.status = 'completed' AND v.is_nsfw = 0
             ORDER BY v.is_featured DESC, v.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Get site statistics
     */
    private function getStats(): array
    {
        return [
            'total_videos' => $this->db->count('videos', ['status' => 'completed']),
            'total_users' => $this->db->count('users'),
            'total_generations' => $this->db->count('generation_jobs', ['status' => 'succeeded']),
        ];
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
