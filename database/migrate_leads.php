<?php
/**
 * MY UGC VIDEO — Lead Management System Migration
 * 
 * Creates the `leads` table with appropriate indexes and status constraints.
 * Safe and repeatable (idempotent).
 */

require_once __DIR__ . '/../config/config.php';

$pdo = Database::getConnection();

echo "=== MIGRATING LEADS TABLE ===\n";

$sql = "
CREATE TABLE IF NOT EXISTS `leads` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(50) NULL DEFAULT NULL,
  `brand` VARCHAR(150) NULL DEFAULT NULL,
  `service` VARCHAR(100) NULL DEFAULT NULL,
  `budget` VARCHAR(100) NULL DEFAULT NULL,
  `message` TEXT NOT NULL,
  `source` VARCHAR(60) NOT NULL,
  `status` ENUM('new', 'contacted', 'converted', 'closed') NOT NULL DEFAULT 'new',
  `internal_notes` TEXT NULL DEFAULT NULL,
  `email_delivery_status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  `email_attempts` INT(11) NOT NULL DEFAULT 0,
  `last_email_attempt_at` DATETIME NULL DEFAULT NULL,
  `email_error_message` TEXT NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_email` (`email`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$pdo->exec($sql);
echo "[PASS] Table `leads` created or verified successfully.\n";

// Verify table columns
$cols = $pdo->query("SHOW COLUMNS FROM `leads`")->fetchAll(PDO::FETCH_COLUMN);
echo "Columns in `leads`: " . implode(', ', $cols) . "\n";
echo "=== LEADS MIGRATION COMPLETE ===\n";

