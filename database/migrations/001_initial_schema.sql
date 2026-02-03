-- Migration: Initial Schema
-- Created: 2024-01-01
-- Description: Creates all initial tables for the Photo2Video application

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Users Table
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `avatar` VARCHAR(500) DEFAULT NULL,
    `telegram_id` BIGINT UNSIGNED DEFAULT NULL UNIQUE,
    `telegram_username` VARCHAR(100) DEFAULT NULL,
    `telegram_chat_id` BIGINT UNSIGNED DEFAULT NULL,
    `telegram_notifications` TINYINT(1) DEFAULT 1,
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `credits` INT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `email_verified` TINYINT(1) DEFAULT 0,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_telegram_id` (`telegram_id`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Password Reset Tokens Table
-- ============================================
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(100) NOT NULL UNIQUE,
    `expires_at` TIMESTAMP NOT NULL,
    `used_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`),
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Projects Table
-- ============================================
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('draft', 'processing', 'completed', 'failed') DEFAULT 'draft',
    `format` ENUM('16:9', '9:16', '1:1') DEFAULT '16:9',
    `preset` VARCHAR(50) DEFAULT 'default',
    `settings` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Videos Table
-- ============================================
CREATE TABLE IF NOT EXISTS `videos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `original_image` VARCHAR(500) NOT NULL,
    `result_video` VARCHAR(500) DEFAULT NULL,
    `thumbnail` VARCHAR(500) DEFAULT NULL,
    `duration` FLOAT DEFAULT NULL,
    `format` ENUM('16:9', '9:16', '1:1') NOT NULL,
    `preset` VARCHAR(50) NOT NULL DEFAULT 'default',
    `settings` JSON DEFAULT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `visibility` ENUM('private', 'unlisted', 'public') DEFAULT 'private',
    `is_nsfw` TINYINT(1) DEFAULT 0,
    `is_featured` TINYINT(1) DEFAULT 0,
    `view_count` INT UNSIGNED DEFAULT 0,
    `like_count` INT UNSIGNED DEFAULT 0,
    `download_count` INT UNSIGNED DEFAULT 0,
    `title` VARCHAR(200) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_visibility` (`visibility`),
    INDEX `idx_is_featured` (`is_featured`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_videos_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_videos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Generation Jobs Table
-- ============================================
CREATE TABLE IF NOT EXISTS `generation_jobs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `video_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `provider` VARCHAR(50) NOT NULL,
    `provider_task_id` VARCHAR(200) DEFAULT NULL,
    `status` ENUM('queued', 'processing', 'succeeded', 'failed') DEFAULT 'queued',
    `input_params` JSON DEFAULT NULL,
    `result_data` JSON DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `attempts` INT UNSIGNED DEFAULT 0,
    `max_attempts` INT UNSIGNED DEFAULT 3,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_video_id` (`video_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_provider_task_id` (`provider_task_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_provider` (`provider`),
    CONSTRAINT `fk_generation_jobs_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_generation_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Credit Ledger Table
-- ============================================
CREATE TABLE IF NOT EXISTS `credit_ledger` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` ENUM('credit', 'debit') NOT NULL,
    `amount` INT UNSIGNED NOT NULL,
    `balance` INT UNSIGNED NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_reference` (`reference_type`, `reference_id`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_credit_ledger_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Orders Table
-- ============================================
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    `total_amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `items_json` JSON NOT NULL,
    `metadata` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Payments Table
-- ============================================
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `provider` VARCHAR(50) NOT NULL,
    `provider_payment_id` VARCHAR(200) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `status` ENUM('pending', 'processing', 'succeeded', 'failed', 'refunded') DEFAULT 'pending',
    `payment_data` JSON DEFAULT NULL,
    `receipt_url` VARCHAR(500) DEFAULT NULL,
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_provider_payment_id` (`provider_payment_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Video Likes Table
-- ============================================
CREATE TABLE IF NOT EXISTS `video_likes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `video_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_video_user` (`video_id`, `user_id`),
    INDEX `idx_video_id` (`video_id`),
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_video_likes_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_video_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Video Reports Table
-- ============================================
CREATE TABLE IF NOT EXISTS `video_reports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `video_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `reason` ENUM('spam', 'copyright', 'nsfw', 'harassment', 'other') NOT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'reviewed', 'dismissed', 'resolved') DEFAULT 'pending',
    `admin_note` TEXT DEFAULT NULL,
    `reviewed_by` INT UNSIGNED DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_video_id` (`video_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_reason` (`reason`),
    CONSTRAINT `fk_video_reports_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- API Tokens Table
-- ============================================
CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `permissions` JSON DEFAULT NULL,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`),
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Rate Limit Table
-- ============================================
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    `attempts` INT UNSIGNED DEFAULT 0,
    `window_start` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_key` (`key`),
    INDEX `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
