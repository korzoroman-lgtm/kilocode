<?php

declare(strict_types=1);

namespace App\Providers\Payments;

/**
 * Payment Provider Interface
 * Abstract interface for payment providers
 */
interface PaymentProviderInterface
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
     * Create a payment intent/invoice
     * 
     * @param array $params Payment parameters
     * @return array Payment info with ID and checkout URL
     */
    public function createPayment(array $params): array;

    /**
     * Verify and process webhook/callback
     * 
     * @param array $payload Webhook payload
     * @param array $headers Request headers
     * @return array Verification result
     */
    public function verifyWebhook(array $payload, array $headers = []): array;

    /**
     * Get payment status
     * 
     * @param string $paymentId Provider's payment ID
     * @return array Payment status info
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Process refund
     * 
     * @param string $paymentId Provider's payment ID
     * @param float|null $amount Refund amount (null for full refund)
     * @return array Refund result
     */
    public function refund(string $paymentId, ?float $amount = null): array;

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get supported countries/regions
     */
    public function getSupportedCountries(): array;
}
