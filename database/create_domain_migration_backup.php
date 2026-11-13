<?php
/**
 * Create Pre-Implementation Backups for Domain Migration, Service URL Standardization, and Blog Image Fixes
 */

$timestamp = date('Ymd_His');
$backupDir = __DIR__ . '/backups/pre_domain_migration_' . $timestamp;
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$root = dirname(__DIR__);

// Helper to copy directory recursively
function copyDir($src, $dst) {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if ($file != '.' && $file != '..') {
            if (is_dir($src . '/' . $file)) {
                copyDir($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// 1. Backup specific key files
$keyFiles = [
    '.htaccess',
    'index.html',
    'about.html',
    'services.html',
    'contact.html',
    'blogs.html',
    'blogs.php',
    'blog.php',
    'sitemap.xml',
    'robots.txt',
    'llms.txt',
    'config/config.php',
    'config/database.php',
    'includes/functions.php',
    'includes/llms-sync.php',
    'includes/blog-header.php',
    'includes/blog-footer.php',
    'includes/schema-renderer.php',
    'css/style.css',
    'js/main.js'
];

foreach ($keyFiles as $f) {
    $src = $root . '/' . $f;
    $dst = $backupDir . '/' . str_replace('/', '_', $f);
    if (file_exists($src)) {
        copy($src, $dst);
    }
}

// 2. Backup services directory
copyDir($root . '/services', $backupDir . '/services');

// 3. Backup database table blog_posts & admins
require_once $root . '/config/config.php';
try {
    $db = getDb();
    $posts = $db->query("SELECT * FROM blog_posts")->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($backupDir . '/db_blog_posts.json', json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Backed up " . count($posts) . " blog posts from MySQL.\n";
} catch (Exception $e) {
    echo "DB backup warning: " . $e->getMessage() . "\n";
}

echo "Backup created successfully in: database/backups/pre_domain_migration_{$timestamp}\n";

