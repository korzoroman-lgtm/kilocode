<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Logger;
use App\Core\Config;
use App\Providers\Payments\PaymentProviderFactory;

/**
 * Payment API Controller
 * JSON API for payment processing
 */
class PaymentApiController
{
    private Database $db;
    private Session $session;
    private Logger $logger;
    private Config $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
        $this->logger = Logger::getInstance();
        $this->config = Config::getInstance();
    }

    /**
     * List user's payments
     */
    public function list(): Response
    {
        $userId = $this->session->get('user_id');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $payments = $this->db->fetchAll(
            "SELECT p.*, o.items_json
             FROM payments p
             JOIN orders o ON p.order_id = o.id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );

        $total = $this->db->count('payments', ['user_id' => $userId]);

        return Response::success([
            'payments' => $payments,
            'page' => $page,
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    /**
     * Create payment
     */
    public function create(): Response
    {
        $userId = $this->session->get('user_id');
        $data = $_POST ?? [];

        $credits = (int) ($data['credits'] ?? 1);
        $paymentProvider = $data['provider'] ?? 'dummy';

        if ($credits < 1 || $credits > 100) {
            return Response::error('Invalid credits amount', 400);
        }

        // Calculate price (placeholder - implement actual pricing)
        $pricePerCredit = 1.00; // $1 per credit
        $totalAmount = $credits * $pricePerCredit;
        $currency = 'USD';

        // Create order
        $orderId = $this->db->insert('orders', [
            'user_id' => $userId,
            'status' => 'pending',
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'items_json' => json_encode([[
                'type' => 'credits',
                'amount' => $credits,
                'price' => $pricePerCredit,
            ]]),
        ]);

        // Create payment record
        $providerFactory = new PaymentProviderFactory();
        $provider = $providerFactory->getProvider($paymentProvider);

        if (!$provider || !$provider->isEnabled()) {
            // Fall back to dummy provider
            $provider = $providerFactory->getProvider('dummy');
        }

        try {
            $paymentResult = $provider->createPayment([
                'order_id' => $orderId,
                'user_id' => $userId,
                'amount' => $credits,
                'title' => 'Photo2Video Credits',
                'description' => "Purchase {$credits} credit(s)",
            ]);

            $paymentId = $this->db->insert('payments', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'provider' => $provider->getName(),
                'provider_payment_id' => $paymentResult['payment_id'] ?? null,
                'amount' => $totalAmount,
                'currency' => $currency,
                'status' => 'pending',
                'payment_data' => json_encode($paymentResult),
            ]);

            return Response::success([
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'amount' => $totalAmount,
                'currency' => $currency,
                'checkout_url' => $paymentResult['checkout_url'] ?? null,
                'provider' => $provider->getName(),
            ], 'Payment created');

        } catch (\Exception $e) {
            $this->logger->error('Payment creation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return Response::error('Payment initialization failed', 500);
        }
    }

    /**
     * View payment details
     */
    public function view(int $id): Response
    {
        $userId = $this->session->get('user_id');

        $payment = $this->db->fetchOne(
            "SELECT p.*, o.items_json
             FROM payments p
             JOIN orders o ON p.order_id = o.id
             WHERE p.id = ? AND p.user_id = ?",
            [$id, $userId]
        );

        if (!$payment) {
            return Response::error('Payment not found', 404);
        }

        return Response::success(['payment' => $payment]);
    }

    /**
     * Handle payment webhook/callback
     */
    public function webhook(): Response
    {
        $data = $_POST ?? [];
        $headers = getallheaders();

        // Determine provider from request
        $providerName = $this->detectProvider($data, $headers);

        $providerFactory = new PaymentProviderFactory();
        $provider = $providerFactory->getProvider($providerName);

        if (!$provider) {
            $this->logger->warning('Unknown payment provider in webhook', [
                'provider' => $providerName,
            ]);
            return Response::error('Unknown provider', 400);
        }

        try {
            $result = $provider->verifyWebhook($data, $headers);

            if (!$result['valid']) {
                $this->logger->warning('Invalid payment webhook', [
                    'provider' => $providerName,
                    'error' => $result['error'] ?? 'Unknown',
                ]);
                return Response::error('Invalid webhook: ' . ($result['error'] ?? 'Unknown'), 400);
            }

            // Process successful payment
            if ($result['status'] === 'succeeded') {
                $this->processSuccessfulPayment($result);
            }

            return Response::success(['processed' => true]);

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);
            return Response::error('Webhook processing failed', 500);
        }
    }

    /**
     * Detect payment provider from request
     */
    private function detectProvider(array $data, array $headers): string
    {
        // Check for provider-specific markers
        if (isset($data['payment_type']) && $data['payment_type'] === 'telegram') {
            return 'telegram';
        }

        // Default to dummy for testing
        return 'dummy';
    }

    /**
     * Process successful payment
     */
    private function processSuccessfulPayment(array $result): void
    {
        $paymentId = $result['payment_id'] ?? null;
        
        if (!$paymentId) {
            $this->logger->error('No payment ID in webhook result');
            return;
        }

        // Find payment record
        $payment = $this->db->fetchOne(
            "SELECT * FROM payments WHERE provider_payment_id = ?",
            [$paymentId]
        );

        if (!$payment) {
            $this->logger->error('Payment not found for webhook', [
                'provider_payment_id' => $paymentId,
            ]);
            return;
        }

        if ($payment['status'] === 'succeeded') {
            // Already processed
            return;
        }

        // Update payment status
        $this->db->update('payments', [
            'status' => 'succeeded',
            'paid_at' => date('Y-m-d H:i:s'),
            'payment_data' => json_encode($result),
        ], ['id' => $payment['id']]);

        // Update order status
        $this->db->update('orders', [
            'status' => 'completed',
        ], ['id' => $payment['order_id']]);

        // Get order details
        $order = $this->db->fetchOne("SELECT * FROM orders WHERE id = ?", [$payment['order_id']]);
        $items = json_decode($order['items_json'] ?? '[]', true);

        // Find credits item
        $creditsAmount = 0;
        foreach ($items as $item) {
            if ($item['type'] === 'credits') {
                $creditsAmount = $item['amount'];
                break;
            }
        }

        // Add credits to user
        if ($creditsAmount > 0) {
            $user = $this->db->fetchOne("SELECT credits FROM users WHERE id = ?", [$payment['user_id']]);
            $newBalance = ($user['credits'] ?? 0) + $creditsAmount;

            $this->db->update('users', [
                'credits' => $newBalance,
            ], ['id' => $payment['user_id']]);

            // Log credit addition
            $this->db->insert('credit_ledger', [
                'user_id' => $payment['user_id'],
                'type' => 'credit',
                'amount' => $creditsAmount,
                'balance' => $newBalance,
                'description' => 'Payment received',
                'reference_type' => 'payment',
                'reference_id' => $payment['id'],
            ]);
        }

        $this->logger->info('Payment processed successfully', [
            'payment_id' => $payment['id'],
            'user_id' => $payment['user_id'],
            'amount' => $payment['amount'],
        ]);
    }
}
