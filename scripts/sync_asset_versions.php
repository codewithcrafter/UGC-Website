<?php
/**
 * MY UGC VIDEO — Static Asset Version Synchronization Script
 * 
 * Synchronizes version query strings (?v=<filemtime>) across all static HTML files
 * (root HTML, services/*.html, blogs/*.html) to match the current file modification
 * timestamps of css/style.css, js/main.js, and js/quick-enquiry.js.
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/includes/functions.php';

$cssVer = getAssetVersion('css/style.css');
$mainJsVer = getAssetVersion('js/main.js');
$qeJsVer = getAssetVersion('js/quick-enquiry.js');

echo "Current Asset Timestamps:" . PHP_EOL;
echo "  css/style.css:        $cssVer" . PHP_EOL;
echo "  js/main.js:           $mainJsVer" . PHP_EOL;
echo "  js/quick-enquiry.js:  $qeJsVer" . PHP_EOL . PHP_EOL;

$targetFiles = [];

// 1. Root HTML files
foreach (['index.html', 'about.html', 'services.html', 'contact.html', 'blogs.html', '404.html'] as $file) {
    $path = CMS_ROOT . '/' . $file;
    if (file_exists($path)) {
        $targetFiles[] = $path;
    }
}

// 2. Services HTML files
$servicesDir = CMS_ROOT . '/services';
if (is_dir($servicesDir)) {
    foreach (glob($servicesDir . '/*.html') as $file) {
        $targetFiles[] = $file;
    }
}

// 3. Blogs HTML files
$blogsDir = CMS_ROOT . '/blogs';
if (is_dir($blogsDir)) {
    foreach (glob($blogsDir . '/*.html') as $file) {
        $targetFiles[] = $file;
    }
}

$updatedCount = 0;

foreach ($targetFiles as $filePath) {
    $content = file_get_contents($filePath);
    $relName = str_replace(CMS_ROOT . DIRECTORY_SEPARATOR, '', $filePath);

    // Replace style.css version
    $updated = preg_replace_callback(
        '/(<link\b[^>]*\bhref=["\'])([^"\']*?css\/style\.css)(?:\?[^"\']*)?(["\'])/i',
        function ($matches) use ($cssVer) {
            return $matches[1] . $matches[2] . '?v=' . $cssVer . $matches[3];
        },
        $content
    );

    // Replace main.js version
    $updated = preg_replace_callback(
        '/(<script\b[^>]*\bsrc=["\'])([^"\']*?js\/main\.js)(?:\?[^"\']*)?(["\'])/i',
        function ($matches) use ($mainJsVer) {
            return $matches[1] . $matches[2] . '?v=' . $mainJsVer . $matches[3];
        },
        $updated
    );

    // Replace quick-enquiry.js version
    $updated = preg_replace_callback(
        '/(<script\b[^>]*\bsrc=["\'])([^"\']*?js\/quick-enquiry\.js)(?:\?[^"\']*)?(["\'])/i',
        function ($matches) use ($qeJsVer) {
            return $matches[1] . $matches[2] . '?v=' . $qeJsVer . $matches[3];
        },
        $updated
    );

    if ($updated !== $content) {
        file_put_contents($filePath, $updated);
        echo "Updated: $relName" . PHP_EOL;
        $updatedCount++;
    } else {
        echo "Already current: $relName" . PHP_EOL;
    }
}

echo PHP_EOL . "Total files updated: $updatedCount / " . count($targetFiles) . PHP_EOL;
