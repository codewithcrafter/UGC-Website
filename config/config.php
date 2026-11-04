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

// 1. Load Optional .env File If Present
$envFile = CMS_ROOT . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
        }
    }
}

// 2. Environment Setup
$env = getenv('APP_ENV') ?: 'development';
define('ENVIRONMENT', $env);

if (ENVIRONMENT === 'development') {
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

// 3. Dynamic Base URL Resolution
// Computes correct base URL whether hosted at root, in a subdirectory, or across drive links
function getBaseUrl(): string {
    if (getenv('APP_URL')) {
        return rtrim(getenv('APP_URL'), '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $subPath = '';
    if (preg_match('#^(/[^/]+?)(?:/admin|/database|/config|/includes|/scratch|/[^/]+\.php|/?$)#i', $script, $matches)) {
        $firstSegment = $matches[1];
        if (!in_array(strtolower($firstSegment), ['/admin', '/database', '/config', '/includes', '/scratch']) && !str_ends_with(strtolower($firstSegment), '.php')) {
            $subPath = $firstSegment;
        }
    }

    return rtrim($protocol . $host . $subPath, '/');
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
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
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
