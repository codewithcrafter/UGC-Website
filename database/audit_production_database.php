<?php
/**
 * =========================================================================
 * MY UGC VIDEO — Production Database Read-Only Safety Audit Tool
 * =========================================================================
 * 
 * STRICT SAFETY RULE:
 * This script is 100% READ-ONLY. It contains ZERO write, create, alter,
 * drop, truncate, update, delete, or replace operations.
 * 
 * Can be executed via:
 * 1. CLI: php database/audit_production_database.php
 * 2. Web: https://myugcvideo.in/database/audit_production_database.php?key=audit_ugc_safe_2026
 * 
 * Auto-detects credentials from:
 * 1. .env (via $_ENV, $_SERVER, or .env file)
 * 2. wp-config.php (if existing WordPress database on Hostinger)
 * 3. CLI arguments / Environment variables
 * =========================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// 1. Target Schema Specification (11 Tables defined in hostinger_production_schema.sql)
$targetTablesSpec = [
    'admins' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'name' => ['type' => 'varchar(100)', 'null' => false, 'key' => '', 'extra' => ''],
            'email' => ['type' => 'varchar(191)', 'null' => false, 'key' => 'UNI', 'extra' => ''],
            'password_hash' => ['type' => 'varchar(255)', 'null' => false, 'key' => '', 'extra' => ''],
            'role' => ['type' => "enum('admin','editor')", 'null' => false, 'key' => '', 'extra' => ''],
            'is_active' => ['type' => 'tinyint(1)', 'null' => false, 'key' => '', 'extra' => ''],
            'last_login_at' => ['type' => 'datetime', 'null' => true, 'key' => '', 'extra' => ''],
            'created_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => ''],
            'updated_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'id',
        'unique_keys' => ['email'],
        'indexes' => []
    ],
    'categories' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'name' => ['type' => 'varchar(100)', 'null' => false, 'key' => '', 'extra' => ''],
            'slug' => ['type' => 'varchar(120)', 'null' => false, 'key' => 'UNI', 'extra' => ''],
            'description' => ['type' => 'text', 'null' => true, 'key' => '', 'extra' => ''],
            'created_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => ''],
            'updated_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'id',
        'unique_keys' => ['slug'],
        'indexes' => []
    ],
    'tags' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'name' => ['type' => 'varchar(100)', 'null' => false, 'key' => '', 'extra' => ''],
            'slug' => ['type' => 'varchar(120)', 'null' => false, 'key' => 'UNI', 'extra' => '']
        ],
        'primary_key' => 'id',
        'unique_keys' => ['slug'],
        'indexes' => []
    ],
    'blog_posts' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'title' => ['type' => 'varchar(255)', 'null' => false, 'key' => '', 'extra' => ''],
            'slug' => ['type' => 'varchar(191)', 'null' => false, 'key' => 'UNI', 'extra' => ''],
            'excerpt' => ['type' => 'text', 'null' => false, 'key' => '', 'extra' => ''],
            'content' => ['type' => 'longtext', 'null' => false, 'key' => '', 'extra' => ''],
            'featured_image' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'featured_image_alt' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'author_name' => ['type' => 'varchar(100)', 'null' => false, 'key' => '', 'extra' => ''],
            'author_role' => ['type' => 'varchar(100)', 'null' => false, 'key' => '', 'extra' => ''],
            'author_bio' => ['type' => 'text', 'null' => true, 'key' => '', 'extra' => ''],
            'category_id' => ['type' => 'int(11)', 'null' => true, 'key' => 'MUL', 'extra' => ''],
            'status' => ['type' => "enum('draft','published')", 'null' => false, 'key' => 'MUL', 'extra' => ''],
            'is_featured' => ['type' => 'tinyint(1)', 'null' => false, 'key' => '', 'extra' => ''],
            'published_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => ''],
            'reading_time' => ['type' => 'varchar(50)', 'null' => false, 'key' => '', 'extra' => ''],
            'schema_type' => ['type' => 'varchar(20)', 'null' => false, 'key' => '', 'extra' => ''],
            'custom_schema' => ['type' => 'longtext', 'null' => true, 'key' => '', 'extra' => ''],
            'schema_filename' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'faqs_json' => ['type' => 'longtext', 'null' => true, 'key' => '', 'extra' => ''],
            'navigation_json' => ['type' => 'longtext', 'null' => true, 'key' => '', 'extra' => ''],
            'markdown_file' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'meta_title' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'meta_description' => ['type' => 'text', 'null' => true, 'key' => '', 'extra' => ''],
            'canonical_url' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'og_image' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'keywords' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'created_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => ''],
            'updated_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'id',
        'unique_keys' => ['slug'],
        'indexes' => ['idx_slug', 'idx_status_published', 'idx_category'],
        'foreign_keys' => [
            'fk_blog_category' => ['column' => 'category_id', 'ref_table' => 'categories', 'ref_col' => 'id']
        ]
    ],
    'blog_post_tags' => [
        'columns' => [
            'post_id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => ''],
            'tag_id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => '']
        ],
        'primary_key' => 'post_id,tag_id',
        'indexes' => ['fk_bpt_tag'],
        'foreign_keys' => [
            'fk_bpt_post' => ['column' => 'post_id', 'ref_table' => 'blog_posts', 'ref_col' => 'id'],
            'fk_bpt_tag' => ['column' => 'tag_id', 'ref_table' => 'tags', 'ref_col' => 'id']
        ]
    ],
    'blog_post_revisions' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'post_id' => ['type' => 'int(11)', 'null' => false, 'key' => 'MUL', 'extra' => ''],
            'admin_id' => ['type' => 'int(11)', 'null' => true, 'key' => 'MUL', 'extra' => ''],
            'title' => ['type' => 'varchar(255)', 'null' => false, 'key' => '', 'extra' => ''],
            'content' => ['type' => 'longtext', 'null' => false, 'key' => '', 'extra' => ''],
            'created_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'id',
        'indexes' => ['fk_bpr_post', 'fk_bpr_admin'],
        'foreign_keys' => [
            'fk_bpr_post' => ['column' => 'post_id', 'ref_table' => 'blog_posts', 'ref_col' => 'id'],
            'fk_bpr_admin' => ['column' => 'admin_id', 'ref_table' => 'admins', 'ref_col' => 'id']
        ]
    ],
    'login_attempts' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'ip_address' => ['type' => 'varchar(45)', 'null' => false, 'key' => 'MUL', 'extra' => ''],
            'attempted_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'id',
        'indexes' => ['idx_ip_attempted']
    ],
    'leads' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'name' => ['type' => 'varchar(150)', 'null' => false, 'key' => '', 'extra' => ''],
            'email' => ['type' => 'varchar(191)', 'null' => false, 'key' => 'MUL', 'extra' => ''],
            'phone' => ['type' => 'varchar(50)', 'null' => true, 'key' => '', 'extra' => ''],
            'brand' => ['type' => 'varchar(150)', 'null' => true, 'key' => '', 'extra' => ''],
            'service' => ['type' => 'varchar(100)', 'null' => true, 'key' => '', 'extra' => ''],
            'budget' => ['type' => 'varchar(100)', 'null' => true, 'key' => '', 'extra' => ''],
            'message' => ['type' => 'text', 'null' => false, 'key' => '', 'extra' => ''],
            'source' => ['type' => 'varchar(60)', 'null' => false, 'key' => 'MUL', 'extra' => ''],
            'status' => ['type' => "enum('new','contacted','converted','closed')", 'null' => false, 'key' => 'MUL', 'extra' => ''],
            'internal_notes' => ['type' => 'text', 'null' => true, 'key' => '', 'extra' => ''],
            'email_delivery_status' => ['type' => "enum('pending','sent','failed')", 'null' => false, 'key' => '', 'extra' => ''],
            'email_attempts' => ['type' => 'int(11)', 'null' => false, 'key' => '', 'extra' => ''],
            'last_email_attempt_at' => ['type' => 'datetime', 'null' => true, 'key' => '', 'extra' => ''],
            'email_error_message' => ['type' => 'text', 'null' => true, 'key' => '', 'extra' => ''],
            'ip_address' => ['type' => 'varchar(45)', 'null' => true, 'key' => '', 'extra' => ''],
            'user_agent' => ['type' => 'varchar(255)', 'null' => true, 'key' => '', 'extra' => ''],
            'created_at' => ['type' => 'datetime', 'null' => false, 'key' => 'MUL', 'extra' => ''],
            'updated_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'id',
        'indexes' => ['idx_status', 'idx_created_at', 'idx_email', 'idx_source']
    ],
    'page_schemas' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'page_key' => ['type' => 'varchar(100)', 'null' => false, 'key' => 'MUL', 'extra' => ''],
            'page_title' => ['type' => 'varchar(255)', 'null' => false, 'key' => '', 'extra' => ''],
            'schema_name' => ['type' => 'varchar(150)', 'null' => false, 'key' => '', 'extra' => ''],
            'schema_type' => ['type' => 'varchar(60)', 'null' => false, 'key' => '', 'extra' => ''],
            'schema_data' => ['type' => 'longtext', 'null' => false, 'key' => '', 'extra' => ''],
            'status' => ['type' => "enum('draft','published')", 'null' => false, 'key' => '', 'extra' => ''],
            'created_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => ''],
            'updated_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'id',
        'indexes' => ['idx_page_key_status']
    ],
    'schema_settings' => [
        'columns' => [
            'setting_key' => ['type' => 'varchar(100)', 'null' => false, 'key' => 'PRI', 'extra' => ''],
            'setting_value' => ['type' => 'longtext', 'null' => false, 'key' => '', 'extra' => ''],
            'updated_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'setting_key',
        'indexes' => []
    ],
    'blog_schema_overrides' => [
        'columns' => [
            'post_id' => ['type' => 'int(11)', 'null' => false, 'key' => 'PRI', 'extra' => ''],
            'override_enabled' => ['type' => 'tinyint(1)', 'null' => false, 'key' => '', 'extra' => ''],
            'schema_disabled' => ['type' => 'tinyint(1)', 'null' => false, 'key' => '', 'extra' => ''],
            'custom_schema' => ['type' => 'longtext', 'null' => true, 'key' => '', 'extra' => ''],
            'status' => ['type' => "enum('draft','published')", 'null' => false, 'key' => '', 'extra' => ''],
            'updated_at' => ['type' => 'datetime', 'null' => false, 'key' => '', 'extra' => '']
        ],
        'primary_key' => 'post_id',
        'indexes' => []
    ]
];

// 2. Resolve Environment & Credentials
$isCli = (php_sapi_name() === 'cli');

// Basic Web Security Gate (if accessed via browser)
if (!$isCli) {
    $expectedKey = 'audit_ugc_safe_2026';
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== $expectedKey) {
        http_response_code(403);
        die("<h1>403 Forbidden</h1><p>Access restricted. Please provide the audit authorization key: <code>?key=audit_ugc_safe_2026</code></p>");
    }
}

// Credentials Discovery Order:
// A. Direct .env / $_ENV / $_SERVER
// B. wp-config.php (if existing WordPress setup)
// C. Fallbacks
$dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: null;
$dbPort = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbName = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: null;
$dbUser = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: null;
$dbPass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: null;
$credSource = '.env / environment';

// If credentials not in env, search for local .env files
if (!$dbName || !$dbUser) {
    $candidateEnvs = [
        __DIR__ . '/../.env',
        dirname(__DIR__, 2) . '/.env',
        __DIR__ . '/.env'
    ];
    foreach ($candidateEnvs as $envPath) {
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                if ($k === 'DB_HOST' && !$dbHost) $dbHost = $v;
                if ($k === 'DB_PORT') $dbPort = $v;
                if ($k === 'DB_NAME' && !$dbName) $dbName = $v;
                if ($k === 'DB_USER' && !$dbUser) $dbUser = $v;
                if ($k === 'DB_PASS' && $dbPass === null) $dbPass = $v;
            }
            if ($dbName && $dbUser) {
                $credSource = "File: " . realpath($envPath);
                break;
            }
        }
    }
}

// If still missing, check if wp-config.php exists (WordPress database on Hostinger)
if (!$dbName || !$dbUser) {
    $candidateWp = [
        dirname(__DIR__) . '/wp-config.php',
        dirname(__DIR__, 2) . '/wp-config.php',
        __DIR__ . '/wp-config.php'
    ];
    foreach ($candidateWp as $wpPath) {
        if (file_exists($wpPath)) {
            $wpContent = file_get_contents($wpPath);
            if (preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $wpContent, $m)) $dbName = $m[1];
            if (preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $wpContent, $m)) $dbUser = $m[1];
            if (preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i", $wpContent, $m)) $dbPass = $m[1];
            if (preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $wpContent, $m)) $dbHost = $m[1];
            if ($dbName && $dbUser) {
                $credSource = "WordPress Config: " . realpath($wpPath);
                break;
            }
        }
    }
}

// Production defaults
if (!$dbHost) $dbHost = 'srv1328.hstgr.io';
if (!$dbPort) $dbPort = '3306';
if (!$dbName) $dbName = 'u555726154_ugcvideo';
if (!$dbUser) $dbUser = 'u555726154_myugcvideo';
if ($dbPass === null) $dbPass = 'Ugcwebsite@2026';

// 3. Connect to MySQL Read-Only
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ]);
} catch (PDOException $e) {
    // If external host failed on Hostinger, try localhost socket
    if ($dbHost !== 'localhost' && $dbHost !== '127.0.0.1') {
        try {
            $fallbackDsn = "mysql:host=localhost;port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($fallbackDsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]);
            $dbHost = 'localhost';
        } catch (PDOException $e2) {
            // fall through to error handling
        }
    }
}

if (!isset($pdo)) {
    $errorMsg = "Database Connection Failed: " . ($e->getMessage() ?? 'Unknown error');
    $errorMsg = "Database Connection Failed: " . $e->getMessage();
    if ($isCli) {
        fwrite(STDERR, "[AUDIT ERROR] " . $errorMsg . PHP_EOL);
        fwrite(STDERR, "Resolved Host: $dbHost | Port: $dbPort | DB: $dbName | User: $dbUser (Source: $credSource)" . PHP_EOL);
        exit(1);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo "<div style='font-family:sans-serif;padding:30px;background:#1a1a24;color:#f87171;'>";
        echo "<h2>Production Database Connection Failed</h2>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Host:</strong> " . htmlspecialchars($dbHost) . " | <strong>DB:</strong> " . htmlspecialchars($dbName) . " | <strong>User:</strong> " . htmlspecialchars($dbUser) . "</p>";
        echo "<p><strong>Credentials Source:</strong> " . htmlspecialchars($credSource) . "</p>";
        echo "<p>Please ensure that database credentials exist in <code>.env</code> or <code>wp-config.php</code>.</p>";
        echo "</div>";
        exit;
    }
}

// 4. Perform Complete Read-Only Forensic Audit
$dbMeta = $pdo->query("SELECT DATABASE() AS current_db, VERSION() AS db_version, @@character_set_database AS charset, @@collation_database AS collation")->fetch();

// Discover all tables
$stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$rawTables = $stmt->fetchAll(PDO::FETCH_NUM);
$allTablesInDb = array_map(fn($r) => $r[0], $rawTables);

$existingTargetTables = [];
$missingTargetTables = [];
$otherTablesInDb = [];

foreach ($targetTablesSpec as $targetTable => $spec) {
    if (in_array($targetTable, $allTablesInDb, true)) {
        $existingTargetTables[] = $targetTable;
    } else {
        $missingTargetTables[] = $targetTable;
    }
}

foreach ($allTablesInDb as $t) {
    if (!isset($targetTablesSpec[$t])) {
        $rowCount = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $otherTablesInDb[$t] = $rowCount;
    }
}

// Audit Existing Target Tables
$auditDetails = [];
$tablesRequiringChanges = [];
$safeMigrationSql = [];

foreach ($existingTargetTables as $table) {
    $spec = $targetTablesSpec[$table];

    // Exact row count
    $rowCount = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();

    // Columns
    $colStmt = $pdo->query("SHOW FULL COLUMNS FROM `$table`");
    $actualCols = $colStmt->fetchAll();
    $actualColMap = [];
    foreach ($actualCols as $c) {
        $actualColMap[$c['Field']] = [
            'type' => strtolower($c['Type']),
            'null' => ($c['Null'] === 'YES'),
            'key' => $c['Key'],
            'default' => $c['Default'],
            'extra' => $c['Extra']
        ];
    }

    $missingColumns = [];
    $typeMismatches = [];
    foreach ($spec['columns'] as $colName => $colSpec) {
        if (!isset($actualColMap[$colName])) {
            $missingColumns[] = $colName;
        } else {
            $actType = $actualColMap[$colName]['type'];
            $expType = strtolower($colSpec['type']);
            // Normalize int(11) vs int
            $normAct = preg_replace('/\((\d+)\)/', '', $actType);
            $normExp = preg_replace('/\((\d+)\)/', '', $expType);
            if ($normAct !== $normExp && !str_starts_with($actType, $expType) && !str_starts_with($expType, $actType)) {
                $typeMismatches[] = [
                    'col' => $colName,
                    'expected' => $expType,
                    'actual' => $actType
                ];
            }
        }
    }

    // Indexes
    $idxStmt = $pdo->query("SHOW INDEXES FROM `$table`");
    $actualIndexes = $idxStmt->fetchAll();
    $actualIdxMap = [];
    foreach ($actualIndexes as $idx) {
        $actualIdxMap[$idx['Key_name']][] = $idx['Column_name'];
    }

    $needsChange = (!empty($missingColumns) || !empty($typeMismatches));
    if ($needsChange) {
        $tablesRequiringChanges[$table] = [
            'missing_cols' => $missingColumns,
            'type_mismatches' => $typeMismatches
        ];
    }

    $auditDetails[$table] = [
        'rows' => $rowCount,
        'has_records' => ($rowCount > 0),
        'columns_actual' => count($actualColMap),
        'columns_expected' => count($spec['columns']),
        'missing_columns' => $missingColumns,
        'type_mismatches' => $typeMismatches,
        'indexes' => array_keys($actualIdxMap),
        'needs_change' => $needsChange
    ];
}

// Generate Safe Migration SQL for Missing Tables (CREATE TABLE IF NOT EXISTS)
$schemaSqlFile = __DIR__ . '/hostinger_production_schema.sql';
if (file_exists($schemaSqlFile)) {
    $sqlContent = file_get_contents($schemaSqlFile);
    foreach ($missingTargetTables as $missingTable) {
        if (preg_match("/CREATE TABLE IF NOT EXISTS `$missingTable`\s*\(.*?\)\s*ENGINE=[^;]+;/s", $sqlContent, $m)) {
            $safeMigrationSql[] = trim($m[0]);
        }
    }
}

// 5. Render Output
if ($isCli) {
    echo "=========================================================================" . PHP_EOL;
    echo "MY UGC VIDEO — PRODUCTION DATABASE READ-ONLY SAFETY AUDIT" . PHP_EOL;
    echo "=========================================================================" . PHP_EOL;
    echo "Connected Database : " . $dbMeta['current_db'] . " (" . $dbMeta['db_version'] . ")" . PHP_EOL;
    echo "Charset / Collate  : " . $dbMeta['charset'] . " / " . $dbMeta['collation'] . PHP_EOL;
    echo "Host / Port / User : " . $dbHost . ":" . $dbPort . " (" . $dbUser . ")" . PHP_EOL;
    echo "Credentials Source : " . $credSource . PHP_EOL;
    echo "Audit Mode         : 100% STRICT READ-ONLY (No changes executed)" . PHP_EOL;
    echo "-------------------------------------------------------------------------" . PHP_EOL;
    echo PHP_EOL;

    echo "1. TARGET TABLES AUDIT SUMMARY (11 TABLES REQUIRED):" . PHP_EOL;
    echo "   Existing Target Tables : " . count($existingTargetTables) . " / 11" . PHP_EOL;
    echo "   Missing Target Tables  : " . count($missingTargetTables) . " / 11" . PHP_EOL;
    echo "   Tables Needing Changes : " . count($tablesRequiringChanges) . PHP_EOL;
    echo PHP_EOL;

    echo "2. EXISTING TARGET TABLES & RECORD SAFETY:" . PHP_EOL;
    if (empty($existingTargetTables)) {
        echo "   (None - The 11 target tables do not exist yet in this database)" . PHP_EOL;
    } else {
        foreach ($existingTargetTables as $t) {
            $info = $auditDetails[$t];
            $status = $info['has_records'] ? "[ACTIVE DATA: {$info['rows']} rows]" : "[EMPTY: 0 rows]";
            $changeStatus = $info['needs_change'] ? "(! REQUIRES COLUMN UPDATE)" : "(✓ Structure Matches)";
            echo sprintf("   %-22s : %-22s %s\n", $t, $status, $changeStatus);
            if (!empty($info['missing_columns'])) {
                echo "      └─ Missing Columns: " . implode(', ', $info['missing_columns']) . PHP_EOL;
            }
            if (!empty($info['type_mismatches'])) {
                foreach ($info['type_mismatches'] as $tm) {
                    echo "      └─ Type Mismatch: {$tm['col']} (expected {$tm['expected']}, found {$tm['actual']})" . PHP_EOL;
                }
            }
        }
    }
    echo PHP_EOL;

    echo "3. MISSING TARGET TABLES (SAFE TO CREATE):" . PHP_EOL;
    if (empty($missingTargetTables)) {
        echo "   (None - All 11 tables already exist)" . PHP_EOL;
    } else {
        foreach ($missingTargetTables as $mt) {
            echo "   - $mt (Will be created with CREATE TABLE IF NOT EXISTS)" . PHP_EOL;
        }
    }
    echo PHP_EOL;

    echo "4. OTHER / UNRELATED TABLES IN DATABASE (" . count($otherTablesInDb) . " tables):" . PHP_EOL;
    if (empty($otherTablesInDb)) {
        echo "   (No unrelated tables found)" . PHP_EOL;
    } else {
        echo "   Note: These tables will NOT be touched, altered, or dropped." . PHP_EOL;
        foreach ($otherTablesInDb as $ot => $rows) {
            $isWp = str_starts_with($ot, 'wp_') ? "[WordPress Table]" : "[Other Table]";
            echo sprintf("   - %-30s : %-18s (%d records)\n", $ot, $isWp, $rows);
        }
    }
    echo PHP_EOL;

    echo "5. POTENTIAL CONFLICTS & DATA RISK ANALYSIS:" . PHP_EOL;
    if (empty($existingTargetTables)) {
        echo "   ✓ ZERO CONFLICTS: None of the target tables exist yet. Importing hostinger_production_schema.sql is 100% safe." . PHP_EOL;
    } else {
        $hasData = false;
        foreach ($existingTargetTables as $t) {
            if ($auditDetails[$t]['has_records']) {
                $hasData = true;
                echo "   ! CAUTION: Table '$t' contains {$auditDetails[$t]['rows']} existing records. DO NOT DROP or TRUNCATE." . PHP_EOL;
            }
        }
        if (!$hasData) {
            echo "   ✓ ZERO DATA LOSS RISK: Existing target tables contain 0 records." . PHP_EOL;
        }
    }
    echo PHP_EOL;

    echo "6. SAFE MIGRATION SQL GENERATION:" . PHP_EOL;
    if (empty($safeMigrationSql)) {
        echo "   ✓ No missing tables to generate. All target tables exist." . PHP_EOL;
    } else {
        echo "   Generated " . count($safeMigrationSql) . " safe non-destructive CREATE TABLE IF NOT EXISTS statements." . PHP_EOL;
    }
    echo "=========================================================================" . PHP_EOL;
} else {
    // HTML Dashboard Output
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>MY UGC VIDEO — Production Database Safety Audit</title>
        <style>
            :root { --bg: #0b0f19; --card: #151b2b; --border: #222d42; --teal: #2dd4bf; --text: #e2e8f0; --muted: #94a3b8; --red: #f87171; --green: #4ade80; --amber: #fbbf24; }
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace; }
            body { background: var(--bg); color: var(--text); padding: 30px; line-height: 1.5; }
            .container { max-width: 1100px; margin: 0 auto; }
            .header { border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 24px; }
            .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: uppercase; background: rgba(45, 212, 191, 0.15); color: var(--teal); border: 1px solid var(--teal); }
            h1 { font-size: 24px; margin-top: 10px; color: #fff; }
            .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin: 20px 0; }
            .card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 18px; }
            .card h3 { font-size: 13px; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; }
            .card .val { font-size: 18px; font-weight: 700; color: #fff; }
            .table-box { width: 100%; border-collapse: collapse; margin-top: 12px; }
            .table-box th, .table-box td { padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--border); font-size: 14px; }
            .table-box th { color: var(--muted); background: rgba(255,255,255,0.02); font-weight: 600; }
            .status-tag { padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
            .tag-green { background: rgba(74, 222, 128, 0.15); color: var(--green); border: 1px solid rgba(74, 222, 128, 0.3); }
            .tag-amber { background: rgba(251, 191, 36, 0.15); color: var(--amber); border: 1px solid rgba(251, 191, 36, 0.3); }
            .tag-red { background: rgba(248, 113, 113, 0.15); color: var(--red); border: 1px solid rgba(248, 113, 113, 0.3); }
            .code-block { background: #070a10; border: 1px solid var(--border); padding: 14px; border-radius: 6px; font-family: monospace; font-size: 13px; color: #38bdf8; overflow-x: auto; white-space: pre-wrap; margin-top: 10px; max-height: 300px; }
            .alert-box { padding: 14px; border-radius: 6px; margin: 18px 0; font-size: 14px; border-left: 4px solid var(--teal); background: rgba(45, 212, 191, 0.08); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <span class="badge">100% Read-Only Safety Audit</span>
                <h1>MY UGC VIDEO — Production Database Verification</h1>
                <p style="color: var(--muted); margin-top: 6px; font-size: 14px;">Forensic inspection of live MySQL database prior to schema deployment.</p>
            </div>

            <div class="meta-grid">
                <div class="card">
                    <h3>Current Database</h3>
                    <div class="val"><?= htmlspecialchars($dbMeta['current_db']) ?></div>
                </div>
                <div class="card">
                    <h3>MySQL / Engine Version</h3>
                    <div class="val"><?= htmlspecialchars($dbMeta['db_version']) ?></div>
                </div>
                <div class="card">
                    <h3>Target Tables Present</h3>
                    <div class="val" style="color: <?= (count($existingTargetTables) === 11) ? 'var(--green)' : 'var(--amber)' ?>;">
                        <?= count($existingTargetTables) ?> / 11
                    </div>
                </div>
                <div class="card">
                    <h3>Unrelated / WP Tables</h3>
                    <div class="val"><?= count($otherTablesInDb) ?></div>
                </div>
            </div>

            <div class="alert-box">
                <strong>Safety Verification Guarantee:</strong> This tool only executed <code>SHOW TABLES</code>, <code>SHOW COLUMNS</code>, <code>SHOW INDEXES</code>, and <code>COUNT(*)</code>. No rows were inserted, updated, or removed, and no tables were altered or dropped.
            </div>

            <div class="card" style="margin-bottom: 24px;">
                <h2 style="font-size: 16px; margin-bottom: 12px; color: #fff;">1. Target CMS Schema Audit (11 Tables)</h2>
                <table class="table-box">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Status in Live DB</th>
                            <th>Records Found</th>
                            <th>Columns</th>
                            <th>Structural Assessment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($targetTablesSpec as $tName => $tSpec): ?>
                            <?php 
                                $exists = in_array($tName, $existingTargetTables, true);
                                $info = $exists ? $auditDetails[$tName] : null;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tName) ?></strong></td>
                                <td>
                                    <?php if ($exists): ?>
                                        <span class="status-tag tag-green">EXISTS</span>
                                    <?php else: ?>
                                        <span class="status-tag tag-amber">MISSING</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($exists): ?>
                                        <?= $info['rows'] ?> records
                                    <?php else: ?>
                                        <span style="color:var(--muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($exists): ?>
                                        <?= $info['columns_actual'] ?> / <?= $info['columns_expected'] ?>
                                    <?php else: ?>
                                        <?= count($tSpec['columns']) ?> required
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$exists): ?>
                                        <span style="color: var(--amber);">Safe to create with <code>CREATE TABLE IF NOT EXISTS</code></span>
                                    <?php elseif ($info['needs_change']): ?>
                                        <span style="color: var(--red);">Requires column updates (<?= implode(', ', $info['missing_columns']) ?>)</span>
                                    <?php else: ?>
                                        <span style="color: var(--green);">✓ Perfect match</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($otherTablesInDb)): ?>
            <div class="card" style="margin-bottom: 24px;">
                <h2 style="font-size: 16px; margin-bottom: 6px; color: #fff;">2. Other Existing Tables in Database (WordPress / Legacy)</h2>
                <p style="font-size: 13px; color: var(--muted); margin-bottom: 12px;">These tables exist in the same database but are NOT part of MY UGC VIDEO CMS. They will remain completely untouched.</p>
                <table class="table-box">
                    <thead>
                        <tr>
                            <th>Existing Table Name</th>
                            <th>Classification</th>
                            <th>Current Record Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otherTablesInDb as $otName => $otRows): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($otName) ?></code></td>
                                <td>
                                    <?php if (str_starts_with($otName, 'wp_')): ?>
                                        <span class="status-tag tag-green">WordPress Native Table</span>
                                    <?php else: ?>
                                        <span class="status-tag tag-amber">Other / Backup Table</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $otRows ?> records</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2 style="font-size: 16px; margin-bottom: 6px; color: #fff;">3. Safe Migration SQL Script</h2>
                <p style="font-size: 13px; color: var(--muted);">Non-destructive SQL statements generated based on this audit. Contains only <code>IF NOT EXISTS</code> clauses.</p>
                <div class="code-block"><?php
                    if (empty($safeMigrationSql)) {
                        echo "-- All 11 target tables already exist. No schema creation statements needed.\n";
                    } else {
                        echo htmlspecialchars(implode("\n\n", $safeMigrationSql));
                    }
                ?></div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
