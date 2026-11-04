<?php
/**
 * Database Migration for Blog CMS Enhancements
 * Adds: schema_type, custom_schema, faqs_json, markdown_file
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = getDb();

// Get existing columns
$existingCols = [];
foreach ($db->query("DESCRIBE blog_posts") as $col) {
    $existingCols[] = strtolower($col['Field']);
}

$migrations = [
    'schema_type' => "ALTER TABLE blog_posts ADD COLUMN schema_type VARCHAR(20) NOT NULL DEFAULT 'auto' AFTER reading_time",
    'custom_schema' => "ALTER TABLE blog_posts ADD COLUMN custom_schema LONGTEXT NULL AFTER schema_type",
    'faqs_json' => "ALTER TABLE blog_posts ADD COLUMN faqs_json LONGTEXT NULL AFTER custom_schema",
    'markdown_file' => "ALTER TABLE blog_posts ADD COLUMN markdown_file VARCHAR(255) NULL AFTER faqs_json"
];

foreach ($migrations as $col => $sql) {
    if (!in_array(strtolower($col), $existingCols)) {
        $db->exec($sql);
        echo "Successfully added column: {$col}\n";
    } else {
        echo "Column {$col} already exists.\n";
    }
}

echo "Database migration complete.\n";

