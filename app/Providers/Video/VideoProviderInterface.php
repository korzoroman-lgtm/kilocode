<?php

declare(strict_types=1);

namespace App\Providers\Video;

/**
 * Video Generation Provider Interface
 * Abstract interface for video generation providers
 */
interface VideoProviderInterface
{
    /**
     * Get provider name
     */
    public function getName(): string;

    /**
     * Get provider display name
     */
    public function getDisplayName(): string;

    /**
     * Check if provider is enabled
     */
    public function isEnabled(): bool;

    /**
     * Create a new video generation task
     * 
     * @param array $payload Task parameters
     * @return array Task info with ID and status
     */
    public function createTask(array $payload): array;

    /**
     * Poll task status
     * 
     * @param string $taskId Provider's task ID
     * @return array Status info
     */
    public function pollStatus(string $taskId): array;

    /**
     * Fetch generated result
     * 
     * @param string $taskId Provider's task ID
     * @return array Result with video URL and metadata
     */
    public function fetchResult(string $taskId): array;

    /**
     * Cancel a running task
     * 
     * @param string $taskId Provider's task ID
     * @return bool
     */
    public function cancelTask(string $taskId): bool;

    /**
     * Get supported formats
     * 
     * @return array Supported aspect ratios
     */
    public function getSupportedFormats(): array;

    /**
     * Get supported presets
     * 
     * @return array Supported animation presets
     */
    public function getSupportedPresets(): array;
}
