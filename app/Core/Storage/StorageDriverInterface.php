<?php

declare(strict_types=1);

namespace App\Core\Storage;

/**
 * Storage Driver Interface
 * Abstract file storage operations for local and cloud storage
 */
interface StorageDriverInterface
{
    /**
     * Upload file from local path
     */
    public function upload(string $sourcePath, string $destinationPath): string;

    /**
     * Upload content directly
     */
    public function uploadContent(string $content, string $destinationPath): string;

    /**
     * Download file to local path
     */
    public function download(string $sourcePath, string $destinationPath): bool;

    /**
     * Get file URL
     */
    public function getUrl(string $filePath): string;

    /**
     * Delete file
     */
    public function delete(string $filePath): bool;

    /**
     * Check if file exists
     */
    public function exists(string $filePath): bool;

    /**
     * Get file size
     */
    public function getSize(string $filePath): int;

    /**
     * Copy file
     */
    public function copy(string $sourcePath, string $destinationPath): bool;

    /**
     * Move file
     */
    public function move(string $sourcePath, string $destinationPath): bool;
}
