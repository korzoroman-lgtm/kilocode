<?php

declare(strict_types=1);

namespace App\Core\Storage;

use App\Core\Config;
use RuntimeException;

/**
 * Local File Storage Driver
 * Stores files locally in the specified directory
 */
class LocalStorage implements StorageDriverInterface
{
    private string $basePath;
    private string $baseUrl;

    public function __construct(?string $basePath = null, ?string $baseUrl = null)
    {
        $config = Config::getInstance();
        $this->basePath = rtrim($basePath ?? $config->get('STORAGE_PATH', './storage'), '/');
        $this->baseUrl = rtrim($baseUrl ?? $config->get('APP_URL', 'http://localhost'), '/');
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectory(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function upload(string $sourcePath, string $destinationPath): string
    {
        $fullPath = $this->basePath . '/' . ltrim($destinationPath, '/');
        $this->ensureDirectory($fullPath);

        if (!copy($sourcePath, $fullPath)) {
            throw new RuntimeException("Failed to upload file: {$sourcePath} -> {$destinationPath}");
        }

        return $this->getUrl($destinationPath);
    }

    public function uploadContent(string $content, string $destinationPath): string
    {
        $fullPath = $this->basePath . '/' . ltrim($destinationPath, '/');
        $this->ensureDirectory($fullPath);

        if (file_put_contents($fullPath, $content) === false) {
            throw new RuntimeException("Failed to upload content to: {$destinationPath}");
        }

        return $this->getUrl($destinationPath);
    }

    public function download(string $sourcePath, string $destinationPath): bool
    {
        $fullSourcePath = $this->basePath . '/' . ltrim($sourcePath, '/');
        
        if (!file_exists($fullSourcePath)) {
            return false;
        }

        return copy($fullSourcePath, $destinationPath);
    }

    public function getUrl(string $filePath): string
    {
        // For local storage, we need to map storage path to web-accessible URL
        // The storage folder should be symlinked or accessible via web
        $filePath = ltrim($filePath, '/');
        
        // Map storage subdirectories to URL paths
        if (str_starts_with($filePath, 'uploads/')) {
            return $this->baseUrl . '/storage/uploads/' . substr($filePath, 8);
        }
        if (str_starts_with($filePath, 'videos/')) {
            return $this->baseUrl . '/storage/videos/' . substr($filePath, 7);
        }
        if (str_starts_with($filePath, 'avatars/')) {
            return $this->baseUrl . '/storage/avatars/' . substr($filePath, 8);
        }
        
        return $this->baseUrl . '/storage/' . $filePath;
    }

    public function delete(string $filePath): bool
    {
        $fullPath = $this->basePath . '/' . ltrim($filePath, '/');
        
        if (!file_exists($fullPath)) {
            return true; // Already deleted
        }

        return unlink($fullPath);
    }

    public function exists(string $filePath): bool
    {
        $fullPath = $this->basePath . '/' . ltrim($filePath, '/');
        return file_exists($fullPath);
    }

    public function getSize(string $filePath): int
    {
        $fullPath = $this->basePath . '/' . ltrim($filePath, '/');
        
        if (!file_exists($fullPath)) {
            return 0;
        }

        return filesize($fullPath);
    }

    public function copy(string $sourcePath, string $destinationPath): bool
    {
        $fullSource = $this->basePath . '/' . ltrim($sourcePath, '/');
        $fullDest = $this->basePath . '/' . ltrim($destinationPath, '/');
        
        $this->ensureDirectory($fullDest);
        
        return copy($fullSource, $fullDest);
    }

    public function move(string $sourcePath, string $destinationPath): bool
    {
        $fullSource = $this->basePath . '/' . ltrim($sourcePath, '/');
        $fullDest = $this->basePath . '/' . ltrim($destinationPath, '/');
        
        $this->ensureDirectory($fullDest);
        
        return rename($fullSource, $fullDest);
    }

    /**
     * Get the local file path for a stored file
     */
    public function getLocalPath(string $filePath): string
    {
        return $this->basePath . '/' . ltrim($filePath, '/');
    }
}
