<?php
/**
 * MY UGC VIDEO — Centralized Dynamic Frontend Page Router
 * 
 * Intercepts clean public URL requests for HTML pages (Homepage, About, Contact, Services, Service Details),
 * dynamically loads the corresponding HTML template, queries active Schema.org JSON-LD from MySQL,
 * validates and server-renders the JSON-LD inside the original HTML <head>, and sends the response.
 *
 * Supported clean routes:
 *   /                     -> index.html + homepage schemas
 *   /about                -> about.html + about schemas
 *   /contact              -> contact.html + contact schemas
 *   /services             -> services.html + services schemas
 *   /services/{slug}      -> services/{slug}.html + service-{slug} schemas
 */

define('CMS_ROOT', __DIR__);
require_once CMS_ROOT . '/config/config.php';
require_once CMS_ROOT . '/includes/schema-renderer.php';
if (file_exists(CMS_ROOT . '/includes/website-markdown-sync.php')) {
    require_once CMS_ROOT . '/includes/website-markdown-sync.php';
}

// 1. Identify current canonical request and resolve page identifier
$page = trim($_GET['page'] ?? '');
$service = trim($_GET['service'] ?? '');

$pageKey = null;
$templateFile = null;

if (!empty($service)) {
    $cleanSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $service);
    $pageKey = 'service-' . $cleanSlug;
    $templateFile = CMS_ROOT . '/services/' . $cleanSlug . '.html';
} elseif (!empty($page)) {
    switch ($page) {
        case 'homepage':
        case 'home':
        case 'index':
            $pageKey = 'homepage';
            $templateFile = CMS_ROOT . '/index.html';
            break;
        case 'about':
            $pageKey = 'about';
            $templateFile = CMS_ROOT . '/about.html';
            break;
        case 'contact':
            $pageKey = 'contact';
            $templateFile = CMS_ROOT . '/contact.html';
            break;
        case 'services':
            $pageKey = 'services';
            $templateFile = CMS_ROOT . '/services.html';
            break;
        default:
            $cleanSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $page);
            $templateFile = CMS_ROOT . '/' . $cleanSlug . '.html';
            $pageKey = $cleanSlug;
            break;
    }
} else {
    // If neither is passed, resolve directly from REQUEST_URI
    $pageKey = resolvePageKeyFromUrl($_SERVER['REQUEST_URI'] ?? '/');
    if ($pageKey === 'homepage') {
        $templateFile = CMS_ROOT . '/index.html';
    } elseif ($pageKey === 'about') {
        $templateFile = CMS_ROOT . '/about.html';
    } elseif ($pageKey === 'contact') {
        $templateFile = CMS_ROOT . '/contact.html';
    } elseif ($pageKey === 'services') {
        $templateFile = CMS_ROOT . '/services.html';
    } elseif ($pageKey && str_starts_with($pageKey, 'service-')) {
        $slug = substr($pageKey, 8);
        $templateFile = CMS_ROOT . '/services/' . $slug . '.html';
    }
}

// 2. Validate template existence
if (!$templateFile || !file_exists($templateFile)) {
    http_response_code(404);
    $errFile = CMS_ROOT . '/404.html';
    if (file_exists($errFile)) {
        include $errFile;
    } else {
        echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>";
    }
    exit;
}

// 3. Load template HTML
$html = file_get_contents($templateFile);
if ($html === false) {
    http_response_code(500);
    echo "<!DOCTYPE html><html><head><title>500 Internal Server Error</title></head><body><h1>500 Internal Server Error</h1></body></html>";
    exit;
}

// 4. Inject dynamic homepage blog cards if serving homepage
if ($pageKey === 'homepage' && function_exists('injectDynamicHomepageJournalCards')) {
    $html = injectDynamicHomepageJournalCards($html);
}

// 5. Retrieve dynamic active schemas from database for this specific page
$dynamicSchemas = '';
if ($pageKey) {
    $dynamicSchemas = renderDynamicPageSchemas($pageKey);
}

// 5. Inject dynamic schemas into <head> (replacing any legacy static blocks)
$finalHtml = injectDynamicSchemasIntoHtml($html, $dynamicSchemas);

// 6. Inject dynamic asset versions for automatic cache-busting (filemtime-based)
$finalHtml = injectDynamicAssetVersions($finalHtml);

// 6.5 Keep existing page Markdown synchronized if HTML source was modified directly
if ($pageKey && $pageKey !== 'homepage' && class_exists('WebsiteMarkdownSync')) {
    WebsiteMarkdownSync::syncHtmlPageIfStale($pageKey);
}

// 7. Set HTTP headers and output
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $finalHtml;
exit;
