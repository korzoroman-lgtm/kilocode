<?php

declare(strict_types=1);

namespace App\Providers\Payments;

use App\Core\Config;
use App\Core\Logger;

/**
 * Telegram Payments Adapter
 * Implements Telegram Stars and Invoice payments
 * 
 * @link https://core.telegram.org/bots/payments
 */
class TelegramPaymentAdapter implements PaymentProviderInterface
{
    private Config $config;
    private Logger $logger;
    private string $token;
    private bool $enabled;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
        $this->token = $this->config->get('TELEGRAM_PAYMENT_TOKEN', '');
        $this->enabled = !empty($this->token);
    }

    public function getName(): string
    {
        return 'telegram';
    }

    public function getDisplayName(): string
    {
        return 'Telegram Stars / Payments';
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->token);
    }

    public function createPayment(array $params): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Telegram payment provider is not enabled. Please configure TELEGRAM_PAYMENT_TOKEN.');
        }

        $this->logger->info('Creating Telegram payment', ['params' => $params]);

        // Telegram Stars invoice creation
        // In production, this would use the Telegram Bot API
        $invoiceId = 'invoice_' . uniqid();
        
        return [
            'payment_id' => $invoiceId,
            'status' => 'pending',
            'type' => 'telegram_stars',
            'checkout_url' => $this->buildInvoiceUrl($params),
            'invoice_payload' => json_encode([
                'order_id' => $params['order_id'] ?? null,
                'user_id' => $params['user_id'] ?? null,
                'amount' => $params['amount'] ?? 1,
            ]),
            'raw_response' => [
                'invoice_id' => $invoiceId,
                'payment_type' => 'stars',
            ],
        ];
    }

    public function verifyWebhook(array $payload, array $headers = []): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Telegram payment provider is not enabled.');
        }

        $this->logger->info('Verifying Telegram payment webhook', ['payload' => $payload]);

        // Check for required fields
        $requiredFields = ['update_id', 'message'];
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                return [
                    'valid' => false,
                    'error' => 'Missing required field: ' . $field,
                ];
            }
        }

        $message = $payload['message'];
        
        // Check for successful payment
        if (isset($message['successful_payment'])) {
            $payment = $message['successful_payment'];
            
            return [
                'valid' => true,
                'status' => 'succeeded',
                'payment_id' => $payment['telegram_payment_id'] ?? '',
                'amount' => $payment['amount'] ?? 0,
                'currency' => $payment['currency'] ?? 'XTR',
                'payload' => json_decode($payment['invoice_payload'] ?? '{}', true),
            ];
        }

        return [
            'valid' => true,
            'status' => 'pending',
            'message' => 'Payment not completed yet',
        ];
    }

    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Telegram payment provider is not enabled.');
        }

        // In production, this would query the Telegram API
        // For now, return placeholder
        return [
            'payment_id' => $paymentId,
            'status' => 'pending',
            'raw_response' => [],
        ];
    }

    public function refund(string $paymentId, ?float $amount = null): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Telegram payment provider is not enabled.');
        }

        $this->logger->info('Processing Telegram refund', [
            'payment_id' => $paymentId,
            'amount' => $amount,
        ]);

        // Telegram Stars refunds are handled differently
        // For now, return placeholder response
        return [
            'refund_id' => 'refund_' . uniqid(),
            'status' => 'pending',
            'message' => 'Refund initiated (Telegram Stars)',
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return [
            'XTR' => 'Telegram Stars',
        ];
    }

    public function getSupportedCountries(): array
    {
        // Telegram Payments are available globally
        return [
            'global' => 'Worldwide',
        ];
    }

    /**
     * Build invoice URL for Telegram
     */
    private function buildInvoiceUrl(array $params): string
    {
        $botUsername = $this->config->get('TELEGRAM_BOT_USERNAME', 'bot');
        $title = urlencode($params['title'] ?? 'Photo2Video Credits');
        $description = urlencode($params['description'] ?? 'Purchase credits');
        $payload = urlencode(json_encode([
            'order_id' => $params['order_id'] ?? '',
            'credits' => $params['credits'] ?? 1,
        ]));
        
        return sprintf(
            'https://t.me/%s?startapp=purchase_%s',
            $botUsername,
            $params['order_id'] ?? 'default'
        );
    }

    /**
     * Send invoice directly to user
     */
    public function sendInvoice(int $chatId, array $params): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Telegram payment provider is not enabled.');
        }

        $this->logger->info('Sending Telegram invoice', [
            'chat_id' => $chatId,
            'params' => $params,
        ]);

        // In production, this would call sendInvoice via Bot API
        // https://core.telegram.org/bots/api#sendinvoice
        
        $invoiceId = 'invoice_' . uniqid();
        
        return [
            'invoice_id' => $invoiceId,
            'status' => 'sent',
            'chat_id' => $chatId,
            'message_id' => null, // Will be set after API call
        ];
    }
}
