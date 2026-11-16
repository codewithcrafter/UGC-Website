<?php
/**
 * MY UGC VIDEO - Database Connection Configuration (PDO)
 * 
 * Provides a secure, persistent-ready PDO connection singleton with utf8mb4 charset,
 * prepared statements emulation disabled, and multi-environment credential resolution.
 * Supports $_ENV, $_SERVER, and getenv() across XAMPP and Hostinger PHP-FPM/LiteSpeed.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

class Database {
    private static ?PDO $instance = null;

    /**
     * Resilient environment variable resolver:
     * Checks $_ENV first, then $_SERVER, then getenv().
     */
    private static function getEnvVar(string $key, ?string $default = null): ?string {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string)$_SERVER[$key];
        }
        if (function_exists('getenv')) {
            $val = getenv($key);
            if ($val !== false && $val !== '') {
                return (string)$val;
            }
        }
        return $default;
    }

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $isProduction = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');

            // Secure environment separation:
            // Production defaults configured for Hostinger database: u555726154_ugcvideo
            // Local development defaults to XAMPP standards.
            $defaultHost = $isProduction ? 'srv1328.hstgr.io' : '127.0.0.1';
            $defaultPort = '3306';
            $defaultDb   = $isProduction ? 'u555726154_ugcvideo' : 'my_ugc_video_cms';
            $defaultUser = $isProduction ? 'u555726154_myugcvideo' : 'root';
            $defaultPass = $isProduction ? 'Ugcwebsite@2026' : '';

            $host   = self::getEnvVar('DB_HOST', $defaultHost);
            $port   = self::getEnvVar('DB_PORT', $defaultPort);
            $dbname = self::getEnvVar('DB_NAME', $defaultDb);
            $user   = self::getEnvVar('DB_USER', $defaultUser);
            $pass   = self::getEnvVar('DB_PASS', $defaultPass);

            // Fail safely in production if credentials have not been configured
            if ($isProduction && (empty($dbname) || empty($user))) {
                error_log("CRITICAL: Database configuration missing in production environment. Verify .env file on Hostinger.");
                die("An error occurred connecting to the service. Please try again later.");
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ];

            try {
                self::$instance = new PDO($dsn, $user ?? '', $pass ?? '', $options);
            } catch (PDOException $e) {
                // If Hostinger server hostname failed, attempt local socket/localhost fallback
                if ($host !== 'localhost' && $host !== '127.0.0.1') {
                    try {
                        $fallbackDsn = "mysql:host=localhost;port={$port};dbname={$dbname};charset=utf8mb4";
                        self::$instance = new PDO($fallbackDsn, $user ?? '', $pass ?? '', $options);
                        return self::$instance;
                    } catch (PDOException $fallbackErr) {
                        // Keep primary error for logging
                    }
                }

                // In production, do not expose SQL credentials or raw error message
                error_log("Database Connection Error: " . $e->getMessage());
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
                } else {
                    die("An error occurred connecting to the service. Please try again later.");
                }
            }
        }

        return self::$instance;
    }
}
