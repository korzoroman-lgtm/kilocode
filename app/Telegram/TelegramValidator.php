<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Core\Config;
use App\Core\Logger;

/**
 * Telegram initData Validator
 * Validates Telegram Web App authentication data
 * 
 * @link https://core.telegram.org/bots/webapps#validating-data
 */
class TelegramValidator
{
    private Config $config;
    private Logger $logger;
    private string $botToken;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
        $this->botToken = $this->config->get('TELEGRAM_BOT_TOKEN', '');
    }

    /**
     * Validate initData string from Telegram Web App
     * 
     * @param string $initData Raw initData string from Telegram
     * @param int|null $userId Optional user ID to match
     * @return array Validation result with user data
     */
    public function validateInitData(string $initData, ?int $userId = null): array
    {
        $this->logger->debug('Validating Telegram initData');

        // Parse initData
        parse_str($initData, $data);

        if (empty($data)) {
            return [
                'valid' => false,
                'error' => 'Invalid initData format',
            ];
        }

        // Check required fields
        $requiredFields = ['hash', 'user'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return [
                    'valid' => false,
                    'error' => 'Missing required field: ' . $field,
                ];
            }
        }

        // Extract hash
        $providedHash = $data['hash'];
        unset($data['hash']);

        // Calculate expected hash
        $expectedHash = $this->calculateHash($data);

        if (!hash_equals($expectedHash, $providedHash)) {
            $this->logger->warning('Telegram hash mismatch', [
                'expected' => $expectedHash,
                'provided' => $providedHash,
            ]);
            
            return [
                'valid' => false,
                'error' => 'Invalid hash signature',
            ];
        }

        // Check auth date (not older than 24 hours)
        if (isset($data['auth_date'])) {
            $authDate = (int) $data['auth_date'];
            $maxAge = 86400; // 24 hours in seconds
            
            if ((time() - $authDate) > $maxAge) {
                return [
                    'valid' => false,
                    'error' => 'initData expired (older than 24 hours)',
                ];
            }
        }

        // Parse user data
        $user = json_decode($data['user'] ?? '{}', true);

        // Validate user ID if provided
        if ($userId !== null && isset($user['id']) && $user['id'] !== $userId) {
            return [
                'valid' => false,
                'error' => 'User ID mismatch',
            ];
        }

        return [
            'valid' => true,
            'user' => $user,
            'data' => $data,
        ];
    }

    /**
     * Calculate HMAC-SHA256 hash for initData validation
     */
    private function calculateHash(array $data): string
    {
        // Sort keys alphabetically
        ksort($data);

        // Create data check string
        $dataCheckString = [];
        foreach ($data as $key => $value) {
            $dataCheckString[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckString);

        // Calculate HMAC-SHA256
        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return $hash;
    }

    /**
     * Extract user data from initData without full validation
     * (Use only for non-critical operations)
     */
    public function extractUserData(string $initData): ?array
    {
        parse_str($initData, $data);
        
        if (!isset($data['user'])) {
            return null;
        }

        return json_decode($data['user'], true);
    }

    /**
     * Check if initData is present and potentially valid
     */
    public function hasValidInitData(string $initData): bool
    {
        if (empty($initData)) {
            return false;
        }

        $result = $this->validateInitData($initData);
        return $result['valid'];
    }
}
