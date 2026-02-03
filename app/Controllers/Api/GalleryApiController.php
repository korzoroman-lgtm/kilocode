<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;

/**
 * Gallery API Controller
 * JSON API for public gallery
 */
class GalleryApiController
{
    private Database $db;
    private Session $session;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
    }

    /**
     * List public videos
     */
    public function list(): Response
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 24;
        $format = $_GET['format'] ?? '';
        $preset = $_GET['preset'] ?? '';
        $sort = $_GET['sort'] ?? 'newest';

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

        $orderBy = match ($sort) {
            'popular' => 'v.view_count DESC, v.like_count DESC',
            'liked' => 'v.like_count DESC',
            default => 'v.created_at DESC',
        };

        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM videos v WHERE " . implode(' AND ', $where),
            $params
        )['COUNT(*)'];

        $videos = $this->db->fetchAll(
            "SELECT v.id, v.title, v.thumbnail, v.format, v.view_count, v.like_count, v.created_at,
                    u.id as author_id, u.name as author_name, u.avatar as author_avatar
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$orderBy}
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        // Get user's liked videos
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

        return Response::success([
            'videos' => $videos,
            'user_liked' => $userLiked,
            'page' => $page,
            'total_pages' => ceil($total / $perPage),
            'total' => $total,
        ]);
    }

    /**
     * Get featured videos
     */
    public function featured(): Response
    {
        $videos = $this->db->fetchAll(
            "SELECT v.id, v.title, v.thumbnail, v.format, v.view_count, v.like_count,
                    u.id as author_id, u.name as author_name
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE v.visibility = 'public' AND v.status = 'completed' AND v.is_nsfw = 0 AND v.is_featured = 1
             ORDER BY v.created_at DESC
             LIMIT 12"
        );

        return Response::success(['videos' => $videos]);
    }

    /**
     * View single video
     */
    public function view(int $id): Response
    {
        $video = $this->db->fetchOne(
            "SELECT v.*, u.id as author_id, u.name as author_name, u.avatar as author_avatar
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE v.id = ? AND v.visibility IN ('public', 'unlisted')",
            [$id]
        );

        if (!$video) {
            return Response::error('Video not found', 404);
        }

        // Increment view count
        $this->db->query("UPDATE videos SET view_count = view_count + 1 WHERE id = ?", [$id]);

        // Check if user liked
        $userLiked = false;
        if ($this->session->has('user_id')) {
            $userId = $this->session->get('user_id');
            $userLiked = $this->db->exists('video_likes', [
                'video_id' => $id,
                'user_id' => $userId,
            ]);
        }

        return Response::success([
            'video' => $video,
            'user_liked' => $userLiked,
        ]);
    }
}
