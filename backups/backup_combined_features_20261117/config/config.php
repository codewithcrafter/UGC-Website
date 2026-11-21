<?php
/**
 * MY UGC VIDEO - Centralized Application Configuration
 * 
 * Manages environment detection, dynamic base URL resolution (handles any directory or domain),
 * session security, upload directories, and error handling settings.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

// 1. Load Optional .env File If Present (Check Root and Parent Directory)
$envPaths = [
    CMS_ROOT . DIRECTORY_SEPARATOR . '.env',
    dirname(CMS_ROOT) . DIRECTORY_SEPARATOR . '.env'
];
foreach ($envPaths as $envFile) {
    if (file_exists($envFile) && is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if (function_exists('putenv')) {
                    @putenv("{$key}={$val}");
                }
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
        break;
    }
}

// 2. Environment Setup (Strict Explicit Environment Resolution)
function getAppEnvironment(): string {
    $envVal = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? (function_exists('getenv') ? getenv('APP_ENV') : false);
    if (!empty($envVal)) {
        $e = strtolower(trim((string)$envVal));
        if (in_array($e, ['production', 'prod'])) {
            return 'production';
        }
        if (in_array($e, ['local', 'development', 'dev'])) {
            return 'local';
        }
    }
    // Strict requirement: Do not guess or fall back via URL
    return '';
}

$env = getAppEnvironment();

if ($env === '') {
    error_log("CRITICAL: APP_ENV environment variable is missing or invalid in environment configuration. Must be 'local' or 'production'.");
    http_response_code(500);
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Configuration Error: APP_ENV is missing or invalid in .env (Must be 'local' or 'production').\n");
    } else {
        echo "<!DOCTYPE html><html><head><title>System Configuration Error</title></head><body><h2>System Configuration Error</h2><p>The application environment is not configured. Please verify server environment settings.</p></body></html>";
    }
    exit(1);
}

define('ENVIRONMENT', $env);
define('APP_ENV', $env);

// Production Canonical Domain Constant (Universal Authority for SEO)
define('CANONICAL_DOMAIN', 'https://myugcvideo.in');
define('CANONICAL_BASE_URL', CANONICAL_DOMAIN);

function getCanonicalBaseUrl(): string {
    return CANONICAL_DOMAIN;
}

if (ENVIRONMENT === 'local' || ENVIRONMENT === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Error logging
ini_set('log_errors', '1');
$logDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'php_errors.log');

/**
 * Detects whether the current request is served over HTTPS,
 * including behind Hostinger CloudLinux/LiteSpeed/Nginx/Cloudflare reverse proxies.
 */
function isHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    return false;
}

