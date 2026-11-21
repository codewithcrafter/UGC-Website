<?php
/**
 * MY UGC VIDEO — Dynamic Announcement Bar API
 * Returns active announcement bar settings in JSON format.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();

    // Query active settings, with automatic table creation safeguard for production migrations
    try {
        $stmt = $pdo->query("SELECT is_enabled, brand_text, promotional_message, bg_color, text_color, redirect_url, updated_at FROM announcement_settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $tableEx) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            brand_text VARCHAR(100) NOT NULL DEFAULT 'MY UGC VIDEO',
            promotional_message VARCHAR(255) NOT NULL DEFAULT 'Let\'s Create Videos That Make Your Brand Stand Out',
            bg_color VARCHAR(30) NOT NULL DEFAULT '#00221E',
            text_color VARCHAR(30) NOT NULL DEFAULT '#36D9B0',
            redirect_url VARCHAR(255) NOT NULL DEFAULT 'contact',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("INSERT IGNORE INTO announcement_settings (id, is_enabled, brand_text, promotional_message, bg_color, text_color, redirect_url) VALUES (1, 1, 'MY UGC VIDEO', 'Let\'s Create Videos That Make Your Brand Stand Out', '#00221E', '#36D9B0', 'contact')");

        $stmt = $pdo->query("SELECT is_enabled, brand_text, promotional_message, bg_color, text_color, redirect_url, updated_at FROM announcement_settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$settings) {
        // Fallback default settings if table empty
        $settings = [
            'is_enabled' => 1,
            'brand_text' => 'MY UGC VIDEO',
            'promotional_message' => "Let's Create Videos That Make Your Brand Stand Out",
            'bg_color' => '#00221E',
            'text_color' => '#36D9B0',
            'redirect_url' => 'contact',
            'updated_at' => date('Y-m-d H:i:s')
        ];
    } else {
        $settings['is_enabled'] = (int)$settings['is_enabled'];
    }

    echo json_encode([
        'success' => true,
        'data' => $settings
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load announcement settings'
    ]);
}
