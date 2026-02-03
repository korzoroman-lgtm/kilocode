<?php

declare(strict_types=1);

/**
 * Video Generation Worker
 * Processes video generation jobs from the database queue
 * 
 * Usage:
 *   php bin/worker.php              # Run once
 *   php bin/worker.php --daemon    # Run as daemon
 *   php bin/worker.php --sleep=60  # Run with custom sleep interval
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Providers\Video\VideoProviderFactory;
use App\Telegram\TelegramBotService;

class Worker
{
    private Database $db;
    private Logger $logger;
    private Config $config;
    private VideoProviderFactory $providerFactory;
    private TelegramBotService $telegram;
    private int $sleepInterval;
    private bool $running = true;
    private int $maxConcurrentJobs;
    private int $processedCount = 0;
    private int $failedCount = 0;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
        $this->providerFactory = new VideoProviderFactory();
        $this->telegram = new TelegramBotService();
        $this->sleepInterval = (int) ($this->config->get('WORKER_SLEEP_INTERVAL', 60));
        $this->maxConcurrentJobs = (int) ($this->config->get('MAX_CONCURRENT_JOBS', 5));

        // Handle signals for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }

    /**
     * Run the worker
     */
    public function run(bool $daemon = false): void
    {
        $this->logger->info('Worker started', [
            'daemon' => $daemon,
            'sleep_interval' => $this->sleepInterval,
            'max_concurrent_jobs' => $this->maxConcurrentJobs,
        ]);

        while ($this->running) {
            // Process jobs
            $this->processJobs();

            // Check if we should continue
            if (!$daemon) {
                break;
            }

            // Sleep before next iteration
            $this->logger->debug('Worker sleeping', ['seconds' => $this->sleepInterval]);
            sleep($this->sleepInterval);
        }

        $this->logger->info('Worker stopped', [
            'processed' => $this->processedCount,
            'failed' => $this->failedCount,
        ]);
    }

    /**
     * Process pending jobs
     */
    private function processJobs(): void
    {
        // Get pending jobs
        $jobs = $this->db->fetchAll(
            "SELECT j.*, v.user_id, v.original_image, v.format, v.preset, v.title,
                    u.telegram_chat_id, u.telegram_notifications, u.name as user_name
             FROM generation_jobs j
             JOIN videos v ON j.video_id = v.id
             JOIN users u ON j.user_id = u.id
             WHERE j.status IN ('queued', 'processing')
             AND j.attempts < j.max_attempts
             ORDER BY j.created_at ASC
             LIMIT ?",
            [$this->maxConcurrentJobs]
        );

        if (empty($jobs)) {
            $this->logger->debug('No pending jobs found');
            return;
        }

        $this->logger->info('Processing jobs', ['count' => count($jobs)]);

        foreach ($jobs as $job) {
            $this->processJob($job);
            $this->processedCount++;
        }
    }

    /**
     * Process a single job
     */
    private function processJob(array $job): void
    {
        $this->logger->info('Processing job', [
            'job_id' => $job['id'],
            'video_id' => $job['video_id'],
            'provider' => $job['provider'],
            'status' => $job['status'],
        ]);

        try {
            // If job is queued, start it
            if ($job['status'] === 'queued') {
                $this->startJob($job);
            }

            // If job is processing, check status
            if ($job['status'] === 'processing') {
                $this->checkJobStatus($job);
            }

        } catch (\Exception $e) {
            $this->handleJobError($job, $e);
        }
    }

    /**
     * Start a new generation job
     */
    private function startJob(array $job): void
    {
        $provider = $this->providerFactory->getProvider($job['provider']);

        if (!$provider) {
            throw new \RuntimeException('Provider not found: ' . $job['provider']);
        }

        $inputParams = json_decode($job['input_params'] ?? '{}', true);

        // Create task with provider
        $result = $provider->createTask($inputParams);

        // Update job
        $this->db->update('generation_jobs', [
            'provider_task_id' => $result['provider_task_id'],
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s'),
            'attempts' => $job['attempts'] + 1,
            'result_data' => json_encode($result),
        ], ['id' => $job['id']]);

        // Update video status
        $this->db->update('videos', [
            'status' => 'processing',
        ], ['id' => $job['video_id']]);

        $this->logger->info('Job started', [
            'job_id' => $job['id'],
            'provider_task_id' => $result['provider_task_id'],
        ]);
    }

    /**
     * Check job status with provider
     */
    private function checkJobStatus(array $job): void
    {
        $provider = $this->providerFactory->getProvider($job['provider']);

        if (!$provider) {
            throw new \RuntimeException('Provider not found: ' . $job['provider']);
        }

        $statusResult = $provider->pollStatus($job['provider_task_id']);

        $this->logger->debug('Job status check', [
            'job_id' => $job['id'],
            'status' => $statusResult['status'],
            'progress' => $statusResult['progress'] ?? 0,
        ]);

        // Update job with status
        $this->db->update('generation_jobs', [
            'result_data' => json_encode($statusResult),
        ], ['id' => $job['id']]);

        // Check if completed
        if ($statusResult['status'] === 'succeeded') {
            $this->completeJob($job, $statusResult);
        } elseif ($statusResult['status'] === 'failed') {
            $errorMessage = $statusResult['error'] ?? 'Generation failed';
            $this->failJob($job, $errorMessage);
        }
        // If still processing, do nothing
    }

    /**
     * Complete job successfully
     */
    private function completeJob(array $job, array $result): void
    {
        $this->logger->info('Job completed', [
            'job_id' => $job['id'],
            'video_id' => $job['video_id'],
        ]);

        // Get result data
        $resultData = $provider->fetchResult($job['provider_task_id']);

        // Update job
        $this->db->update('generation_jobs', [
            'status' => 'succeeded',
            'completed_at' => date('Y-m-d H:i:s'),
            'result_data' => json_encode($resultData),
        ], ['id' => $job['id']]);

        // Update video
        $this->db->update('videos', [
            'status' => 'completed',
            'result_video' => $resultData['video_url'] ?? null,
            'thumbnail' => $resultData['thumbnail_url'] ?? null,
            'duration' => $resultData['duration'] ?? null,
        ], ['id' => $job['video_id']]);

        // Send Telegram notification
        if ($job['telegram_notifications'] && !empty($job['telegram_chat_id'])) {
            try {
                $this->telegram->sendVideoReadyNotification((int) $job['telegram_chat_id'], [
                    'id' => $job['video_id'],
                    'title' => $job['title'] ?? 'Your Video',
                    'url' => $this->config->get('APP_URL') . '/dashboard/generations',
                    'share_url' => $this->config->get('APP_URL') . '/gallery/' . $job['video_id'],
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Telegram notification failed', [
                    'job_id' => $job['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Fail job
     */
    private function failJob(array $job, string $errorMessage): void
    {
        $this->logger->warning('Job failed', [
            'job_id' => $job['id'],
            'error' => $errorMessage,
            'attempts' => $job['attempts'],
            'max_attempts' => $job['max_attempts'],
        ]);

        // Check if should retry
        if ($job['attempts'] >= $job['max_attempts']) {
            // Mark as permanently failed
            $this->db->update('generation_jobs', [
                'status' => 'failed',
                'error_message' => $errorMessage,
                'completed_at' => date('Y-m-d H:i:s'),
            ], ['id' => $job['id']]);

            $this->db->update('videos', [
                'status' => 'failed',
            ], ['id' => $job['video_id']]);

            $this->failedCount++;

        } else {
            // Reset to queued for retry
            $this->db->update('generation_jobs', [
                'status' => 'queued',
                'error_message' => null,
            ], ['id' => $job['id']]);
        }
    }

    /**
     * Handle job error
     */
    private function handleJobError(array $job, \Exception $e): void
    {
        $this->logger->error('Job error', [
            'job_id' => $job['id'],
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->failJob($job, $e->getMessage());
    }

    /**
     * Shutdown handler
     */
    public function shutdown(): void
    {
        $this->logger->info('Worker shutting down...');
        $this->running = false;
    }
}

// CLI handling
if (php_sapi_name() === 'cli') {
    $options = getopt('', ['daemon', 'sleep::']);
    
    $daemon = isset($options['daemon']);
    $sleep = (int) ($options['sleep'] ?? 0);
    
    $worker = new Worker();
    
    if ($sleep > 0) {
        // Override config sleep interval
        $config = Config::getInstance();
        $config->set('WORKER_SLEEP_INTERVAL', (string) $sleep);
    }
    
    $worker->run($daemon);
}
