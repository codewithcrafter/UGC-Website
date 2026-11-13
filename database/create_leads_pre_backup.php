<?php
/**
 * Create Pre-Leads Implementation Database & Configuration Backup
 */

require_once __DIR__ . '/../config/config.php';

$pdo = Database::getConnection();
$timestamp = date('Ymd_His');
$backupDir = CMS_ROOT . '/database/backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// 1. Dump all database tables to JSON
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$dbDump = [];

foreach ($tables as $t) {
    $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
    $dbDump[$t] = $rows;
}

$dbDumpFile = $backupDir . "/pre_leads_backup_{$timestamp}.json";
file_put_contents($dbDumpFile, json_encode($dbDump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "[PASS] Database tables dumped to: " . basename($dbDumpFile) . " (" . count($tables) . " tables)\n";

// 2. Backup key files that will be touched
$filesToBackup = [
    '.htaccess',
    'config/config.php',
    'admin/includes/header.php',
    'js/main.js',
    'contact.html',
    'index.html'
];

foreach ($filesToBackup as $f) {
    $src = CMS_ROOT . '/' . $f;
    if (file_exists($src)) {
        $safeName = str_replace(['/', '\\'], '_', $f);
        $dest = $backupDir . "/{$safeName}.bak_{$timestamp}";
        copy($src, $dest);
        echo "[PASS] Backed up {$f} -> " . basename($dest) . "\n";
    }
}

echo "=== PRE-IMPLEMENTATION BACKUPS COMPLETE ===\n";

