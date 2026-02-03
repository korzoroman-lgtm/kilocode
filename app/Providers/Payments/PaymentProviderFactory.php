<?php

declare(strict_types=1);

namespace App\Providers\Payments;

use App\Core\Config;

/**
 * Payment Provider Factory
 * Creates and manages payment providers
 */
class PaymentProviderFactory
{
    private static ?self $instance = null;
    private array $providers = [];

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
        // Register Telegram provider
        $this->providers['telegram'] = function () {
            return new TelegramPaymentAdapter();
        };

        // Register Dummy provider (always available)
        $this->providers['dummy'] = function () {
            return new DummyPaymentAdapter();
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
    public function getProvider(string $name): ?PaymentProviderInterface
    {
        $name = strtolower($name);
        
        if (!isset($this->providers[$name])) {
            return null;
        }

        return ($this->providers[$name])();
    }

    /**
     * Get the configured provider from settings
     */
    public function getConfiguredProvider(): PaymentProviderInterface
    {
        $config = Config::getInstance();
        $providerName = $config->get('PAYMENT_PROVIDER', 'dummy');
        
        $provider = $this->getProvider($providerName);
        
        if ($provider === null) {
            // Fall back to dummy provider
            return $this->getProvider('dummy');
        }
        
        return $provider;
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
                'currencies' => $provider->getSupportedCurrencies(),
                'countries' => $provider->getSupportedCountries(),
            ];
        }

        return $status;
    }

    /**
     * Check if any provider is available for payments
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
     * Create a payment with the configured provider
     */
    public function createPayment(array $params): array
    {
        return $this->getConfiguredProvider()->createPayment($params);
    }

    /**
     * Verify webhook with appropriate provider
     */
    public function verifyWebhook(string $providerName, array $payload, array $headers = []): array
    {
        $provider = $this->getProvider($providerName);
        
        if ($provider === null) {
            return [
                'valid' => false,
                'error' => 'Unknown provider: ' . $providerName,
            ];
        }
        
        return $provider->verifyWebhook($payload, $headers);
    }
}
