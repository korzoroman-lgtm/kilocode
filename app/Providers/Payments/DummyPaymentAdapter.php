<?php

declare(strict_types=1);

namespace App\Providers\Payments;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

/**
 * Dummy/Manual Payment Adapter
 * For testing and manual payment processing
 */
class DummyPaymentAdapter implements PaymentProviderInterface
{
    private Config $config;
    private Logger $logger;
    private Database $db;
    private bool $enabled;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
        $this->db = Database::getInstance();
        // Always enabled for testing
        $this->enabled = true;
    }

    public function getName(): string
    {
        return 'dummy';
    }

    public function getDisplayName(): string
    {
        return 'Manual / Test Payments';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function createPayment(array $params): array
    {
        $this->logger->info('Creating dummy payment', ['params' => $params]);

        $paymentId = 'dummy_' . uniqid();

        return [
            'payment_id' => $paymentId,
            'status' => 'pending',
            'type' => 'manual',
            'checkout_url' => '#manual-' . $paymentId,
            'message' => 'Payment created. Mark as paid manually via admin panel.',
            'raw_response' => [
                'payment_id' => $paymentId,
                'created_at' => date('c'),
                'provider' => 'dummy',
            ],
        ];
    }

    public function verifyWebhook(array $payload, array $headers = []): array
    {
        $this->logger->info('Dummy payment webhook received', ['payload' => $payload]);

        // Dummy payments are verified manually
        return [
            'valid' => true,
            'status' => 'pending',
            'message' => 'Manual verification required',
        ];
    }

    public function getPaymentStatus(string $paymentId): array
    {
        // Look up payment in database
        $payment = $this->db->fetchOne(
            "SELECT * FROM payments WHERE provider_payment_id = ?",
            [$paymentId]
        );

        if (!$payment) {
            return [
                'payment_id' => $paymentId,
                'status' => 'not_found',
            ];
        }

        return [
            'payment_id' => $paymentId,
            'status' => $payment['status'],
            'amount' => (float) $payment['amount'],
            'currency' => $payment['currency'],
        ];
    }

    public function refund(string $paymentId, ?float $amount = null): array
    {
        $this->logger->info('Processing dummy refund', [
            'payment_id' => $paymentId,
            'amount' => $amount,
        ]);

        return [
            'refund_id' => 'refund_dummy_' . uniqid(),
            'status' => 'succeeded',
            'message' => 'Dummy refund processed',
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'RUB' => 'Russian Ruble',
        ];
    }

    public function getSupportedCountries(): array
    {
        return [
            'test' => 'Test Mode (All Countries)',
        ];
    }

    /**
     * Manually mark payment as succeeded (for admin use)
     */
    public function markAsPaid(string $paymentId, array $metadata = []): array
    {
        $this->logger->info('Marking dummy payment as paid', [
            'payment_id' => $paymentId,
            'metadata' => $metadata,
        ]);

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'status' => 'succeeded',
            'marked_at' => date('c'),
        ];
    }

    /**
     * Create test payment for development
     */
    public function createTestPayment(int $userId, int $orderId, float $amount, string $currency = 'USD'): array
    {
        $this->logger->info('Creating test payment', [
            'user_id' => $userId,
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        $paymentId = 'test_' . uniqid();

        return [
            'payment_id' => $paymentId,
            'status' => 'succeeded', // Auto-complete for testing
            'amount' => $amount,
            'currency' => $currency,
            'user_id' => $userId,
            'order_id' => $orderId,
        ];
    }
}
