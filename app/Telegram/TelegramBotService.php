<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

/**
 * Telegram Bot Service
 * Handles sending notifications and messages via Telegram Bot API
 */
class TelegramBotService
{
    private Config $config;
    private Logger $logger;
    private Database $db;
    private string $botToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
        $this->db = Database::getInstance();
        $this->botToken = $this->config->get('TELEGRAM_BOT_TOKEN', '');
        $this->apiUrl = 'https://api.telegram.org/bot' . $this->botToken;
    }

    /**
     * Check if bot is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken);
    }

    /**
     * Send notification to user about video completion
     */
    public function sendVideoReadyNotification(int $chatId, array $videoInfo): bool
    {
        $message = $this->buildVideoReadyMessage($videoInfo);
        
        return $this->sendMessage($chatId, $message, [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->buildVideoButtons($videoInfo),
        ]);
    }

    /**
     * Send message to user
     */
    public function sendMessage(int $chatId, string $text, array $options = []): bool
    {
        if (!$this->isConfigured()) {
            $this->logger->warning('Telegram bot not configured, skipping message');
            return false;
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options);

        $response = $this->makeRequest('sendMessage', $payload);

        if ($response['ok'] ?? false) {
            $this->logger->info('Telegram message sent', ['chat_id' => $chatId]);
            return true;
        }

        $this->logger->error('Failed to send Telegram message', [
            'chat_id' => $chatId,
            'error' => $response['description'] ?? 'Unknown error',
        ]);
        return false;
    }

    /**
     * Send photo to user
     */
    public function sendPhoto(int $chatId, string $photoUrl, string $caption = '', array $options = []): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        $response = $this->makeRequest('sendPhoto', $payload);

        return $response['ok'] ?? false;
    }

    /**
     * Send invoice for credits
     */
    public function sendInvoice(int $chatId, array $invoiceData): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Bot not configured',
            ];
        }

        $payload = [
            'chat_id' => $chatId,
            'title' => $invoiceData['title'] ?? 'Photo2Video Credits',
            'description' => $invoiceData['description'] ?? 'Purchase video generation credits',
            'payload' => json_encode($invoiceData['payload'] ?? []),
            'provider_token' => $this->config->get('TELEGRAM_PAYMENT_TOKEN', ''),
            'currency' => 'XTR', // Telegram Stars
            'prices' => json_encode([[
                'label' => $invoiceData['label'] ?? 'Credits',
                'amount' => $invoiceData['amount'] ?? 1,
            ]]),
        ];

        $response = $this->makeRequest('sendInvoice', $payload);

        return [
            'success' => $response['ok'] ?? false,
            'response' => $response,
        ];
    }

    /**
     * Answer callback query (for inline button clicks)
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $payload = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ];

        $response = $this->makeRequest('answerCallbackQuery', $payload);
        return $response['ok'] ?? false;
    }

    /**
     * Get chat info
     */
    public function getChat(int $chatId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $response = $this->makeRequest('getChat', ['chat_id' => $chatId]);
        return $response['result'] ?? null;
    }

    /**
     * Make API request to Telegram
     */
    private function makeRequest(string $method, array $payload): array
    {
        $url = $this->apiUrl . '/' . $method;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            $this->logger->error('Telegram API error', [
                'method' => $method,
                'error' => $error,
            ]);
            return ['ok' => false, 'description' => $error];
        }

        return json_decode($response, true) ?? ['ok' => false];
    }

    /**
     * Build video ready message
     */
    private function buildVideoReadyMessage(array $videoInfo): string
    {
        $title = htmlspecialchars($videoInfo['title'] ?? 'Your Video');
        
        return <<<HTML
🎬 <b>Video Ready!</b>

Your video "<b>{$title}</b>" has been generated successfully.

Click the button below to watch and share!
HTML;
    }

    /**
     * Build video action buttons
     */
    private function buildVideoButtons(array $videoInfo): array
    {
        $videoUrl = $videoInfo['url'] ?? '#';
        $shareUrl = $videoInfo['share_url'] ?? '#';

        return json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '▶️ Watch Video', 'url' => $videoUrl],
                    ['text' => '🔗 Share', 'url' => $shareUrl],
                ],
                [
                    ['text' => '📥 Download', 'callback_data' => 'download_' . ($videoInfo['id'] ?? 0)],
                ],
            ],
        ]);
    }

    /**
     * Update user's Telegram chat ID
     */
    public function updateUserChatId(int $userId, int $chatId): bool
    {
        return $this->db->update('users', [
            'telegram_chat_id' => $chatId,
        ], ['id' => $userId]) > 0;
    }

    /**
     * Send notification to user by their user ID
     */
    public function notifyUserById(int $userId, string $message): bool
    {
        $user = $this->db->fetchOne(
            "SELECT telegram_chat_id, telegram_notifications FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user || empty($user['telegram_chat_id']) || !$user['telegram_notifications']) {
            return false;
        }

        return $this->sendMessage((int) $user['telegram_chat_id'], $message);
    }
}
