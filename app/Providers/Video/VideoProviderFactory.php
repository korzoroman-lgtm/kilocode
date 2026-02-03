<?php

declare(strict_types=1);

namespace App\Providers\Video;

use App\Core\Config;

/**
 * Video Provider Factory
 * Creates and manages video generation providers
 */
class VideoProviderFactory
{
    private static ?self $instance = null;
    private array $providers = [];
    private ?VideoProviderInterface $defaultProvider = null;

    private function __construct()
    {
        $this->registerProviders();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register available providers
     */
    private function registerProviders(): void
    {
        // Register Kling provider
        $this->providers['kling'] = function () {
            return new KlingAdapter();
        };

        // Register Backup provider (fallback)
        $this->providers['backup'] = function () {
            return new BackupAdapter();
        };
    }

    /**
     * Get all registered provider names
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Get a specific provider by name
     */
    public function getProvider(string $name): ?VideoProviderInterface
    {
        $name = strtolower($name);
        
        if (!isset($this->providers[$name])) {
            return null;
        }

        return ($this->providers[$name])();
    }

    /**
     * Get the best available provider
     * Tries primary first, falls back to backup
     */
    public function getBestProvider(?string $preferredProvider = null): VideoProviderInterface
    {
        // Try preferred provider first
        if ($preferredProvider !== null) {
            $provider = $this->getProvider($preferredProvider);
            if ($provider !== null && $provider->isEnabled()) {
                return $provider;
            }
        }

        // Try Kling as primary
        $kling = $this->getProvider('kling');
        if ($kling !== null && $kling->isEnabled()) {
            return $kling;
        }

        // Fall back to backup provider
        $backup = $this->getProvider('backup');
        if ($backup !== null) {
            return $backup;
        }

        throw new \RuntimeException('No video generation provider available');
    }

    /**
     * Get provider status information
     */
    public function getProviderStatus(): array
    {
        $status = [];
        
        foreach ($this->providers as $name => $factory) {
            $provider = $factory();
            $status[$name] = [
                'name' => $provider->getName(),
                'display_name' => $provider->getDisplayName(),
                'enabled' => $provider->isEnabled(),
                'formats' => $provider->getSupportedFormats(),
                'presets' => $provider->getSupportedPresets(),
            ];
        }

        return $status;
    }

    /**
     * Get default provider name from config
     */
    public function getDefaultProviderName(): string
    {
        $config = Config::getInstance();
        $provider = $config->get('VIDEO_PROVIDER', 'kling');
        
        // Check if provider exists and is enabled
        $providerObj = $this->getProvider($provider);
        if ($providerObj === null || !$providerObj->isEnabled()) {
            // Fall back to backup
            return 'backup';
        }
        
        return $provider;
    }

    /**
     * Check if any provider is available
     */
    public function hasAvailableProvider(): bool
    {
        foreach ($this->providers as $factory) {
            $provider = $factory();
            if ($provider->isEnabled()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get provider by task ID prefix
     */
    public function getProviderByTaskId(string $taskId): ?VideoProviderInterface
    {
        if (str_starts_with($taskId, 'kling_')) {
            return $this->getProvider('kling');
        }
        
        if (str_starts_with($taskId, 'backup_')) {
            return $this->getProvider('backup');
        }
        
        return null;
    }
}
