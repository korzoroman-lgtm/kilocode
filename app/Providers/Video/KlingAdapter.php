<?php

declare(strict_types=1);

namespace App\Providers\Video;

use App\Core\Config;
use App\Core\Logger;

/**
 * Kling Video Generation Adapter
 * Implements Kling AI video generation API
 * 
 * @link https://www.kling.ai/
 */
class KlingAdapter implements VideoProviderInterface
{
    private Config $config;
    private Logger $logger;
    private string $apiUrl;
    private string $apiKey;
    private string $secretKey;
    private int $timeout;
    private bool $enabled;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
        $this->apiUrl = $this->config->get('KLING_API_URL', 'https://api.kling.ai/v1');
        $this->apiKey = $this->config->get('KLING_API_KEY', '');
        $this->secretKey = $this->config->get('KLING_SECRET_KEY', '');
        $this->timeout = (int) $this->config->get('KLING_TIMEOUT', 30);
        $this->enabled = $this->config->get('KLING_ENABLED', 'false') === 'true';
    }

    public function getName(): string
    {
        return 'kling';
    }

    public function getDisplayName(): string
    {
        return 'Kling AI';
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey) && !empty($this->secretKey);
    }

    public function createTask(array $payload): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Kling provider is not enabled. Please configure KLING_API_KEY and KLING_SECRET_KEY.');
        }

        $this->logger->info('Creating Kling task', ['payload' => $payload]);

        // Prepare API request
        $endpoint = $this->apiUrl . '/video/generate';
        
        $data = $this->prepareRequestData($payload);
        
        $response = $this->makeRequest('POST', $endpoint, $data);
        
        return [
            'provider_task_id' => $response['task_id'] ?? '',
            'status' => $response['task_status'] ?? 'pending',
            'raw_response' => $response,
        ];
    }

    public function pollStatus(string $taskId): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Kling provider is not enabled.');
        }

        $endpoint = $this->apiUrl . '/video/task/' . $taskId;
        
        $response = $this->makeRequest('GET', $endpoint);
        
        return [
            'status' => $this->mapStatus($response['task_status'] ?? 'unknown'),
            'progress' => $response['task_progress'] ?? 0,
            'raw_response' => $response,
        ];
    }

    public function fetchResult(string $taskId): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Kling provider is not enabled.');
        }

        $status = $this->pollStatus($taskId);
        
        if ($status['status'] !== 'succeeded') {
            return [
                'success' => false,
                'status' => $status['status'],
                'message' => 'Task not completed yet',
            ];
        }

        $response = $status['raw_response'];
        
        return [
            'success' => true,
            'video_url' => $response['task_result']['video_url'] ?? '',
            'thumbnail_url' => $response['task_result']['cover_url'] ?? '',
            'duration' => $response['task_result']['duration'] ?? null,
            'width' => $response['task_result']['width'] ?? null,
            'height' => $response['task_result']['height'] ?? null,
            'raw_response' => $response,
        ];
    }

    public function cancelTask(string $taskId): bool
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Kling provider is not enabled.');
        }

        $endpoint = $this->apiUrl . '/video/task/' . $taskId . '/cancel';
        
        try {
            $this->makeRequest('POST', $endpoint);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel Kling task', ['taskId' => $taskId, 'error' => $e->getMessage()]);
            return false;
        }
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
     * Prepare request data for Kling API
     */
    private function prepareRequestData(array $payload): array
    {
        return [
            'image' => $payload['image_url'],
            'prompt' => $payload['prompt'] ?? 'Make this image animated',
            'duration' => $payload['duration'] ?? '5',
            'aspect_ratio' => $payload['format'] ?? '16:9',
            'cfg_scale' => $payload['cfg_scale'] ?? 1.5,
            'motion_mode' => $payload['preset'] ?? 'default',
        ];
    }

    /**
     * Make API request to Kling
     */
    private function makeRequest(string $method, string $url, array $data = []): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Kling API request failed: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException('Kling API error: ' . $response);
        }

        $decoded = json_decode($response, true);
        
        if ($decoded === null) {
            throw new \RuntimeException('Invalid JSON response from Kling API');
        }

        return $decoded;
    }

    /**
     * Map Kling status to our status
     */
    private function mapStatus(string $klingStatus): string
    {
        return match ($klingStatus) {
            'pending', 'queued' => 'pending',
            'processing', 'running' => 'processing',
            'completed', 'succeeded' => 'succeeded',
            'failed', 'error', 'canceled' => 'failed',
            default => 'unknown',
        };
    }
}
