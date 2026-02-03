<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Logger;
use App\Core\Storage\LocalStorage;
use App\Core\Config;

/**
 * Project API Controller
 * JSON API for project management
 */
class ProjectApiController
{
    private Database $db;
    private Session $session;
    private Logger $logger;
    private LocalStorage $storage;
    private Config $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
        $this->logger = Logger::getInstance();
        $this->storage = new LocalStorage();
        $this->config = Config::getInstance();
    }

    /**
     * List user's projects
     */
    public function list(): Response
    {
        $userId = $this->session->get('user_id');

        $projects = $this->db->fetchAll(
            "SELECT p.*, 
                    (SELECT COUNT(*) FROM videos WHERE project_id = p.id) as video_count,
                    (SELECT MAX(created_at) FROM videos WHERE project_id = p.id) as last_video_at
             FROM projects p
             WHERE p.user_id = ?
             ORDER BY p.updated_at DESC",
            [$userId]
        );

        return Response::success(['projects' => $projects]);
    }

    /**
     * Create new project
     */
    public function create(): Response
    {
        $userId = $this->session->get('user_id');
        $data = $_POST ?? [];

        $name = $data['name'] ?? 'Untitled Project';
        $description = $data['description'] ?? '';
        $format = $data['format'] ?? '16:9';

        if (strlen($name) > 200) {
            return Response::error('Name too long', 400);
        }

        $projectId = $this->db->insert('projects', [
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'format' => $format,
            'status' => 'draft',
        ]);

        return Response::success([
            'project_id' => $projectId,
        ], 'Project created');
    }

    /**
     * View project
     */
    public function view(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $project = $this->db->fetchOne(
            "SELECT * FROM projects WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$project) {
            return Response::error('Project not found', 404);
        }

        $videos = $this->db->fetchAll(
            "SELECT * FROM videos WHERE project_id = ? ORDER BY created_at DESC",
            [$id]
        );

        return Response::success([
            'project' => $project,
            'videos' => $videos,
        ]);
    }

    /**
     * Update project
     */
    public function update(int $id): Response
    {
        $userId = $this->session->get('user_id');
        $data = $_POST ?? [];

        $project = $this->db->fetchOne(
            "SELECT * FROM projects WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$project) {
            return Response::error('Project not found', 404);
        }

        $updates = [];

        if (isset($data['name'])) {
            $updates['name'] = trim($data['name']);
        }

        if (isset($data['description'])) {
            $updates['description'] = trim($data['description']);
        }

        if (isset($data['status']) && in_array($data['status'], ['draft', 'active'])) {
            $updates['status'] = $data['status'];
        }

        if (!empty($updates)) {
            $this->db->update('projects', $updates, ['id' => $id]);
        }

        return Response::success(['updated' => true], 'Project updated');
    }

    /**
     * Delete project
     */
    public function delete(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $project = $this->db->fetchOne(
            "SELECT * FROM projects WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$project) {
            return Response::error('Project not found', 404);
        }

        // Delete associated videos
        $videos = $this->db->fetchAll("SELECT id FROM videos WHERE project_id = ?", [$id]);
        foreach ($videos as $video) {
            $this->db->delete('generation_jobs', ['video_id' => $video['id']]);
        }
        $this->db->delete('videos', ['project_id' => $id]);

        // Delete project
        $this->db->delete('projects', ['id' => $id]);

        return Response::success(null, 'Project deleted');
    }

    /**
     * Upload image
     */
    public function uploadImage(): Response
    {
        $userId = $this->session->get('user_id');

        if (!isset($_FILES['image'])) {
            return Response::error('No image uploaded', 400);
        }

        $file = $_FILES['image'];

        // Validate
        $maxSize = (int) $this->config->get('UPLOAD_MAX_SIZE', 10485760);
        $allowedTypes = explode(',', $this->config->get('ALLOWED_IMAGE_TYPES', 'image/jpeg,image/png,image/webp'));

        if ($file['size'] > $maxSize) {
            return Response::error('File too large. Maximum size: ' . round($maxSize / 1024 / 1024) . 'MB', 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return Response::error('Invalid file type. Allowed: JPEG, PNG, WebP', 400);
        }

        // Generate safe filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
        $destination = 'uploads/' . date('Y/m/d') . '/' . $safeName;

        // Upload
        $url = $this->storage->upload($file['tmp_name'], $destination);

        $this->logger->info('Image uploaded', [
            'user_id' => $userId,
            'filename' => $safeName,
            'size' => $file['size'],
        ]);

        return Response::success([
            'url' => $url,
            'filename' => $safeName,
        ], 'Upload successful');
    }
}
