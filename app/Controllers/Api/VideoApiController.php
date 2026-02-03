<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Logger;
use App\Core\Config;
use App\Providers\Video\VideoProviderFactory;

/**
 * Video API Controller
 * JSON API for video generation and management
 */
class VideoApiController
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
     * List user's videos
     */
    public function list(): Response
    {
        $userId = $this->session->get('user_id');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $videos = $this->db->fetchAll(
            "SELECT * FROM videos 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );

        $total = $this->db->count('videos', ['user_id' => $userId]);

        return Response::success([
            'videos' => $videos,
            'page' => $page,
            'total_pages' => ceil($total / $perPage),
            'total' => $total,
        ]);
    }

    /**
     * Create video record
     */
    public function create(): Response
    {
        $userId = $this->session->get('user_id');
        $data = $_POST ?? [];

        $imageUrl = $data['image_url'] ?? '';
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $format = $data['format'] ?? '16:9';
        $preset = $data['preset'] ?? 'default';
        $projectId = !empty($data['project_id']) ? (int) $data['project_id'] : null;

        // Validate
        if (empty($imageUrl)) {
            return Response::error('Image URL required', 400);
        }

        if (!in_array($format, ['16:9', '9:16', '1:1'])) {
            return Response::error('Invalid format', 400);
        }

        // Create video record
        $videoId = $this->db->insert('videos', [
            'user_id' => $userId,
            'project_id' => $projectId,
            'original_image' => $imageUrl,
            'format' => $format,
            'preset' => $preset,
            'title' => $title ?: 'Untitled Video',
            'description' => $description,
            'status' => 'pending',
            'visibility' => 'private',
        ]);

        $this->logger->info('Video created', ['video_id' => $videoId, 'user_id' => $userId]);

        return Response::success([
            'video_id' => $videoId,
            'status' => 'pending',
        ], 'Video created');
    }

    /**
     * View video details
     */
    public function view(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $video = $this->db->fetchOne(
            "SELECT * FROM videos WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$video) {
            return Response::error('Video not found', 404);
        }

        // Get job info
        $job = $this->db->fetchOne(
            "SELECT * FROM generation_jobs WHERE video_id = ? ORDER BY created_at DESC",
            [$id]
        );

        return Response::success([
            'video' => $video,
            'job' => $job,
        ]);
    }

    /**
     * Start video generation
     */
    public function generate(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $video = $this->db->fetchOne(
            "SELECT * FROM videos WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$video) {
            return Response::error('Video not found', 404);
        }

        if ($video['status'] === 'processing') {
            return Response::error('Video is already being generated', 400);
        }

        // Check credits
        $creditsPerVideo = (int) $this->config->get('CREDITS_PER_VIDEO', 1);
        $user = $this->db->fetchOne("SELECT credits FROM users WHERE id = ?", [$userId]);

        if (($user['credits'] ?? 0) < $creditsPerVideo) {
            return Response::error('Insufficient credits. Please purchase more.', 402);
        }

        // Create generation job
        $providerFactory = new VideoProviderFactory();
        $provider = $providerFactory->getBestProvider();

        $jobId = $this->db->insert('generation_jobs', [
            'video_id' => $id,
            'user_id' => $userId,
            'provider' => $provider->getName(),
            'status' => 'queued',
            'input_params' => json_encode([
                'image_url' => $video['original_image'],
                'format' => $video['format'],
                'preset' => $video['preset'],
            ]),
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // Deduct credits
        $this->db->update('users', [
            'credits' => $user['credits'] - $creditsPerVideo,
        ], ['id' => $userId]);

        // Log credit deduction
        $newBalance = $user['credits'] - $creditsPerVideo;
        $this->db->insert('credit_ledger', [
            'user_id' => $userId,
            'type' => 'debit',
            'amount' => $creditsPerVideo,
            'balance' => $newBalance,
            'description' => 'Video generation',
            'reference_type' => 'video',
            'reference_id' => $id,
        ]);

        // Update video status
        $this->db->update('videos', [
            'status' => 'processing',
        ], ['id' => $id]);

        $this->logger->info('Generation job queued', [
            'job_id' => $jobId,
            'video_id' => $id,
            'user_id' => $userId,
            'provider' => $provider->getName(),
        ]);

        return Response::success([
            'job_id' => $jobId,
            'status' => 'queued',
        ], 'Generation started');
    }

    /**
     * Get generation status
     */
    public function status(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $video = $this->db->fetchOne(
            "SELECT * FROM videos WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$video) {
            return Response::error('Video not found', 404);
        }

        $job = $this->db->fetchOne(
            "SELECT * FROM generation_jobs WHERE video_id = ? ORDER BY created_at DESC",
            [$id]
        );

        return Response::success([
            'video_status' => $video['status'],
            'video' => $video,
            'job' => $job,
        ]);
    }

    /**
     * Delete video
     */
    public function delete(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $video = $this->db->fetchOne(
            "SELECT * FROM videos WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$video) {
            return Response::error('Video not found', 404);
        }

        // Delete associated jobs
        $this->db->delete('generation_jobs', ['video_id' => $id]);

        // Delete video record
        $this->db->delete('videos', ['id' => $id]);

        $this->logger->info('Video deleted', ['video_id' => $id, 'user_id' => $userId]);

        return Response::success(null, 'Video deleted');
    }

    /**
     * Regenerate video
     */
    public function regenerate(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $video = $this->db->fetchOne(
            "SELECT * FROM videos WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$video) {
            return Response::error('Video not found', 404);
        }

        // Reset video status
        $this->db->update('videos', [
            'status' => 'pending',
            'result_video' => null,
            'thumbnail' => null,
        ], ['id' => $id]);

        return Response::success([
            'status' => 'pending',
        ], 'Video ready for regeneration. Call POST /videos/:id/generate to start.');
    }

    /**
     * Like video
     */
    public function like(int $id): Response
    {
        $userId = $this->session->get('user_id');

        if ($this->db->exists('video_likes', ['video_id' => $id, 'user_id' => $userId])) {
            return Response::error('Already liked', 400);
        }

        $this->db->insert('video_likes', [
            'video_id' => $id,
            'user_id' => $userId,
        ]);

        $this->db->query("UPDATE videos SET like_count = like_count + 1 WHERE id = ?", [$id]);

        return Response::success(['liked' => true], 'Video liked');
    }

    /**
     * Unlike video
     */
    public function unlike(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $this->db->delete('video_likes', ['video_id' => $id, 'user_id' => $userId]);
        $this->db->query("UPDATE videos SET like_count = GREATEST(0, like_count - 1) WHERE id = ?", [$id]);

        return Response::success(['liked' => false], 'Video unliked');
    }

    /**
     * Record video view
     */
    public function viewVideo(int $id): Response
    {
        $this->db->query("UPDATE videos SET view_count = view_count + 1 WHERE id = ?", [$id]);
        return Response::success(['viewed' => true]);
    }

    /**
     * Report video
     */
    public function report(int $id): Response
    {
        $userId = $this->session->get('user_id');
        $data = $_POST ?? [];

        $reason = $data['reason'] ?? '';
        $description = $data['description'] ?? '';

        if (!in_array($reason, ['spam', 'copyright', 'nsfw', 'harassment', 'other'])) {
            return Response::error('Invalid reason', 400);
        }

        $this->db->insert('video_reports', [
            'video_id' => $id,
            'user_id' => $userId,
            'reason' => $reason,
            'description' => $description,
            'status' => 'pending',
        ]);

        return Response::success(null, 'Report submitted');
    }

    /**
     * Update video visibility
     */
    public function updateVisibility(int $id): Response
    {
        $userId = $this->session->get('user_id');
        $data = $_POST ?? [];

        $visibility = $data['visibility'] ?? '';
        if (!in_array($visibility, ['private', 'unlisted', 'public'])) {
            return Response::error('Invalid visibility', 400);
        }

        $this->db->update('videos', [
            'visibility' => $visibility,
        ], ['id' => $id, 'user_id' => $userId]);

        return Response::success(['visibility' => $visibility]);
    }
}
