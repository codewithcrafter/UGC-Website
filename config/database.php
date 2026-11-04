<?php
/**
 * MY UGC VIDEO - Database Connection Configuration (PDO)
 * 
 * Provides a secure, persistent-ready PDO connection singleton with utf8mb4 charset,
 * prepared statements emulation disabled, and error reporting tailored to environment.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_NAME') ?: 'my_ugc_video_cms';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
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
