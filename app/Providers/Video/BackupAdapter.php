<?php

declare(strict_types=1);

namespace App\Providers\Video;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Storage\LocalStorage;

/**
 * Backup Video Generation Adapter
 * Fallback provider for testing and development
 * Generates placeholder videos or copies sample videos
 */
class BackupAdapter implements VideoProviderInterface
{
    private Config $config;
    private Logger $logger;
    private LocalStorage $storage;
    private string $sampleVideoPath;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
        $this->storage = new LocalStorage();
        $this->sampleVideoPath = dirname(__DIR__, 2) . '/storage/sample.mp4';
    }

    public function getName(): string
    {
        return 'backup';
    }

    public function getDisplayName(): string
    {
        return 'Backup Provider (Test)';
    }

    public function isEnabled(): bool
    {
        // Always enabled as fallback
        return true;
    }

    public function createTask(array $payload): array
    {
        $this->logger->info('Creating backup task', ['payload' => $payload]);

        // Generate a unique task ID
        $taskId = 'backup_' . uniqid() . '_' . bin2hex(random_bytes(4));

        // Return queued status immediately
        return [
            'provider_task_id' => $taskId,
            'status' => 'queued',
            'raw_response' => [
                'message' => 'Task queued in backup provider',
                'image_url' => $payload['image_url'] ?? '',
                'format' => $payload['format'] ?? '16:9',
                'preset' => $payload['preset'] ?? 'default',
            ],
        ];
    }

    public function pollStatus(string $taskId): array
    {
        // Simulate processing time
        // In real implementation, this would check actual job status
        $this->logger->debug('Polling backup task status', ['taskId' => $taskId]);

        return [
            'status' => 'processing',
            'progress' => 50,
            'message' => 'Generating video...',
            'raw_response' => [
                'task_id' => $taskId,
                'phase' => 'processing',
            ],
        ];
    }

    public function fetchResult(string $taskId): array
    {
        $this->logger->info('Fetching backup task result', ['taskId' => $taskId]);

        // Check if sample video exists, otherwise create a placeholder
        $videoPath = $this->generateOrCopyVideo($taskId);
        
        if ($videoPath === null) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'Could not generate video: sample file not found',
            ];
        }

        // Upload to storage and return URL
        $destination = 'videos/' . $taskId . '.mp4';
        $url = $this->storage->upload($videoPath, $destination);

        // Generate a placeholder thumbnail (just use first frame placeholder)
        $thumbnailUrl = $this->generateThumbnail($taskId);

        return [
            'success' => true,
            'video_url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'duration' => 5.0,
            'width' => 1920,
            'height' => 1080,
            'raw_response' => [
                'task_id' => $taskId,
                'generated_at' => date('c'),
                'provider' => 'backup',
            ],
        ];
    }

    public function cancelTask(string $taskId): bool
    {
        // Cannot cancel backup tasks (they're instant)
        $this->logger->info('Attempted to cancel backup task', ['taskId' => $taskId]);
        return false;
    }

    public function getSupportedFormats(): array
    {
        return [
            '16:9' => 'Landscape (1920x1080)',
            '9:16' => 'Portrait (1080x1920)',
            '1:1' => 'Square (1080x1080)',
        ];
    }

    public function getSupportedPresets(): array
    {
        return [
            'default' => 'Default Animation',
            'smooth' => 'Smooth Motion',
            'cinematic' => 'Cinematic',
            'fast' => 'Fast Motion',
            'slow' => 'Slow Motion',
        ];
    }

    /**
     * Generate or copy a sample video
     */
    private function generateOrCopyVideo(string $taskId): ?string
    {
        // Try to find sample video
        $samplePaths = [
            $this->sampleVideoPath,
            dirname(__DIR__, 2) . '/storage/sample.mp4',
            dirname(__DIR__, 2) . '/storage/uploads/sample.mp4',
        ];

        foreach ($samplePaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                // Copy to temp location for processing
                $tempPath = sys_get_temp_dir() . '/video_' . $taskId . '.mp4';
                if (copy($path, $tempPath)) {
                    return $tempPath;
                }
            }
        }

        // If no sample video, create a minimal valid MP4 placeholder
        // This is a minimal MP4 header for testing
        $this->logger->warning('Sample video not found, creating minimal placeholder', [
            'sample_path' => $this->sampleVideoPath,
        ]);

        // For testing purposes, return null - real implementation should have sample.mp4
        return null;
    }

    /**
     * Generate thumbnail URL
     */
    private function generateThumbnail(string $taskId): string
    {
        // Return a placeholder thumbnail
        return $this->storage->getUrl('thumbnails/' . $taskId . '.jpg');
    }

    /**
     * Simulate video generation delay
     */
    public function simulateProcessing(int $seconds = 2): void
    {
        sleep($seconds);
    }
}