// 3. Dynamic Base URL Resolution
// Computes correct base URL whether hosted at root, in a subdirectory, or across LAN/drive links
function getBaseUrl(): string {
    $appUrl = $_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? (function_exists('getenv') ? getenv('APP_URL') : false);
    if (!empty($appUrl)) {
        return rtrim((string)$appUrl, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (getAppEnvironment() === 'production' || str_contains($host, 'myugcvideo.in') || str_contains($host, 'myugcvideo.com')) {
        return 'https://myugcvideo.in';
    }

    $protocol = isHttps() ? 'https://' : 'http://';
    
    // When invoked via CLI without HTTP_HOST, default to local XAMPP application path
    if (php_sapi_name() === 'cli' && empty($host)) {
        return 'http://localhost/UGC%20Website';
    }

    $effectiveHost = $host ?: 'localhost';

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $subPath = '';
    if (preg_match('#^(/[^/]+?)(?:/admin|/database|/config|/includes|/scratch|/[^/]+\.php|/?$)#i', $script, $matches)) {
        $firstSegment = $matches[1];
        if (!in_array(strtolower($firstSegment), ['/admin', '/database', '/config', '/includes', '/scratch']) && !str_ends_with(strtolower($firstSegment), '.php')) {
            $subPath = $firstSegment;
        }
    }

    if ($subPath !== '') {
        $subPath = str_replace(' ', '%20', $subPath);
    }

    return rtrim($protocol . $effectiveHost . $subPath, '/');
}

/**
 * Normalizes any internal route, filename, or legacy path to its clean extensionless public path.
 */
function cleanPublicPath(string $path): string {
    $p = trim($path);
    // Remove query string or fragments for route detection
    $fragment = '';
    if (str_contains($p, '#')) {
        [$p, $fragment] = explode('#', $p, 2);
        $fragment = '#' . $fragment;
    }
    $query = '';
    if (str_contains($p, '?')) {
        [$p, $query] = explode('?', $p, 2);
        $query = '?' . $query;
    }

    $p = trim($p, '/');

    if ($p === '' || $p === 'index.html' || $p === 'index.php') {
        return '' . $fragment;
    }

    // Core pages
    if ($p === 'about.html') return 'about' . $fragment;
    if ($p === 'services.html') return 'services' . $fragment;
    if ($p === 'contact.html') return 'contact' . $fragment;
    if ($p === 'blogs.php' || $p === 'blogs.html') return 'blogs' . ($query ?: '') . $fragment;

    // Admin routes
    if ($p === 'admin' || $p === 'admin/index.php') return 'admin' . $fragment;
    if ($p === 'admin/login' || $p === 'admin/login.php') return 'admin/login' . $fragment;
    if ($p === 'admin/dashboard' || $p === 'admin/dashboard.php') return 'admin/dashboard' . $fragment;
    if ($p === 'admin/schema-manager' || $p === 'admin/schema-manager.php') return 'admin/schema-manager' . $fragment;
    if ($p === 'admin/logout' || $p === 'admin/logout.php') return 'admin/logout' . $fragment;
    if ($p === 'admin/posts' || $p === 'admin/posts/index.php') return 'admin/posts' . ($query ?: '') . $fragment;
    if ($p === 'admin/posts/create' || $p === 'admin/posts/create.php') return 'admin/posts/create' . $fragment;
    if ($p === 'admin/posts/edit' || $p === 'admin/posts/edit.php') return 'admin/posts/edit' . ($query ?: '') . $fragment;
    if ($p === 'admin/categories' || $p === 'admin/categories/index.php') return 'admin/categories' . $fragment;
    if ($p === 'admin/markdown' || $p === 'admin/markdown/index.php') return 'admin/markdown' . ($query ?: '') . $fragment;
    if ($p === 'admin/media' || $p === 'admin/media/index.php') return 'admin/media' . $fragment;
    if ($p === 'admin/leads' || $p === 'admin/leads.php') return 'admin/leads' . ($query ?: '') . $fragment;
    if ($p === 'api/contact' || $p === 'api/contact.php') return 'api/contact' . ($query ?: '') . $fragment;

    // Dedicated services: services/{slug}.html -> services/{slug}
    if (preg_match('#^services/([^/]+)\.html$#i', $p, $m)) {
        return 'services/' . $m[1] . $fragment;
    }

    // Blog routes: blog.php?slug={slug} -> blog/{slug}
    if (preg_match('#^blog\.php\?slug=([^&]+)#i', $path, $m)) {
        return 'blog/' . $m[1] . $fragment;
    }
    if (preg_match('#^blogs/([^/]+)\.html$#i', $p, $m)) {
        return 'blog/' . $m[1] . $fragment;
    }

    // Direct blog route: blog/{slug}
    if (preg_match('#^blog/([^/]+)/?$#i', $p, $m)) {
        return 'blog/' . $m[1] . $fragment;
    }

    // Remove .html from any other public HTML filename
    if (str_ends_with(strtolower($p), '.html')) {
        return substr($p, 0, -5) . $fragment;
    }

    return $p . ($query ? '?' . $query : '') . $fragment;
}

/**
 * Generates an environment-aware clean public URL (e.g. for user navigation).
 */
function publicUrl(string $path = ''): string {
    $clean = cleanPublicPath($path);
    $base = getBaseUrl();
    return $clean === '' ? $base : $base . '/' . ltrim($clean, '/');
}

/**
 * Generates a production canonical clean public URL (e.g. for SEO schemas, sitemaps, OpenGraph).
 */
function canonicalPublicUrl(string $path = ''): string {
    $clean = cleanPublicPath($path);
    $base = CANONICAL_DOMAIN;
    return $clean === '' ? $base : $base . '/' . ltrim($clean, '/');
}

define('SITE_URL', getBaseUrl());
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOADS_DIR', CMS_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blogs');
define('UPLOADS_URL', SITE_URL . '/uploads/blogs');
define('MARKDOWN_DIR', CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown');
define('MARKDOWN_URL', SITE_URL . '/markdown');

// Ensure upload directory exists
if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0755, true);
}

// 4. Secure Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// 5. Require Database & Core Functions
require_once __DIR__ . '/database.php';
require_once CMS_ROOT . '/includes/functions.php';
require_once CMS_ROOT . '/includes/csrf.php';
require_once CMS_ROOT . '/includes/auth.php';
require_once CMS_ROOT . '/includes/llms-sync.php';
require_once CMS_ROOT . '/includes/schema-renderer.php';
