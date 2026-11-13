<?php
/**
 * Create Pre-Implementation Backups for Direct Markdown Routing
 */

$timestamp = date('Ymd_His');
$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

$filesToBackup = [
    '.htaccess' => ".htaccess.bak_{$timestamp}",
    'config/config.php' => "config_config.php.bak_{$timestamp}",
    'includes/llms-sync.php' => "includes_llms_sync.php.bak_{$timestamp}",
    'admin/markdown/index.php' => "admin_markdown_index.php.bak_{$timestamp}",
    'markdown/view.php' => "markdown_view.php.bak_{$timestamp}"
];

$root = dirname(__DIR__);
foreach ($filesToBackup as $srcRel => $bakName) {
    $srcPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $srcRel);
    $dstPath = $backupDir . DIRECTORY_SEPARATOR . $bakName;
    if (file_exists($srcPath)) {
        copy($srcPath, $dstPath);
        echo "Backed up {$srcRel} -> database/backups/{$bakName}\n";
    }
}

echo "\nBackup complete.\n";

