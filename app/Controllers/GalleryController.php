<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;

/**
 * Gallery Controller
 * Handles public video gallery
 */
class GalleryController
{
    private Database $db;
    private Session $session;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
    }

    /**
     * Gallery index page
     */
    public function index(): Response
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 24;
        
        // Get filters
        $format = $_GET['format'] ?? '';
        $preset = $_GET['preset'] ?? '';
        $sort = $_GET['sort'] ?? 'newest';

        // Build query
        $where = ["v.visibility = 'public'", "v.status = 'completed'", "v.is_nsfw = 0"];
        $params = [];

        if (!empty($format)) {
            $where[] = "v.format = ?";
            $params[] = $format;
        }

        if (!empty($preset)) {
            $where[] = "v.preset = ?";
            $params[] = $preset;
        }

        // Order by
        $orderBy = match ($sort) {
            'popular' => 'v.view_count DESC, v.like_count DESC',
            'liked' => 'v.like_count DESC',
            default => 'v.created_at DESC',
        };

        // Get total count
        $totalSql = "SELECT COUNT(*) FROM videos v WHERE " . implode(' AND ', $where);
        $total = (int) $this->db->fetchOne($totalSql, $params)['COUNT(*)'];

        // Get videos
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT v.*, u.name as author_name, u.avatar as author_avatar
                FROM videos v
                JOIN users u ON v.user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$orderBy}
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $videos = $this->db->fetchAll($sql, $params);

        // Check if user liked videos
        $userLiked = [];
        if ($this->session->has('user_id')) {
            $userId = $this->session->get('user_id');
            $videoIds = array_column($videos, 'id');
            
            if (!empty($videoIds)) {
                $liked = $this->db->fetchAll(
                    "SELECT video_id FROM video_likes WHERE user_id = ? AND video_id IN (" . implode(',', $videoIds) . ")",
                    [$userId]
                );
                $userLiked = array_column($liked, 'video_id');
            }
        }

        // Pagination
        $totalPages = ceil($total / $perPage);

        return Response::html($this->render('gallery/index', [
            'title' => 'Gallery - Photo2Video',
            'videos' => $videos,
            'user_liked' => $userLiked,
            'page' => $page,
            'total_pages' => $totalPages,
            'filters' => [
                'format' => $format,
                'preset' => $preset,
                'sort' => $sort,
            ],
            'is_logged_in' => $this->session->has('user_id'),
        ]));
    }

    /**
     * View single video
     */
    public function view(int $id): Response
    {
        $video = $this->db->fetchOne(
            "SELECT v.*, u.name as author_name, u.avatar as author_avatar, u.id as author_id
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE v.id = ? AND v.visibility IN ('public', 'unlisted')",
            [$id]
        );

        if (!$video) {
            return Response::html($this->render('errors/404', [
                'title' => 'Not Found',
            ]), 404);
        }

        // Check if NSFW and user didn't consent
        if ($video['is_nsfw'] && !$this->session->get('show_nsfw', false)) {
            return Response::html($this->render('gallery/nsfw_warning', [
                'video' => $video,
            ]));
        }

        // Increment view count
        $this->db->query("UPDATE videos SET view_count = view_count + 1 WHERE id = ?", [$id]);

        // Get related videos
        $related = $this->db->fetchAll(
            "SELECT v.*, u.name as author_name
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE v.id != ? AND v.visibility = 'public' AND v.status = 'completed'
             AND (v.format = ? OR v.preset = ?)
             ORDER BY v.view_count DESC
             LIMIT 6",
            [$id, $video['format'], $video['preset']]
        );

        // Check if user liked
        $userLiked = false;
        if ($this->session->has('user_id')) {
            $userId = $this->session->get('user_id');
            $userLiked = $this->db->exists('video_likes', [
                'video_id' => $id,
                'user_id' => $userId,
            ]);
        }

        // Get comments (placeholder)
        $comments = [];

        return Response::html($this->render('gallery/view', [
            'title' => $video['title'] ?? 'Video - Photo2Video',
            'video' => $video,
            'related_videos' => $related,
            'user_liked' => $userLiked,
            'comments' => $comments,
            'is_logged_in' => $this->session->has('user_id'),
            'is_author' => $this->session->get('user_id') === $video['user_id'],
        ]));
    }

    /**
     * Embed video
     */
    public function embed(int $id): Response
    {
        $video = $this->db->fetchOne(
            "SELECT v.*, u.name as author_name
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE v.id = ? AND v.visibility IN ('public', 'unlisted') AND v.status = 'completed'",
            [$id]
        );

        if (!$video) {
            return Response::html('', 404);
        }

        return Response::html($this->render('gallery/embed', [
            'video' => $video,
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
