<?php
/**
 * MY UGC VIDEO — Safe Schema Manager Database Migration
 * Creates schema_settings, page_schemas, and blog_schema_overrides tables.
 * Additive, idempotent, and preserves all existing database tables and records.
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

function runSchemaManagerMigration(): array {
    $db = getDb();
    $messages = [];

    // 1. Create schema_settings table (Key-Value configuration)
    $db->exec("CREATE TABLE IF NOT EXISTS `schema_settings` (
        `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
        `setting_value` LONGTEXT NOT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "Table `schema_settings` verified/created.";

    // 2. Create page_schemas table (Multiple schemas per static page)
    $db->exec("CREATE TABLE IF NOT EXISTS `page_schemas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `page_key` VARCHAR(100) NOT NULL,
        `page_title` VARCHAR(255) NOT NULL,
        `schema_name` VARCHAR(150) NOT NULL,
        `schema_type` VARCHAR(60) NOT NULL,
        `schema_data` LONGTEXT NOT NULL,
        `status` ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_page_key_status` (`page_key`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "Table `page_schemas` verified/created.";

    // 3. Create blog_schema_overrides table (Individual blog customization)
    $db->exec("CREATE TABLE IF NOT EXISTS `blog_schema_overrides` (
        `post_id` INT NOT NULL PRIMARY KEY,
        `override_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `schema_disabled` TINYINT(1) NOT NULL DEFAULT 0,
        `custom_schema` LONGTEXT NULL,
        `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published',
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "Table `blog_schema_overrides` verified/created.";

    // 4. Seed default global settings if missing
    $defaults = [
        'auto_blog_schema_enabled' => '1',
        'global_template_status'   => 'published',
        'field_mappings'           => json_encode([
            'title'          => 'title',
            'description'    => 'meta_description',
            'author_name'    => 'author_name',
            'author_role'    => 'author_role',
            'featured_image' => 'featured_image',
            'published_at'   => 'published_at',
            'updated_at'     => 'updated_at',
            'canonical_url'  => 'canonical_url'
        ], JSON_PRETTY_PRINT),
        'global_blog_template'     => json_encode([
            "@context" => "https://schema.org",
            "@type" => "BlogPosting",
            "@id" => "{{canonical_url}}#article",
            "headline" => "{{title}}",
            "description" => "{{description}}",
            "image" => "{{featured_image}}",
            "datePublished" => "{{published_at}}",
            "dateModified" => "{{updated_at}}",
            "mainEntityOfPage" => [
                "@type" => "WebPage",
                "@id" => "{{canonical_url}}"
            ],
            "author" => [
                "@type" => "Person",
                "name" => "{{author_name}}",
                "jobTitle" => "{{author_role}}"
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => "MY UGC VIDEO",
                "url" => "https://myugcvideo.in/",
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => "https://myugcvideo.in/images/logo.png"
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ];

    $checkStmt = $db->prepare("SELECT COUNT(*) FROM `schema_settings` WHERE `setting_key` = :k");
    $insStmt = $db->prepare("INSERT INTO `schema_settings` (`setting_key`, `setting_value`) VALUES (:k, :v)");

    foreach ($defaults as $k => $v) {
        $checkStmt->execute([':k' => $k]);
        if ((int)$checkStmt->fetchColumn() === 0) {
            $insStmt->execute([':k' => $k, ':v' => $v]);
            $messages[] = "Initialized default setting `{$k}`.";
        }
    }

    // 5. Seed initial static page schemas from existing project metadata if empty
    $countPageSchemas = (int)$db->query("SELECT COUNT(*) FROM `page_schemas`")->fetchColumn();
    if ($countPageSchemas === 0) {
        $initialStaticSchemas = [
            [
                'page_key' => 'homepage',
                'page_title' => 'Homepage',
                'schema_name' => 'Organization & Professional Service',
                'schema_type' => 'ProfessionalService',
                'schema_data' => json_encode([
                    "@context" => "https://schema.org",
                    "@type" => "ProfessionalService",
                    "name" => "MY UGC VIDEO",
                    "url" => "https://myugcvideo.in/",
                    "logo" => "https://myugcvideo.in/images/hero/hero-creator.jpg",
                    "description" => "Premium UGC video production agency specializing in creator-led video advertising, product demonstrations, unboxings, and direct-response social media video production.",
                    "address" => [
                        "@type" => "PostalAddress",
                        "addressLocality" => "Delhi",
                        "addressCountry" => "IN"
                    ]
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'status' => 'published'
            ],
            [
                'page_key' => 'about',
                'page_title' => 'About Us',
                'schema_name' => 'About Page Details',
                'schema_type' => 'AboutPage',
                'schema_data' => json_encode([
                    "@context" => "https://schema.org",
                    "@type" => "AboutPage",
                    "name" => "About MY UGC VIDEO",
                    "url" => "https://myugcvideo.in/about",
                    "description" => "Discover MY UGC VIDEO — a premier platform bridging ambitious brands and authentic content creators to craft engaging, high-performing creator-led video content."
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'status' => 'published'
            ],
            [
                'page_key' => 'services',
                'page_title' => 'Services Directory',
                'schema_name' => 'Services Directory Collection',
                'schema_type' => 'Service',
                'schema_data' => json_encode([
                    "@context" => "https://schema.org",
                    "@type" => "Service",
                    "name" => "UGC Video Production Services",
                    "provider" => [
                        "@type" => "Organization",
                        "name" => "MY UGC VIDEO",
                        "url" => "https://myugcvideo.in/"
                    ],
                    "serviceType" => "User Generated Content Video Ads Production"
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'status' => 'published'
            ],
            [
                'page_key' => 'contact',
                'page_title' => 'Contact Us',
                'schema_name' => 'Contact Us Details',
                'schema_type' => 'ContactPage',
                'schema_data' => json_encode([
                    "@context" => "https://schema.org",
                    "@type" => "ContactPage",
                    "name" => "Contact MY UGC VIDEO",
                    "url" => "https://myugcvideo.in/contact",
                    "description" => "Contact channels and project inquiry form for MY UGC VIDEO production agency."
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'status' => 'published'
            ],
            [
                'page_key' => 'blog-listing',
                'page_title' => 'Blog Listing',
                'schema_name' => 'Blog Collection Page',
                'schema_type' => 'WebPage',
                'schema_data' => json_encode([
                    "@context" => "https://schema.org",
                    "@type" => "CollectionPage",
                    "name" => "MY UGC VIDEO Insights & Editorial Journal",
                    "url" => "https://myugcvideo.in/blogs",
                    "description" => "Strategic insights and tactical production frameworks for modern brands scaling through user-generated content."
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'status' => 'published'
            ]
        ];

        $insPageStmt = $db->prepare("INSERT INTO `page_schemas` (`page_key`, `page_title`, `schema_name`, `schema_type`, `schema_data`, `status`) VALUES (:pk, :pt, :sn, :st, :sd, :s)");
        foreach ($initialStaticSchemas as $s) {
            $insPageStmt->execute([
                ':pk' => $s['page_key'],
                ':pt' => $s['page_title'],
                ':sn' => $s['schema_name'],
                ':st' => $s['schema_type'],
                ':sd' => $s['schema_data'],
                ':s'  => $s['status']
            ]);
            $messages[] = "Seeded initial schema for `{$s['page_title']}`.";
        }
    }

    return ['success' => true, 'messages' => $messages];
}

if (php_sapi_name() === 'cli' || defined('RUN_MIGRATION_NOW')) {
    $res = runSchemaManagerMigration();
    echo "=== Schema Manager Migration Result ===\n";
    foreach ($res['messages'] as $m) {
        echo "[OK] {$m}\n";
    }
}

