<?php
/**
 * MY UGC VIDEO - XML Sitemap Generator / Sync
 * 
 * Synchronizes sitemap.xml with all published blog posts in the MySQL database.
 * Uses centralized, validated syncSitemapXml() engine.
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';
require_once CMS_ROOT . '/includes/llms-sync.php';

$res = syncSitemapXml();
if ($res['success']) {
    echo "Sitemap updated successfully with " . ($res['url_count'] ?? 0) . " published pages and articles.\n";
} else {
    echo "Sitemap update failed: " . ($res['error'] ?? 'Unknown error') . "\n";
    exit(1);
}
