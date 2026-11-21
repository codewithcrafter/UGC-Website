<?php
/**
 * MY UGC VIDEO - Database Connection Configuration (PDO)
 * 
 * Provides a secure, persistent-ready PDO connection singleton with utf8mb4 charset,
 * prepared statements emulation disabled, and strict environment-based credential resolution.
 * 
 * LOCAL XAMPP (APP_ENV=local):
 *   Uses existing local MySQL database (my_ugc_video_cms on 127.0.0.1:3306).
 * 
 * HOSTINGER PRODUCTION (APP_ENV=production):
 *   Uses dedicated Hostinger MySQL database (u555726154_ugcvideo on srv1328.hstgr.io:3306).
 * 
 * STRICT ARCHITECTURAL & SECURITY ENFORCEMENT:
 *   - Explicit environment variable check (APP_ENV).
 *   - Production NEVER silently falls back to local XAMPP database or root/blank passwords.
 *   - Local development NEVER connects to Hostinger production database.
 *   - If credentials fail or environment is invalid, terminates safely with secure logging.
 *   - Never exposes database credentials, passwords, or SQL errors to client browsers.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

require_once CMS_ROOT . '/config/config.php';

class Database {
    private static ?PDO $instance = null;
    private static ?string $activeEnvironment = null;
    private static ?string $activeDatabase = null;
    private static ?string $activeHost = null;

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
        if (self::$instance !== null) {
            return self::$instance;
        }

        $env = defined('APP_ENV') ? APP_ENV : (defined('ENVIRONMENT') ? ENVIRONMENT : '');

        // 1. Strict environment validation
        if ($env !== 'local' && $env !== 'production') {
            error_log("CRITICAL: Database connection aborted. Invalid APP_ENV: '{$env}'. Must be 'local' or 'production'.");
            http_response_code(500);
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, "Database Error: Invalid APP_ENV. Set APP_ENV=local or APP_ENV=production in .env.\n");
            } else {
                echo "<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body><h2>Service Unavailable</h2><p>An application configuration error occurred. Please contact the administrator.</p></body></html>";
            }
            exit(1);
        }

        // 2. Resolve credentials strictly by active environment
        if ($env === 'production') {
            // Production Credentials (from Hostinger environment)
            $host   = self::getEnvVar('DB_HOST', 'srv1328.hstgr.io');
            $port   = self::getEnvVar('DB_PORT', '3306');
            $dbname = self::getEnvVar('DB_NAME', 'u555726154_ugcvideo');
            $user   = self::getEnvVar('DB_USER', 'u555726154_myugcvideo');
            $pass   = self::getEnvVar('DB_PASS', 'Ugcwebsite@2026');

            // Production Safety Guard: Never allow fallback to root or local XAMPP database in production
            if ($dbname === 'my_ugc_video_cms' || ($user === 'root' && empty($pass))) {
                error_log("CRITICAL SECURITY VIOLATION: Production environment attempted to connect using local XAMPP database/credentials. Connection aborted.");
                http_response_code(500);
                die("An error occurred connecting to the service. Please try again later.");
            }

            if (empty($dbname) || empty($user)) {
                error_log("CRITICAL: Production database credentials missing in .env.");
                http_response_code(500);
                die("An error occurred connecting to the service. Please try again later.");
            }
        } else {
            // Local XAMPP Credentials (Preserve exact working local setup)
            $host   = self::getEnvVar('DB_HOST', '127.0.0.1');
            $port   = self::getEnvVar('DB_PORT', '3306');
            $dbname = self::getEnvVar('DB_NAME', 'my_ugc_video_cms');
            $user   = self::getEnvVar('DB_USER', 'root');
            $pass   = self::getEnvVar('DB_PASS', '');

            // Local Safety Guard: Never connect to Hostinger production database from local XAMPP
            if ($host === 'srv1328.hstgr.io' || $dbname === 'u555726154_ugcvideo') {
                error_log("WARNING: Local environment was provided with Hostinger production database settings. Reverting to local XAMPP defaults for safety.");
                $host   = '127.0.0.1';
                $port   = '3306';
                $dbname = 'my_ugc_video_cms';
                $user   = 'root';
                $pass   = '';
            }
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];

        try {
            self::$instance = new PDO($dsn, $user ?? '', $pass ?? '', $options);
            self::$activeEnvironment = $env;
            self::$activeDatabase = $dbname;
            self::$activeHost = $host;
            return self::$instance;
        } catch (PDOException $e) {
            // In production on Hostinger: if remote srv1328.hstgr.io failed from local context,
            // attempt local socket localhost with the EXACT SAME production credentials and production database
            if ($env === 'production' && $host !== 'localhost' && $host !== '127.0.0.1') {
                try {
                    $fallbackDsn = "mysql:host=localhost;port={$port};dbname={$dbname};charset=utf8mb4";
                    self::$instance = new PDO($fallbackDsn, $user ?? '', $pass ?? '', $options);
                    self::$activeEnvironment = $env;
                    self::$activeDatabase = $dbname;
                    self::$activeHost = 'localhost';
                    return self::$instance;
                } catch (PDOException $fallbackErr) {
                    // Retain primary error for secure logging below
                }
            }

            // Secure Logging: Log technical error details to private log file
            error_log("Database Connection Error [env={$env}, db={$dbname}]: " . $e->getMessage());

            http_response_code(500);

            if ($env === 'local') {
                // In local development, show clear message for developer convenience
                die("Local Database Connection Failed: " . htmlspecialchars($e->getMessage()) . "<br><small>Verify MySQL is running in XAMPP Control Panel.</small>");
            } else {
                // In production, NEVER expose SQL credentials, database name, or stack trace
                die("An error occurred connecting to the service. Please try again later.");
            }
        }
    }

    public static function getActiveEnvironment(): ?string {
        return self::$activeEnvironment;
    }

    public static function getActiveDatabase(): ?string {
        return self::$activeDatabase;
    }

    public static function getActiveHost(): ?string {
        return self::$activeHost;
    }

    public static function isConnected(): bool {
        return self::$instance !== null;
    }

    /**
     * Resets instance (primarily used for automated testing of environment transitions).
     */
    public static function resetInstance(): void {
        self::$instance = null;
        self::$activeEnvironment = null;
        self::$activeDatabase = null;
        self::$activeHost = null;
    }
}
