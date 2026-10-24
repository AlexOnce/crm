-- ========================================================
-- SCRIPT DE ACTUALIZACIÓN DE ESTRUCTURA PARA CPANEL
-- Fecha: 24/10/2026
-- Base de datos: kqlurhnh_crm_whatsapp
-- ========================================================

-- 1. Agregar campo PNR / booking_reference a broadcast_recipients
ALTER TABLE `broadcast_recipients` 
  ADD COLUMN IF NOT EXISTS `booking_reference` VARCHAR(50) NULL AFTER `flight_number`;

ALTER TABLE `broadcast_recipients` 
  ADD INDEX IF NOT EXISTS `broadcast_recipients_booking_reference_index` (`booking_reference`);

-- 2. Crear tabla call_center_broadcasts si no existe
CREATE TABLE IF NOT EXISTS `call_center_broadcasts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `audio_path` varchar(255) DEFAULT NULL,
  `audio_format` varchar(20) NOT NULL DEFAULT 'gsm',
  `total_recipients` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sent_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pending_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `call_center_broadcasts_user_id_foreign` (`user_id`),
  KEY `call_center_broadcasts_status_index` (`status`),
  KEY `call_center_broadcasts_created_at_index` (`created_at`),
  CONSTRAINT `call_center_broadcasts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Crear tabla call_center_recipients si no existe
CREATE TABLE IF NOT EXISTS `call_center_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `broadcast_id` bigint(20) UNSIGNED NOT NULL,
  `phone` varchar(30) NOT NULL,
  `recipient_name` varchar(150) DEFAULT NULL,
  `custom_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_data`)),
  `channel_used` varchar(50) DEFAULT NULL,
  `call_unique_id` varchar(100) DEFAULT NULL,
  `call_duration` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `call_status` varchar(30) NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `called_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `call_center_recipients_broadcast_id_foreign` (`broadcast_id`),
  KEY `call_center_recipients_phone_index` (`phone`),
  KEY `call_center_recipients_call_status_index` (`call_status`),
  CONSTRAINT `call_center_recipients_broadcast_id_foreign` FOREIGN KEY (`broadcast_id`) REFERENCES `call_center_broadcasts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Registrar migraciones en la tabla migrations de Laravel
INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES 
  ('2026_10_20_130000_create_call_center_broadcasts_tables', (SELECT IFNULL(MAX(batch), 0) + 1 FROM `migrations` m)),
  ('2026_10_24_140648_add_booking_reference_to_broadcast_recipients_table', (SELECT IFNULL(MAX(batch), 0) + 1 FROM `migrations` m));
