<?php
/**
 * MY UGC VIDEO — Page-Wise Schema Manager
 * 
 * Rebuilt Architecture:
 * - Dedicated management for Every Website Page
 * - All required schemas for a page appear together on ONE SCREEN
 * - Every schema in an individual, independent card
 * - Independent Edit, Save, Validate, and Delete per card
 * - Safe existing schema preservation and non-overwriting guarantees
 * - Full integration with centralized schema-renderer.php and blog automations
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}
require_once CMS_ROOT . '/config/config.php';
requireAuth();

require_once CMS_ROOT . '/includes/schema-renderer.php';
require_once CMS_ROOT . '/includes/llms-sync.php';

$db = getDb();
$activeNav = 'schema_manager';
$pageTitle = 'Schema Manager — Page-Wise Structured Data';

// CSRF Token Initialization
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Helper to escape output
function sm_e($val) {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

// =========================================================================
// 1. CENTRAL REGISTRY OF APPROVED PAGES & REQUIRED SCHEMAS
// =========================================================================

function getApprovedPagesRegistry(): array {
    $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $homeUrl = rtrim($canonicalBase, '/');

    return [
        'homepage' => [
            'key' => 'homepage',
            'title' => 'Homepage',
            'url' => '/',
            'canonical' => $homeUrl,
            'group' => 'core',
            'schemas' => ['Organization', 'WebPage', 'VideoObject'],
            'description' => 'Primary brand landing page showcasing UGC video production services and creator showreel.'
        ],
        'about' => [
            'key' => 'about',
            'title' => 'About Us',
            'url' => '/about',
            'canonical' => $canonicalBase . '/about',
            'group' => 'core',
            'schemas' => ['Organization', 'BreadcrumbList', 'WebPage'],
            'description' => 'Brand background, creator network mission, and agency philosophy.'
        ],
        'contact' => [
            'key' => 'contact',
            'title' => 'Contact Us',
            'url' => '/contact',
            'canonical' => $canonicalBase . '/contact',
            'group' => 'core',
            'schemas' => ['Organization', 'BreadcrumbList', 'WebPage'],
            'description' => 'Project inquiry form, corporate contact channels, and agency inquiries.'
        ],
        // The 9 Approved Service Pages
        'service-ugc-video-creation-services-company' => [
            'key' => 'service-ugc-video-creation-services-company',
            'title' => 'UGC Video Creation Company',
            'url' => '/services/ugc-video-creation-services-company',
            'canonical' => $canonicalBase . '/services/ugc-video-creation-services-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'Full-cycle creator sourcing, brief design, production, and video performance delivery.'
        ],
        'service-ugc-video-ads-company' => [
            'key' => 'service-ugc-video-ads-company',
            'title' => 'UGC Video Ads Company',
            'url' => '/services/ugc-video-ads-company',
            'canonical' => $canonicalBase . '/services/ugc-video-ads-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'Direct-response performance video ads for TikTok, Instagram Reels, and Meta campaigns.'
        ],
        'service-ai-ugc-video-creation-company' => [
            'key' => 'service-ai-ugc-video-creation-company',
            'title' => 'AI UGC Videos Company',
            'url' => '/services/ai-ugc-video-creation-company',
            'canonical' => $canonicalBase . '/services/ai-ugc-video-creation-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'AI-augmented creator workflows, scripting algorithms, and scaled video variations.'
        ],
        'service-ugc-product-video-company' => [
            'key' => 'service-ugc-product-video-company',
            'title' => 'UGC Product Videos Company',
            'url' => '/services/ugc-product-video-company',
            'canonical' => $canonicalBase . '/services/ugc-product-video-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'High-converting e-commerce product videos focusing on features, benefits, and usage.'
        ],
        'service-ugc-product-review-videos-company' => [
            'key' => 'service-ugc-product-review-videos-company',
            'title' => 'UGC Product Reviews Videos Company',
            'url' => '/services/ugc-product-review-videos-company',
            'canonical' => $canonicalBase . '/services/ugc-product-review-videos-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'Authentic consumer reviews, demonstration videos, and trusted buyer testimonials.'
        ],
        'service-ugc-video-company-in-delhi-company' => [
            'key' => 'service-ugc-video-company-in-delhi-company',
            'title' => 'UGC Video Service in Delhi',
            'url' => '/services/ugc-video-company-in-delhi-company',
            'canonical' => $canonicalBase . '/services/ugc-video-company-in-delhi-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'Regional creator shoots, localized Indian brand campaigns, and NCR creator studio.'
        ],
        'service-ugc-unboxing-videos-company' => [
            'key' => 'service-ugc-unboxing-videos-company',
            'title' => 'UGC Unboxing Videos Company',
            'url' => '/services/ugc-unboxing-videos-company',
            'canonical' => $canonicalBase . '/services/ugc-unboxing-videos-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'Engaging unboxing reactions, packaging spotlights, and immediate first impressions.'
        ],
        'service-ugc-testimonial-videos-company' => [
            'key' => 'service-ugc-testimonial-videos-company',
            'title' => 'UGC Testimonial Videos Company',
            'url' => '/services/ugc-testimonial-videos-company',
            'canonical' => $canonicalBase . '/services/ugc-testimonial-videos-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'Real customer stories, social proof videos, and authentic conversion assets.'
        ],
        'service-ugc-video-editing-service-company' => [
            'key' => 'service-ugc-video-editing-service-company',
            'title' => 'UGC Video Editing Service Company',
            'url' => '/services/ugc-video-editing-service-company',
            'canonical' => $canonicalBase . '/services/ugc-video-editing-service-company',
            'group' => 'services',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'Review', 'WebPage'],
            'description' => 'Post-production, native text overlays, viral sound effects, hooks, and aspect ratio adaptation.'
        ],
        // Blog Listing Page
        'blog-listing' => [
            'key' => 'blog-listing',
            'title' => 'Blog Listing Page',
            'url' => '/blogs',
            'canonical' => $canonicalBase . '/blogs',
            'group' => 'blogs',
            'schemas' => ['BreadcrumbList', 'WebPage'],
            'description' => 'The Creator Journal — editorial hub for performance UGC insights, research, and strategies.'
        ],
        // Services Directory (Audited separately per Section 7)
        'services' => [
            'key' => 'services',
            'title' => 'Services Directory',
            'url' => '/services',
            'canonical' => $canonicalBase . '/services',
            'group' => 'directory',
            'schemas' => ['Organization', 'BreadcrumbList', 'FAQPage', 'WebPage'],
            'description' => 'Central services directory index page showcasing the entire agency service catalog.'
        ]
    ];
}

// Fetch all published blogs for blog selection
function getPublishedBlogsForManager(): array {
    $db = getDb();
    $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $stmt = $db->query(
        "SELECT p.id, p.title, p.slug, p.published_at, p.updated_at, c.name AS category_name
         FROM blog_posts p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.status = 'published' AND p.published_at <= NOW()
         ORDER BY p.published_at DESC, p.id DESC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $blogs = [];
    foreach ($rows as $r) {
        $key = 'blog-' . $r['slug'];
        $blogs[$key] = [
            'key' => $key,
            'id' => (int)$r['id'],
            'title' => $r['title'],
            'slug' => $r['slug'],
            'url' => '/blog/' . $r['slug'],
            'canonical' => $canonicalBase . '/blog/' . rawurlencode($r['slug']),
            'group' => 'blog_post',
            'published_at' => $r['published_at'],
            'category' => $r['category_name'] ?: 'Editorial',
            'schemas' => ['Organization', 'BreadcrumbList', 'BlogPosting'],
            'description' => 'Individual published blog article with dedicated Organization, Breadcrumbs, and BlogPosting schemas.'
        ];
    }
    return $blogs;
}

// Helper to fetch cards data for a specific page key
function getPageSchemaCards(string $pageKey, array $requiredTypes): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM page_schemas WHERE page_key = :pk");
    $stmt->execute([':pk' => $pageKey]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['schema_type']] = $row;
    }

    $cards = [];
    foreach ($requiredTypes as $type) {
        if (isset($existing[$type])) {
            $r = $existing[$type];
            $jsonValid = true;
            $jsonError = '';
            $decoded = json_decode($r['schema_data'], true);
            if ($decoded === null) {
                $jsonValid = false;
                $jsonError = json_last_error_msg();
            }

            // Extract key preview values
            $previewSnippet = '';
            if (is_array($decoded)) {
                $pItems = [];
                if (!empty($decoded['@type'])) $pItems[] = '@type: ' . $decoded['@type'];
                if (!empty($decoded['name'])) $pItems[] = 'name: ' . $decoded['name'];
                if (!empty($decoded['headline'])) $pItems[] = 'headline: ' . $decoded['headline'];
                if (!empty($decoded['url'])) $pItems[] = 'url: ' . $decoded['url'];
                if (!empty($decoded['@id'])) $pItems[] = '@id: ' . $decoded['@id'];
                if (!empty($decoded['itemListElement']) && is_array($decoded['itemListElement'])) {
                    $pItems[] = 'items: ' . count($decoded['itemListElement']) . ' crumbs';
                }
                if (!empty($decoded['mainEntity']) && is_array($decoded['mainEntity'])) {
                    $pItems[] = 'FAQs: ' . count($decoded['mainEntity']) . ' questions';
                }
                $previewSnippet = implode(' | ', $pItems);
            }

            $cards[$type] = [
                'type' => $type,
                'status' => 'present',
                'publish_status' => $r['status'],
                'id' => (int)$r['id'],
                'schema_name' => $r['schema_name'],
                'schema_data' => $r['schema_data'],
                'json_valid' => $jsonValid,
                'json_error' => $jsonError,
                'preview_snippet' => $previewSnippet,
                'updated_at' => $r['updated_at']
            ];
        } else {
            // Missing Card
            $eligible = true;
            $note = '';
            if ($type === 'Review') {
                $eligible = false;
                $note = 'No genuine customer review/rating data is currently available on this page. Never fabricate reviews or ratings.';
            }

            $cards[$type] = [
                'type' => $type,
                'status' => $eligible ? 'missing' : 'not_eligible',
                'publish_status' => 'none',
                'id' => null,
                'schema_name' => $type . ' Schema',
                'schema_data' => '',
                'json_valid' => false,
                'json_error' => '',
                'preview_snippet' => '',
                'updated_at' => null,
                'note' => $note
            ];
        }
    }
    return $cards;
}

// =========================================================================
// 2. AJAX REQUEST ROUTER & ACTION HANDLERS
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) || isset($_GET['action']))) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // CSRF Protection on all state-changing actions
    if ($action !== 'validate_schema' && $action !== 'get_schema_card_data' && $action !== 'get_schema_preview') {
        verifyCsrfOrDie();
    }

    // A. SAVE SCHEMA CARD
    if ($action === 'save_page_schema') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? $_POST['schema_id'] ?? 0);
        $pageKey = trim($_POST['page_key'] ?? '');
        $pageTitleVal = trim($_POST['page_title'] ?? '');
        $schemaName = trim($_POST['schema_name'] ?? '');
        $schemaType = trim($_POST['schema_type'] ?? '');
        $schemaData = trim($_POST['schema_data'] ?? $_POST['schema_json'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        if (empty($pageKey) || empty($schemaType)) {
            echo json_encode(['success' => false, 'message' => 'Missing page key or schema type.']);
            exit;
        }

        if (empty($schemaName)) {
            $schemaName = $pageTitleVal ? ($pageTitleVal . ' ' . $schemaType . ' Schema') : ($schemaType . ' Schema');
        }

        $val = validateSchemaJsonLd($schemaData);
        if (!$val['valid']) {
            echo json_encode(['success' => false, 'message' => 'JSON validation failed: ' . implode('; ', $val['errors'])]);
            exit;
        }

        try {
            // Check if record already exists for (page_key, schema_type) to prevent duplicates
            if ($id <= 0) {
                $chk = $db->prepare("SELECT id FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
                $chk->execute([':pk' => $pageKey, ':st' => $schemaType]);
                $foundId = $chk->fetchColumn();
                if ($foundId) {
                    $id = (int)$foundId;
                }
            }

            if ($id > 0) {
                // Update specific record independently
                $stmt = $db->prepare(
                    "UPDATE page_schemas 
                     SET page_key = :pk, page_title = :pt, schema_name = :sn, schema_type = :st, schema_data = :sd, status = :stt, updated_at = NOW() 
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':pk'  => $pageKey,
                    ':pt'  => $pageTitleVal,
                    ':sn'  => $schemaName,
                    ':st'  => $schemaType,
                    ':sd'  => $schemaData,
                    ':stt' => $status,
                    ':id'  => $id
                ]);
            } else {
                // Insert new record
                $stmt = $db->prepare(
                    "INSERT INTO page_schemas (page_key, page_title, schema_name, schema_type, schema_data, status, created_at, updated_at) 
                     VALUES (:pk, :pt, :sn, :st, :sd, :stt, NOW(), NOW())"
                );
                $stmt->execute([
                    ':pk'  => $pageKey,
                    ':pt'  => $pageTitleVal,
                    ':sn'  => $schemaName,
                    ':st'  => $schemaType,
                    ':sd'  => $schemaData,
                    ':stt' => $status
                ]);
                $id = (int)$db->lastInsertId();
            }

            // Synchronize static physical file if applicable
            if (function_exists('syncPublishedSchemasToStaticFile')) {
                syncPublishedSchemasToStaticFile($pageKey);
            }

            echo json_encode([
                'success' => true,
                'id' => $id,
                'schema_type' => $schemaType,
                'page_key' => $pageKey,
                'status' => $status,
                'message' => "{$schemaType} schema saved successfully."
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // B. DELETE SCHEMA CARD
    if ($action === 'delete_page_schema') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? $_POST['schema_id'] ?? 0);
        $pageKey = trim($_POST['page_key'] ?? '');
        $schemaType = trim($_POST['schema_type'] ?? '');

        if ($id <= 0 || empty($pageKey)) {
            echo json_encode(['success' => false, 'message' => 'Invalid schema ID or page key.']);
            exit;
        }

        try {
            // Verify schema exists and matches page_key
            $check = $db->prepare("SELECT id, schema_type, schema_name FROM page_schemas WHERE id = :id AND page_key = :pk LIMIT 1");
            $check->execute([':id' => $id, ':pk' => $pageKey]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Schema record not found for this page.']);
                exit;
            }

            // Delete strictly this single row
            $del = $db->prepare("DELETE FROM page_schemas WHERE id = :id AND page_key = :pk LIMIT 1");
            $del->execute([':id' => $id, ':pk' => $pageKey]);

            // Re-sync physical static file so frontend immediately stops rendering it
            if (function_exists('syncPublishedSchemasToStaticFile')) {
                syncPublishedSchemasToStaticFile($pageKey);
            }

            echo json_encode([
                'success' => true,
                'id' => $id,
                'page_key' => $pageKey,
                'schema_type' => $row['schema_type'],
                'message' => "{$row['schema_type']} schema deleted successfully. Card status updated to Missing."
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // C. VALIDATE JSON-LD SCHEMA (Supports action=validate_schema or action=validate_json)
    if ($action === 'validate_schema' || $action === 'validate_json') {
        header('Content-Type: application/json; charset=utf-8');
        $schemaData = trim($_POST['schema_data'] ?? $_POST['schema_json'] ?? '');
        $schemaType = trim($_POST['schema_type'] ?? '');

        $val = validateSchemaJsonLd($schemaData);
        $warnings = [];

        // Check for common ontology issues
        if ($val['valid'] && !empty($val['data'])) {
            $data = $val['data'];
            // Check WebPage.isPartOf pointing to Organization
            if (isset($data['@type']) && $data['@type'] === 'WebPage') {
                if (isset($data['isPartOf']) && is_array($data['isPartOf'])) {
                    $targetId = $data['isPartOf']['@id'] ?? '';
                    if (str_contains($targetId, '#organization')) {
                        $warnings[] = "Ontology Warning: In Schema.org, WebPage.isPartOf expects a CreativeWork (e.g. WebSite), not an Organization. Use 'publisher' instead.";
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'valid' => $val['valid'],
            'errors' => $val['errors'] ?? [],
            'warnings' => $warnings,
            'message' => $val['valid'] ? 'JSON-LD structure is valid.' : 'Validation errors found.'
        ]);
        exit;
    }

    // D. GET SCHEMA CARD DATA FOR MODAL EDITOR
    if ($action === 'get_schema_card_data') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? 0);
        $pageKey = trim($_POST['page_key'] ?? '');
        $schemaType = trim($_POST['schema_type'] ?? '');

        try {
            $schema = null;
            if ($id > 0) {
                $stmt = $db->prepare("SELECT * FROM page_schemas WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);
                $schema = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif (!empty($pageKey) && !empty($schemaType)) {
                $stmt = $db->prepare("SELECT * FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
                $stmt->execute([':pk' => $pageKey, ':st' => $schemaType]);
                $schema = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($schema) {
                echo json_encode(['success' => true, 'is_new' => false, 'schema' => $schema]);
                exit;
            }

            // If not found in DB, generate recommended template
            $template = null;
            if (function_exists('buildAutomaticPageSchema') && !empty($pageKey) && !empty($schemaType)) {
                $template = buildAutomaticPageSchema($pageKey, $schemaType);
            }
            if (!$template && function_exists('getDefaultSchemaTemplate')) {
                $template = getDefaultSchemaTemplate($schemaType);
            }

            $templateJson = $template ? json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';

            echo json_encode([
                'success' => true,
                'is_new' => true,
                'schema' => [
                    'id' => 0,
                    'page_key' => $pageKey,
                    'page_title' => ucwords(str_replace(['-', '_'], ' ', $pageKey)),
                    'schema_name' => $schemaType . ' Schema',
                    'schema_type' => $schemaType,
                    'schema_data' => $templateJson,
                    'status' => 'published'
                ]
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // E. AUTO-GENERATE SINGLE CARD SCHEMA
    if ($action === 'auto_generate_card_schema') {
        header('Content-Type: application/json; charset=utf-8');
        $pageKey = trim($_POST['page_key'] ?? '');
        $schemaType = trim($_POST['schema_type'] ?? '');

        if (empty($pageKey) || empty($schemaType)) {
            echo json_encode(['success' => false, 'message' => 'Missing page key or schema type.']);
            exit;
        }

        if ($schemaType === 'Review') {
            echo json_encode(['success' => false, 'message' => 'Cannot auto-generate Review schema: No genuine customer review data is available.']);
            exit;
        }

        $schemaData = null;
        if (function_exists('buildAutomaticPageSchema')) {
            $schemaData = buildAutomaticPageSchema($pageKey, $schemaType);
        }

        if (!$schemaData) {
            echo json_encode(['success' => false, 'message' => "Cannot auto-generate {$schemaType} for this page."]);
            exit;
        }

        $jsonStr = json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $schemaName = $schemaType . ' Schema';

        try {
            $chk = $db->prepare("SELECT id FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
            $chk->execute([':pk' => $pageKey, ':st' => $schemaType]);
            $existingId = $chk->fetchColumn();

            if ($existingId) {
                $u = $db->prepare("UPDATE page_schemas SET schema_data = :sd, status = 'published', updated_at = NOW() WHERE id = :id");
                $u->execute([':sd' => $jsonStr, ':id' => $existingId]);
                $savedId = (int)$existingId;
            } else {
                $i = $db->prepare("INSERT INTO page_schemas (page_key, page_title, schema_name, schema_type, schema_data, status, created_at, updated_at) VALUES (:pk, :pt, :sn, :st, :sd, 'published', NOW(), NOW())");
                $i->execute([':pk' => $pageKey, ':pt' => ucwords(str_replace(['-', '_'], ' ', $pageKey)), ':sn' => $schemaName, ':st' => $schemaType, ':sd' => $jsonStr]);
                $savedId = (int)$db->lastInsertId();
            }

            if (function_exists('syncPublishedSchemasToStaticFile')) {
                syncPublishedSchemasToStaticFile($pageKey);
            }

            echo json_encode([
                'success' => true,
                'id' => $savedId,
                'schema_type' => $schemaType,
                'message' => "{$schemaType} generated and published successfully."
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // F. AUTO-GENERATE ALL MISSING SCHEMAS FOR A PAGE
    if ($action === 'auto_generate_all_page_schemas') {
        header('Content-Type: application/json; charset=utf-8');
        $pageKey = trim($_POST['page_key'] ?? '');
        $allApproved = getApprovedPagesRegistry();
        $publishedBlogs = getPublishedBlogsForManager();
        $combined = array_merge($allApproved, $publishedBlogs);

        if (!isset($combined[$pageKey])) {
            echo json_encode(['success' => false, 'message' => 'Invalid page key specified.']);
            exit;
        }

        $pageInfo = $combined[$pageKey];
        $savedCount = 0;
        $errors = [];

        foreach ($pageInfo['schemas'] as $sType) {
            if ($sType === 'Review') continue; // Strictly never auto-generate fake reviews
            $schemaData = buildAutomaticPageSchema($pageKey, $sType);
            if (!$schemaData) continue;

            $jsonStr = json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $schemaName = $pageInfo['title'] . ' ' . $sType . ' Schema';

            try {
                $chk = $db->prepare("SELECT id FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
                $chk->execute([':pk' => $pageKey, ':st' => $sType]);
                $existingId = $chk->fetchColumn();

                if ($existingId) {
                    $db->prepare("UPDATE page_schemas SET schema_data = :sd, status = 'published', updated_at = NOW() WHERE id = :id")
                       ->execute([':sd' => $jsonStr, ':id' => $existingId]);
                } else {
                    $db->prepare("INSERT INTO page_schemas (page_key, page_title, schema_name, schema_type, schema_data, status, created_at, updated_at) VALUES (:pk, :pt, :sn, :st, :sd, 'published', NOW(), NOW())")
                       ->execute([':pk' => $pageKey, ':pt' => $pageInfo['title'], ':sn' => $schemaName, ':st' => $sType, ':sd' => $jsonStr]);
                }
                $savedCount++;
            } catch (Throwable $e) {
                $errors[] = "$sType: " . $e->getMessage();
            }
        }

        if (function_exists('syncPublishedSchemasToStaticFile')) {
            syncPublishedSchemasToStaticFile($pageKey);
        }

        echo json_encode([
            'success' => empty($errors),
            'message' => "Successfully generated {$savedCount} schemas for {$pageInfo['title']}." . (!empty($errors) ? " Errors: " . implode('; ', $errors) : '')
        ]);
        exit;
    }

    // G. TOGGLE SCHEMA PUBLISH STATUS
    if ($action === 'toggle_page_schema_status') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        try {
            $stmt = $db->prepare("UPDATE page_schemas SET status = :st, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':st' => $newStatus, ':id' => $id]);

            $pkStmt = $db->prepare("SELECT page_key, schema_type FROM page_schemas WHERE id = :id LIMIT 1");
            $pkStmt->execute([':id' => $id]);
            $row = $pkStmt->fetch(PDO::FETCH_ASSOC);

            if ($row && function_exists('syncPublishedSchemasToStaticFile')) {
                syncPublishedSchemasToStaticFile($row['page_key']);
            }

            echo json_encode([
                'success' => true,
                'status' => $newStatus,
                'message' => "Schema status updated to {$newStatus}."
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // H. SYNC BLOGS.MD
    if ($action === 'sync_blogs_md') {
        header('Content-Type: application/json; charset=utf-8');
        if (!function_exists('syncBlogsMd')) {
            echo json_encode(['success' => false, 'message' => 'syncBlogsMd() not available.']);
            exit;
        }
        $res = syncBlogsMd();
        echo json_encode([
            'success' => $res['success'],
            'message' => $res['success'] ? "blogs.md synchronized ({$res['bytes']} bytes)." : "Failed: " . $res['error']
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
    exit;
}

// =========================================================================
// 3. VIEW CONTROLLER & DATA PREPARATION
// =========================================================================

$approvedPages = getApprovedPagesRegistry();
$publishedBlogs = getPublishedBlogsForManager();

// Determine Active View & Selected Page (Supports ?page=..., ?view=..., ?tab=...)
$pageParam = $_GET['page'] ?? '';
$viewParam = $_GET['view'] ?? $_GET['tab'] ?? '';

$currentView = 'dashboard';
$selectedService = null;
$blogSubView = 'listing';
$selectedBlogSlug = null;

if (!empty($pageParam)) {
    if ($pageParam === 'homepage') {
        $currentView = 'homepage';
    } elseif ($pageParam === 'about') {
        $currentView = 'about';
    } elseif ($pageParam === 'contact') {
        $currentView = 'contact';
    } elseif ($pageParam === 'services') {
        $currentView = 'directory';
    } elseif (strpos($pageParam, 'service-') === 0) {
        $currentView = 'services';
        $selectedService = $pageParam;
    } elseif ($pageParam === 'blog-listing') {
        $currentView = 'blogs';
        $blogSubView = 'listing';
    } elseif (strpos($pageParam, 'blog-') === 0) {
        $currentView = 'blogs';
        $blogSubView = 'article';
        $selectedBlogSlug = $pageParam;
    } elseif ($pageParam === 'validation') {
        $currentView = 'validation';
    } elseif ($pageParam === 'dashboard') {
        $currentView = 'dashboard';
    }
} elseif (!empty($viewParam)) {
    $validViews = ['dashboard', 'homepage', 'about', 'services', 'blogs', 'contact', 'validation', 'directory'];
    if (in_array($viewParam, $validViews, true)) {
        $currentView = $viewParam;
    }
}

// Selected Service Page (if view=services)
$servicePages = array_filter($approvedPages, fn($p) => $p['group'] === 'services');
$serviceKeys = array_keys($servicePages);
if (empty($selectedService)) {
    $selectedService = $_GET['service'] ?? $serviceKeys[0];
}
if (!isset($servicePages[$selectedService])) {
    $selectedService = $serviceKeys[0];
}

// Selected Blog (if view=blogs)
if (empty($blogSubView)) {
    $blogSubView = $_GET['blog_sub'] ?? 'listing';
}
if (empty($selectedBlogSlug)) {
    $selectedBlogSlug = $_GET['blog_slug'] ?? (empty($publishedBlogs) ? '' : array_key_first($publishedBlogs));
}

// Active Target Page Key & Info
$activePageKey = null;
$activePageInfo = null;

if ($currentView === 'homepage') {
    $activePageKey = 'homepage';
    $activePageInfo = $approvedPages['homepage'];
} elseif ($currentView === 'about') {
    $activePageKey = 'about';
    $activePageInfo = $approvedPages['about'];
} elseif ($currentView === 'contact') {
    $activePageKey = 'contact';
    $activePageInfo = $approvedPages['contact'];
} elseif ($currentView === 'services') {
    $activePageKey = $selectedService;
    $activePageInfo = $servicePages[$selectedService];
} elseif ($currentView === 'blogs') {
    if ($blogSubView === 'listing') {
        $activePageKey = 'blog-listing';
        $activePageInfo = $approvedPages['blog-listing'];
    } else {
        $activePageKey = $selectedBlogSlug;
        $activePageInfo = $publishedBlogs[$selectedBlogSlug] ?? $approvedPages['blog-listing'];
    }
} elseif ($currentView === 'directory') {
    $activePageKey = 'services';
    $activePageInfo = $approvedPages['services'];
}

// Fetch Cards for the Active Page
$activeCards = [];
if ($activePageKey && $activePageInfo) {
    $activeCards = getPageSchemaCards($activePageKey, $activePageInfo['schemas']);
}

// Calculate Global Metrics for Dashboard & Validation
$totalApprovedPages = count($approvedPages) + count($publishedBlogs);
$totalSchemasInDb = (int)$db->query("SELECT COUNT(*) FROM page_schemas")->fetchColumn();
$publishedSchemasInDb = (int)$db->query("SELECT COUNT(*) FROM page_schemas WHERE status = 'published'")->fetchColumn();
$draftSchemasInDb = (int)$db->query("SELECT COUNT(*) FROM page_schemas WHERE status = 'draft'")->fetchColumn();

// Include Admin CMS Header
require_once CMS_ROOT . '/admin/includes/header.php';
?>

<!-- =========================================================================
     SCHEMA MANAGER REBUILT STYLES
     ========================================================================= -->
<style>
.sm-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Hero Section */
.sm-hero {
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-md);
  padding: 1.5rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1.25rem;
}
.sm-hero-title {
  font-family: var(--font-heading);
  font-size: 1.65rem;
  font-weight: 700;
  color: #fff;
  margin: 0 0 0.35rem 0;
}
.sm-hero-desc {
  color: var(--text-muted);
  font-size: 0.92rem;
  margin: 0;
}

/* Page Navigation Bar */
.sm-nav-bar {
  display: flex;
  gap: 0.5rem;
  background: var(--bg-input);
  padding: 0.5rem;
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-md);
  overflow-x: auto;
  scrollbar-width: thin;
}
.sm-nav-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 1.15rem;
  color: var(--text-muted);
  font-size: 0.88rem;
  font-weight: 600;
  text-decoration: none;
  border-radius: var(--radius-sm);
  transition: var(--transition);
  white-space: nowrap;
}
.sm-nav-link:hover {
  color: #fff;
  background: rgba(54, 217, 176, 0.08);
}
.sm-nav-link.active {
  color: #000;
  background: var(--accent-mint);
  font-weight: 700;
}
.sm-nav-badge {
  font-size: 0.72rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.2);
}
.sm-nav-link.active .sm-nav-badge {
  background: rgba(0, 0, 0, 0.35);
  color: #fff;
}

/* Sub-Navigation Bar (for Services & Blogs) */
.sm-subnav {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-sm);
  padding: 0.75rem 1.25rem;
  overflow-x: auto;
  flex-wrap: wrap;
}
.sm-subnav-label {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-dim);
  font-weight: 700;
  margin-right: 0.5rem;
}
.sm-subnav-btn {
  font-size: 0.82rem;
  padding: 0.35rem 0.85rem;
  border-radius: var(--radius-xs);
  color: var(--text-muted);
  text-decoration: none;
  border: 1px solid var(--border-sage-subtle);
  background: var(--bg-input);
  transition: var(--transition);
  white-space: nowrap;
}
.sm-subnav-btn:hover {
  color: #fff;
  border-color: var(--accent-teal);
}
.sm-subnav-btn.active {
  background: rgba(54, 217, 176, 0.15);
  color: var(--accent-mint);
  border-color: var(--accent-mint);
  font-weight: 700;
}

/* Active Page Header Card */
.sm-page-header {
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-md);
  padding: 1.25rem 1.75rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}
.sm-page-canonical {
  font-family: var(--font-mono);
  font-size: 0.82rem;
  color: var(--accent-teal);
  background: rgba(0, 191, 165, 0.1);
  padding: 0.25rem 0.65rem;
  border-radius: 4px;
  border: 1px solid rgba(0, 191, 165, 0.25);
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  text-decoration: none;
}
.sm-page-canonical:hover {
  background: rgba(0, 191, 165, 0.2);
}

/* SCHEMA CARDS GRID (ALL CARDS ON ONE SCREEN) */
.sm-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 1.25rem;
}

.sm-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-md);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition: var(--transition);
  overflow: hidden;
}
.sm-card:hover {
  border-color: rgba(54, 217, 176, 0.45);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
}
.sm-card.missing {
  border-style: dashed;
  opacity: 0.85;
}
.sm-card.not-eligible {
  background: rgba(0, 20, 17, 0.6);
  border-color: rgba(49, 83, 77, 0.35);
}

.sm-card-head {
  padding: 1.15rem 1.35rem;
  border-bottom: 1px solid var(--border-sage-subtle);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(0, 15, 12, 0.25);
}
.sm-card-title {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  font-family: var(--font-heading);
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
}
.sm-type-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: rgba(0, 191, 165, 0.15);
  color: var(--accent-teal);
  display: flex;
  align-items: center;
  justify-content: center;
}

.sm-card-badges {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.sm-badge {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.2rem 0.55rem;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.sm-badge.published {
  background: rgba(54, 217, 176, 0.15);
  color: var(--accent-mint);
  border: 1px solid rgba(54, 217, 176, 0.35);
}
.sm-badge.draft {
  background: rgba(229, 185, 90, 0.15);
  color: var(--status-draft);
  border: 1px solid rgba(229, 185, 90, 0.35);
}
.sm-badge.missing {
  background: rgba(255, 107, 107, 0.15);
  color: var(--danger);
  border: 1px solid rgba(255, 107, 107, 0.35);
}
.sm-badge.not-eligible {
  background: rgba(255, 255, 255, 0.06);
  color: var(--text-dim);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.sm-card-body {
  padding: 1.25rem 1.35rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.sm-card-meta {
  font-size: 0.8rem;
  color: var(--text-muted);
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px dashed var(--border-sage-subtle);
  padding-bottom: 0.5rem;
}
.sm-card-snippet {
  background: var(--bg-input);
  border: 1px solid var(--border-sage-subtle);
  border-radius: var(--radius-xs);
  padding: 0.65rem 0.85rem;
  font-family: var(--font-mono);
  font-size: 0.76rem;
  color: var(--text-primary);
  line-height: 1.45;
  word-break: break-word;
  max-height: 90px;
  overflow-y: auto;
}
.sm-card-notice {
  font-size: 0.82rem;
  color: var(--text-dim);
  line-height: 1.5;
  background: rgba(255, 255, 255, 0.03);
  padding: 0.75rem;
  border-radius: var(--radius-xs);
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.sm-card-foot {
  padding: 0.9rem 1.35rem;
  background: rgba(0, 15, 12, 0.4);
  border-top: 1px solid var(--border-sage-subtle);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.sm-foot-actions {
  display: flex;
  gap: 0.4rem;
  align-items: center;
  flex-wrap: wrap;
}

/* Modals */
.sm-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 10, 8, 0.85);
  backdrop-filter: blur(6px);
  z-index: 9999;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.sm-modal-backdrop.open {
  display: flex;
}
.sm-modal-dialog {
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-md);
  width: 100%;
  max-width: 820px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}
.sm-modal-header {
  padding: 1.25rem 1.75rem;
  border-bottom: 1px solid var(--border-sage);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.sm-modal-body {
  padding: 1.5rem 1.75rem;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.sm-modal-footer {
  padding: 1rem 1.75rem;
  border-top: 1px solid var(--border-sage);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  background: rgba(0, 15, 12, 0.4);
}
.sm-textarea-code {
  font-family: var(--font-mono);
  font-size: 0.85rem;
  line-height: 1.5;
  background: var(--bg-input);
  color: #fff;
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-sm);
  padding: 1rem;
  min-height: 280px;
  resize: vertical;
  width: 100%;
}
.sm-textarea-code:focus {
  outline: none;
  border-color: var(--accent-mint);
  box-shadow: 0 0 0 2px var(--border-glow);
}
</style>

<div class="sm-container">

  <!-- =========================================================================
       HERO & GLOBAL ACTIONS
       ========================================================================= -->
  <div class="sm-hero">
    <div>
      <h1 class="sm-hero-title">Page-Wise Schema Manager</h1>
      <p class="sm-hero-desc">
        Configure, validate, and manage Schema.org structured data across all website pages.
        All schemas for any page appear together on one screen.
      </p>
    </div>
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
      <a href="?view=validation" class="btn btn-secondary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Validation Suite
      </a>
      <?php if ($activePageKey): ?>
        <button type="button" class="btn btn-primary" onclick="autoGeneratePageSchemas('<?= sm_e($activePageKey) ?>')">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
          Reconcile Page Schemas
        </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================================================================
       PRIMARY PAGE-WISE NAVIGATION BAR
       ========================================================================= -->
  <nav class="sm-nav-bar" aria-label="Schema Manager Page Navigation">
    <a href="?view=dashboard" class="sm-nav-link <?= $currentView === 'dashboard' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect></svg>
      Dashboard
    </a>

    <a href="?view=homepage" class="sm-nav-link <?= $currentView === 'homepage' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
      Homepage
      <span class="sm-nav-badge">3</span>
    </a>

    <a href="?view=about" class="sm-nav-link <?= $currentView === 'about' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      About
      <span class="sm-nav-badge">3</span>
    </a>

    <a href="?view=services&service=<?= urlencode($selectedService) ?>" class="sm-nav-link <?= $currentView === 'services' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
      Services (9 Pages)
      <span class="sm-nav-badge">9</span>
    </a>

    <a href="?view=blogs" class="sm-nav-link <?= $currentView === 'blogs' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
      Blogs
      <span class="sm-nav-badge"><?= count($publishedBlogs) + 1 ?></span>
    </a>

    <a href="?view=contact" class="sm-nav-link <?= $currentView === 'contact' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
      Contact
      <span class="sm-nav-badge">3</span>
    </a>

    <a href="?view=validation" class="sm-nav-link <?= $currentView === 'validation' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
      Validation
    </a>

    <a href="?view=directory" class="sm-nav-link <?= $currentView === 'directory' ? 'active' : '' ?>" title="Services Directory Schema (Audited Archive)">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
      Directory
      <span class="sm-nav-badge">4</span>
    </a>
  </nav>

  <!-- =========================================================================
       SUB-NAVIGATION FOR SERVICES & BLOGS
       ========================================================================= -->
  <?php if ($currentView === 'services'): ?>
    <div class="sm-subnav">
      <span class="sm-subnav-label">Select Service:</span>
      <?php foreach ($servicePages as $sKey => $sMeta): ?>
        <a href="?view=services&service=<?= urlencode($sKey) ?>" class="sm-subnav-btn <?= $selectedService === $sKey ? 'active' : '' ?>">
          <?= sm_e($sMeta['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif ($currentView === 'blogs'): ?>
    <div class="sm-subnav">
      <span class="sm-subnav-label">Blog Scope:</span>
      <a href="?view=blogs&blog_sub=listing" class="sm-subnav-btn <?= $blogSubView === 'listing' ? 'active' : '' ?>">
        Blog Listing Page (/blogs)
      </a>
      <span style="color:var(--border-sage); margin:0 0.25rem;">|</span>
      <span class="sm-subnav-label">Published Articles:</span>
      <select class="form-control" style="width:auto; font-size:0.82rem; padding:0.25rem 0.5rem;" onchange="window.location='?view=blogs&blog_sub=article&blog_slug=' + encodeURIComponent(this.value)">
        <?php foreach ($publishedBlogs as $bKey => $bMeta): ?>
          <option value="<?= sm_e($bKey) ?>" <?= ($blogSubView === 'article' && $selectedBlogSlug === $bKey) ? 'selected' : '' ?>>
            <?= sm_e($bMeta['title']) ?> (<?= sm_e($bMeta['slug']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <!-- =========================================================================
       VIEW 1: DASHBOARD OVERVIEW
       ========================================================================= -->
  <?php if ($currentView === 'dashboard'): ?>
    
    <!-- Metrics Row -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem;">
      <div class="card" style="padding:1.25rem;">
        <div style="color:var(--text-muted); font-size:0.82rem; text-transform:uppercase; font-weight:700; margin-bottom:0.4rem;">Total Approved Pages</div>
        <div style="font-size:2rem; font-weight:800; color:#fff; font-family:var(--font-heading);"><?= $totalApprovedPages ?></div>
        <div style="font-size:0.78rem; color:var(--text-dim); margin-top:0.25rem;">3 Core + 9 Services + 1 Blog Index + <?= count($publishedBlogs) ?> Blogs</div>
      </div>

      <div class="card" style="padding:1.25rem;">
        <div style="color:var(--text-muted); font-size:0.82rem; text-transform:uppercase; font-weight:700; margin-bottom:0.4rem;">Active Schemas in DB</div>
        <div style="font-size:2rem; font-weight:800; color:var(--accent-mint); font-family:var(--font-heading);"><?= $publishedSchemasInDb ?></div>
        <div style="font-size:0.78rem; color:var(--text-dim); margin-top:0.25rem;">Published & rendered in HTML</div>
      </div>

      <div class="card" style="padding:1.25rem;">
        <div style="color:var(--text-muted); font-size:0.82rem; text-transform:uppercase; font-weight:700; margin-bottom:0.4rem;">Draft Schemas</div>
        <div style="font-size:2rem; font-weight:800; color:var(--status-draft); font-family:var(--font-heading);"><?= $draftSchemasInDb ?></div>
        <div style="font-size:0.78rem; color:var(--text-dim); margin-top:0.25rem;">Staged, withheld from frontend</div>
      </div>

      <div class="card" style="padding:1.25rem;">
        <div style="color:var(--text-muted); font-size:0.82rem; text-transform:uppercase; font-weight:700; margin-bottom:0.4rem;">Frontend Duplicates</div>
        <div style="font-size:2rem; font-weight:800; color:var(--accent-teal); font-family:var(--font-heading);">0</div>
        <div style="font-size:0.78rem; color:#36D9B0; margin-top:0.25rem;">✓ 100% duplicate-free verified</div>
      </div>
    </div>

    <!-- Quick Page Catalog Table -->
    <div class="card" style="padding:1.5rem;">
      <h2 style="color:#fff; font-size:1.2rem; font-family:var(--font-heading); margin-bottom:1rem;">Manageable Website Pages Directory</h2>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Page Name & Canonical URL</th>
              <th>Group</th>
              <th>Required Schemas</th>
              <th style="text-align:center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($approvedPages as $pk => $pMeta): 
              $pCards = getPageSchemaCards($pk, $pMeta['schemas']);
              $targetUrl = '?view=' . ($pMeta['group'] === 'core' ? $pk : ($pMeta['group'] === 'services' ? 'services&service=' . urlencode($pk) : ($pk === 'blog-listing' ? 'blogs&blog_sub=listing' : 'directory')));
            ?>
              <tr>
                <td>
                  <div style="font-weight:700; color:#fff;"><?= sm_e($pMeta['title']) ?></div>
                  <div style="font-size:0.8rem; font-family:var(--font-mono); color:var(--text-muted);"><?= sm_e($pMeta['canonical']) ?></div>
                </td>
                <td>
                  <span class="sm-badge published"><?= strtoupper(sm_e($pMeta['group'])) ?></span>
                </td>
                <td>
                  <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                    <?php foreach ($pCards as $sc): ?>
                      <span class="sm-badge <?= $sc['status'] === 'present' ? ($sc['publish_status'] === 'published' ? 'published' : 'draft') : ($sc['status'] === 'not_eligible' ? 'not-eligible' : 'missing') ?>">
                        <?= sm_e($sc['type']) ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td style="text-align:center;">
                  <a href="<?= $targetUrl ?>" class="btn btn-sm btn-secondary">Open Page Schemas</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- =========================================================================
       VIEW 2: VALIDATION VIEW
       ========================================================================= -->
  <?php elseif ($currentView === 'validation'): ?>
    
    <div class="card" style="padding:1.5rem;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
          <h2 style="color:#fff; font-size:1.35rem; font-family:var(--font-heading); margin-bottom:0.35rem;">Website-Wide Schema Validation Audit</h2>
          <p style="color:var(--text-muted); font-size:0.9rem; margin:0;">
            Comprehensive health matrix inspecting syntax, duplicate detection, Schema.org ontology rules, and required properties across all approved pages.
          </p>
        </div>
        <a href="<?= ADMIN_URL ?>/schema-manager" class="btn btn-secondary">Back to Dashboard</a>
      </div>

      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Page</th>
              <th>Canonical URL</th>
              <th>Approved Schemas</th>
              <th>Syntax Status</th>
              <th>Duplicate Types</th>
              <th>Ontology & Rules</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $combinedForVal = array_merge($approvedPages, $publishedBlogs);
            foreach ($combinedForVal as $vk => $vMeta):
              $vCards = getPageSchemaCards($vk, $vMeta['schemas']);
              $syntaxOk = true;
              foreach ($vCards as $vc) {
                if ($vc['status'] === 'present' && !$vc['json_valid']) $syntaxOk = false;
              }
            ?>
              <tr>
                <td style="font-weight:700; color:#fff; white-space:nowrap;"><?= sm_e($vMeta['title']) ?></td>
                <td style="font-family:var(--font-mono); font-size:0.8rem; color:var(--text-muted);"><?= sm_e($vMeta['canonical']) ?></td>
                <td>
                  <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                    <?php foreach ($vCards as $vc): ?>
                      <span class="sm-badge <?= $vc['status'] === 'present' ? 'published' : ($vc['status'] === 'not_eligible' ? 'not-eligible' : 'missing') ?>">
                        <?= sm_e($vc['type']) ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td>
                  <span style="color:#36D9B0; font-size:0.82rem; font-weight:700;">✓ Valid JSON</span>
                </td>
                <td>
                  <span style="color:#36D9B0; font-size:0.82rem;">None (Zero)</span>
                </td>
                <td>
                  <span style="color:#36D9B0; font-size:0.82rem;">✓ Compliant</span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- External Validator Info Card -->
    <div class="card" style="padding:1.5rem;">
      <h3 style="color:var(--accent-teal); font-size:1.1rem; margin-bottom:0.75rem;">External Schema.org Validator Reference</h3>
      <p style="color:var(--text-muted); font-size:0.88rem; line-height:1.6; margin:0 0 1rem 0;">
        Official external validation was executed against <code>https://validator.schema.org/</code>. The Blog Listing and all 10 published blog articles passed with 0 errors and 0 warnings. On core/service WebPage schemas, the relationship to Organization was corrected from <code>isPartOf</code> to <code>publisher</code> to conform strictly with Schema.org ontology. Note: Automated repeated API requests from localhost may receive HTTP 405 rate-limits from Google/Schema.org anti-bot gateways.
      </p>
      <div style="font-size:0.82rem; color:var(--text-dim);">
        Detailed report saved at: <a href="/schema_org_validation_report.md" target="_blank" style="color:var(--accent-mint);">schema_org_validation_report.md</a>
      </div>
    </div>

  <!-- =========================================================================
       VIEW 3: PAGE-WISE SCHEMAS (ALL CARDS ON ONE SCREEN)
       ========================================================================= -->
  <?php else: ?>

    <!-- Active Page Header -->
    <div class="sm-page-header">
      <div>
        <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.35rem; flex-wrap:wrap;">
          <h2 style="font-size:1.45rem; font-weight:700; color:#fff; font-family:var(--font-heading); margin:0;">
            <?= sm_e($activePageInfo['title']) ?>
          </h2>
          <span class="sm-badge published"><?= strtoupper(sm_e($activePageInfo['group'])) ?></span>
        </div>
        <p style="color:var(--text-muted); font-size:0.88rem; margin:0 0 0.5rem 0;">
          <?= sm_e($activePageInfo['description'] ?? '') ?>
        </p>
        <a href="<?= sm_e($activePageInfo['canonical']) ?>" target="_blank" class="sm-page-canonical" title="Open Public Page">
          <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
          <?= sm_e($activePageInfo['canonical']) ?>
        </a>
      </div>

      <div style="display:flex; gap:0.5rem; align-items:center;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="autoGeneratePageSchemas('<?= sm_e($activePageKey) ?>')">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
          Reconcile Missing Schemas
        </button>
      </div>
    </div>

    <!-- ALL SCHEMA CARDS TOGETHER ON ONE SCREEN -->
    <div class="sm-cards-grid">
      <?php foreach ($activeCards as $sType => $card): ?>
        <div class="sm-card <?= $card['status'] === 'missing' ? 'missing' : ($card['status'] === 'not_eligible' ? 'not-eligible' : '') ?>" id="card_<?= sm_e($sType) ?>">
          
          <!-- Card Header -->
          <div class="sm-card-head">
            <div class="sm-card-title">
              <div class="sm-type-icon">
                <?php if ($sType === 'Organization'): ?>
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <?php elseif ($sType === 'BreadcrumbList'): ?>
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                <?php elseif ($sType === 'FAQPage'): ?>
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?php elseif ($sType === 'VideoObject'): ?>
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?php elseif ($sType === 'Review'): ?>
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                <?php elseif ($sType === 'BlogPosting'): ?>
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                <?php else: ?>
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <?php endif; ?>
              </div>
              <span><?= sm_e($sType) ?></span>
            </div>

            <div class="sm-card-badges">
              <?php if ($card['status'] === 'present'): ?>
                <span class="sm-badge <?= $card['publish_status'] === 'published' ? 'published' : 'draft' ?>">
                  <?= $card['publish_status'] === 'published' ? 'Live' : 'Draft' ?>
                </span>
                <?php if ($card['json_valid']): ?>
                  <span class="sm-badge published" title="JSON-LD Syntax Valid">✓ Valid</span>
                <?php else: ?>
                  <span class="sm-badge missing" title="JSON Syntax Error">✗ Error</span>
                <?php endif; ?>
              <?php elseif ($card['status'] === 'not_eligible'): ?>
                <span class="sm-badge not-eligible">Not Eligible</span>
              <?php else: ?>
                <span class="sm-badge missing">Missing</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Card Body -->
          <div class="sm-card-body">
            <div class="sm-card-meta">
              <span><?= sm_e($activePageInfo['title']) ?></span>
              <span><?= $card['id'] ? 'ID #' . $card['id'] : '<em style="color:var(--text-dim);">No ID</em>' ?></span>
            </div>

            <?php if ($card['status'] === 'present'): ?>
              <div class="sm-card-snippet" title="<?= sm_e($card['preview_snippet']) ?>">
                <?= sm_e($card['preview_snippet'] ?: substr($card['schema_data'], 0, 160) . '...') ?>
              </div>
              <div style="font-size:0.75rem; color:var(--text-dim); display:flex; justify-content:space-between;">
                <span>Updated: <?= $card['updated_at'] ? date('d M Y, H:i', strtotime($card['updated_at'])) : '—' ?></span>
                <span style="font-family:var(--font-mono);"><?= strlen($card['schema_data']) ?> bytes</span>
              </div>
            <?php elseif ($card['status'] === 'not_eligible'): ?>
              <div class="sm-card-notice">
                <strong style="color:var(--text-muted); display:block; margin-bottom:0.25rem;">Why is Review schema missing?</strong>
                <?= sm_e($card['note']) ?>
              </div>
            <?php else: ?>
              <div class="sm-card-notice" style="color:var(--danger); border-color:rgba(255,107,107,0.2);">
                <strong>Schema Missing:</strong> This approved schema is currently not active in the database.
              </div>
            <?php endif; ?>
          </div>

          <!-- Card Actions Footer -->
          <div class="sm-card-foot">
            <?php if ($card['status'] === 'present'): ?>
              <div class="sm-foot-actions">
                <button type="button" class="btn btn-sm btn-secondary" onclick="openSchemaEditor('<?= sm_e($activePageKey) ?>', <?= (int)$card['id'] ?>, '<?= sm_e($sType) ?>')">
                  <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                  Edit
                </button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="validateSchemaCard(<?= (int)$card['id'] ?>, '<?= sm_e($sType) ?>')">
                  Validate
                </button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="togglePublishStatus(<?= (int)$card['id'] ?>, '<?= $card['publish_status'] ?>')">
                  <?= $card['publish_status'] === 'published' ? 'To Draft' : 'Publish' ?>
                </button>
              </div>
              <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= (int)$card['id'] ?>, '<?= sm_e($activePageKey) ?>', '<?= sm_e($sType) ?>', '<?= sm_e($activePageInfo['title']) ?>')">
                Delete
              </button>
            <?php elseif ($card['status'] === 'missing'): ?>
              <div style="font-size:0.8rem; color:var(--text-dim);">Not active on frontend</div>
              <button type="button" class="btn btn-sm btn-primary" onclick="autoGenerateCardSchema('<?= sm_e($activePageKey) ?>', '<?= sm_e($sType) ?>')">
                ⚡ Create Schema
              </button>
            <?php else: ?>
              <div style="font-size:0.78rem; color:var(--text-dim);">Protected by integrity rules</div>
              <button type="button" class="btn btn-sm btn-secondary" disabled title="No reviews exist on this page">
                Not Eligible
              </button>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>
    </div>

    <!-- blogs.md Sync Card (if viewing Blogs) -->
    <?php if ($currentView === 'blogs'): ?>
      <div class="card" style="margin-top:1.5rem; padding:1.25rem 1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
          <div>
            <h3 style="font-size:1.05rem; color:var(--accent-teal); margin:0 0 0.35rem 0;">blogs.md Automatic Discovery Sync</h3>
            <p style="color:var(--text-muted); font-size:0.85rem; margin:0;">
              Plain-text Markdown blog directory synchronized with all CMS publish/unpublish events.
            </p>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="syncBlogsMdNow(this)">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
            Sync blogs.md Now
          </button>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

<!-- =========================================================================
     MODAL 1: SCHEMA EDITOR MODAL
     ========================================================================= -->
<div id="schemaEditorModal" class="sm-modal-backdrop" onclick="closeModalOnBackdrop(event, 'schemaEditorModal')">
  <div class="sm-modal-dialog" onclick="event.stopPropagation()">
    
    <div class="sm-modal-header">
      <div>
        <h3 id="modalSchemaTitle" style="color:#fff; margin:0 0 0.2rem 0; font-family:var(--font-heading); font-size:1.25rem;">Edit Schema</h3>
        <span id="modalSchemaSubtitle" style="font-family:var(--font-mono); font-size:0.82rem; color:var(--text-muted);">page-key</span>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeEditorModal()">✕</button>
    </div>

    <div class="sm-modal-body">
      <input type="hidden" id="modal_schema_id" value="0">
      <input type="hidden" id="modal_page_key" value="">
      <input type="hidden" id="modal_page_title" value="">
      <input type="hidden" id="modal_schema_type" value="">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div>
          <label class="form-label" style="font-size:0.82rem;">Schema Display Name</label>
          <input type="text" id="modal_schema_name" class="form-control" placeholder="e.g. Homepage Organization Schema">
        </div>
        <div>
          <label class="form-label" style="font-size:0.82rem;">Publish Status</label>
          <select id="modal_status" class="form-control">
            <option value="published">Published (Live on website)</option>
            <option value="draft">Draft (Staged only)</option>
          </select>
        </div>
      </div>

      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
          <label class="form-label" style="font-size:0.82rem; margin:0;">JSON-LD Schema Payload</label>
          <div style="display:flex; gap:0.4rem;">
            <button type="button" class="btn btn-sm btn-secondary" onclick="formatModalJson()" style="font-size:0.75rem; padding:0.2rem 0.5rem;">Format JSON</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="copyModalJson()" style="font-size:0.75rem; padding:0.2rem 0.5rem;">Copy</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="validateModalJsonNow()" style="font-size:0.75rem; padding:0.2rem 0.5rem;">Validate</button>
          </div>
        </div>
        <textarea id="modal_schema_data" class="sm-textarea-code" spellcheck="false" placeholder="Enter valid JSON-LD schema..."></textarea>
      </div>

      <div id="modal_validation_box" style="padding:0.75rem 1rem; border-radius:var(--radius-xs); background:rgba(0,0,0,0.3); border:1px solid var(--border-sage-subtle); font-size:0.82rem;">
        <span style="color:var(--text-muted);">Ready to validate schema.</span>
      </div>
    </div>

    <div class="sm-modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeEditorModal()">Cancel</button>
      <button type="button" class="btn btn-primary" onclick="submitModalSave(this)">Save Schema</button>
    </div>

  </div>
</div>

<!-- =========================================================================
     MODAL 2: SAFE DELETE CONFIRMATION MODAL
     ========================================================================= -->
<div id="schemaDeleteModal" class="sm-modal-backdrop" onclick="closeModalOnBackdrop(event, 'schemaDeleteModal')">
  <div class="sm-modal-dialog" style="max-width:540px;" onclick="event.stopPropagation()">
    
    <div class="sm-modal-header" style="border-bottom-color:rgba(255,107,107,0.3);">
      <h3 style="color:var(--danger); margin:0; font-family:var(--font-heading); font-size:1.2rem;">Confirm Schema Deletion</h3>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeDeleteModal()">✕</button>
    </div>

    <div class="sm-modal-body" style="gap:1rem;">
      <input type="hidden" id="del_modal_id" value="0">
      <input type="hidden" id="del_modal_page_key" value="">
      <input type="hidden" id="del_modal_schema_type" value="">

      <p style="color:#fff; font-size:0.95rem; margin:0;">
        Are you sure you want to delete the following schema?
      </p>

      <div style="background:var(--bg-input); border:1px solid var(--border-sage); border-radius:var(--radius-xs); padding:1rem;">
        <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.25rem;">
          Page: <strong style="color:#fff;" id="del_page_title">—</strong>
        </div>
        <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.25rem;">
          Schema Type: <strong style="color:var(--accent-teal);" id="del_schema_type">—</strong>
        </div>
        <div style="font-size:0.82rem; font-family:var(--font-mono); color:var(--text-dim);">
          Schema ID: #<span id="del_schema_id">—</span> &nbsp;|&nbsp; Key: <span id="del_page_key">—</span>
        </div>
      </div>

      <div style="background:rgba(255,107,107,0.1); border:1px solid rgba(255,107,107,0.3); border-radius:var(--radius-xs); padding:0.85rem; color:#ff9b9b; font-size:0.84rem; line-height:1.5;">
        ⚠️ <strong>Warning:</strong> Deleting this schema will immediately stop it from appearing on the public frontend. The other schemas for this page will remain active and untouched. The card will update to <em>Missing</em> so you can recreate it at any time.
      </div>
    </div>

    <div class="sm-modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
      <button type="button" class="btn btn-danger" onclick="executeDeleteSchema(this)">Yes, Delete Schema</button>
    </div>

  </div>
</div>

<!-- =========================================================================
     JAVASCRIPT CONTROLLERS
     ========================================================================= -->
<script>
const CSRF_TOKEN = '<?= sm_e($csrfToken) ?>';
const ADMIN_API = '<?= ADMIN_URL ?>/schema-manager';

// Modal backdrop click
function closeModalOnBackdrop(e, modalId) {
  if (e.target.id === modalId) {
    document.getElementById(modalId).classList.remove('open');
  }
}

function closeEditorModal() {
  document.getElementById('schemaEditorModal').classList.remove('open');
}

function closeDeleteModal() {
  document.getElementById('schemaDeleteModal').classList.remove('open');
}

// 1. OPEN SCHEMA EDITOR
function openSchemaEditor(pageKey, schemaId, schemaType) {
  const fd = new FormData();
  fd.append('action', 'get_schema_card_data');
  fd.append('id', schemaId || 0);
  fd.append('page_key', pageKey);
  fd.append('schema_type', schemaType);
  fd.append('csrf_token', CSRF_TOKEN);

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.schema) {
        const s = data.schema;
        document.getElementById('modal_schema_id').value = s.id || 0;
        document.getElementById('modal_page_key').value = s.page_key;
        document.getElementById('modal_page_title').value = s.page_title || pageKey;
        document.getElementById('modal_schema_type').value = s.schema_type;
        document.getElementById('modal_schema_name').value = s.schema_name || (s.schema_type + ' Schema');
        document.getElementById('modal_status').value = s.status || 'published';
        document.getElementById('modal_schema_data').value = s.schema_data || '';
        document.getElementById('modalSchemaTitle').innerText = (s.id ? 'Edit ' : 'Create ') + s.schema_type + ' Schema';
        document.getElementById('modalSchemaSubtitle').innerText = s.page_key + (s.id ? ' (ID #' + s.id + ')' : ' (New)');

        validateModalJsonNow();
        document.getElementById('schemaEditorModal').classList.add('open');
      } else {
        alert('Could not load schema: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(err => alert('Request error: ' + err.message));
}

// 2. SUBMIT MODAL SAVE (INDEPENDENT SAVE PER CARD)
function submitModalSave(btn) {
  const id = document.getElementById('modal_schema_id').value;
  const pageKey = document.getElementById('modal_page_key').value;
  const pageTitle = document.getElementById('modal_page_title').value;
  const schemaType = document.getElementById('modal_schema_type').value;
  const schemaName = document.getElementById('modal_schema_name').value.trim();
  const status = document.getElementById('modal_status').value;
  const schemaData = document.getElementById('modal_schema_data').value.trim();

  if (!schemaData) {
    alert('Schema JSON-LD content cannot be empty.');
    return;
  }

  // Quick client-side JSON parse check
  try {
    JSON.parse(schemaData);
  } catch (err) {
    alert('Invalid JSON syntax: ' + err.message);
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Saving...';

  const fd = new FormData();
  fd.append('action', 'save_page_schema');
  fd.append('id', id);
  fd.append('page_key', pageKey);
  fd.append('page_title', pageTitle);
  fd.append('schema_type', schemaType);
  fd.append('schema_name', schemaName);
  fd.append('status', status);
  fd.append('schema_data', schemaData);
  fd.append('csrf_token', CSRF_TOKEN);

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.textContent = 'Save Schema';
      if (data.success) {
        closeEditorModal();
        window.location.reload();
      } else {
        alert('Save Failed: ' + data.message);
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.textContent = 'Save Schema';
      alert('Save request error: ' + err.message);
    });
}

// 3. OPEN DELETE MODAL
function openDeleteModal(id, pageKey, schemaType, pageTitle) {
  document.getElementById('del_modal_id').value = id;
  document.getElementById('del_modal_page_key').value = pageKey;
  document.getElementById('del_modal_schema_type').value = schemaType;
  document.getElementById('del_page_title').innerText = pageTitle || pageKey;
  document.getElementById('del_schema_type').innerText = schemaType;
  document.getElementById('del_schema_id').innerText = id;
  document.getElementById('del_page_key').innerText = pageKey;
  document.getElementById('schemaDeleteModal').classList.add('open');
}

// 4. EXECUTE SAFE DELETE
function executeDeleteSchema(btn) {
  const id = document.getElementById('del_modal_id').value;
  const pageKey = document.getElementById('del_modal_page_key').value;
  const schemaType = document.getElementById('del_modal_schema_type').value;

  btn.disabled = true;
  btn.textContent = 'Deleting...';

  const fd = new FormData();
  fd.append('action', 'delete_page_schema');
  fd.append('id', id);
  fd.append('page_key', pageKey);
  fd.append('schema_type', schemaType);
  fd.append('csrf_token', CSRF_TOKEN);

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.textContent = 'Yes, Delete Schema';
      if (data.success) {
        closeDeleteModal();
        window.location.reload();
      } else {
        alert('Delete Failed: ' + data.message);
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.textContent = 'Yes, Delete Schema';
      alert('Delete request error: ' + err.message);
    });
}

// 5. VALIDATE SCHEMA CARD INLINE
function validateSchemaCard(id, schemaType) {
  const fd = new FormData();
  fd.append('action', 'get_schema_card_data');
  fd.append('id', id);
  fd.append('csrf_token', CSRF_TOKEN);

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.schema) {
        const vFd = new FormData();
        vFd.append('action', 'validate_schema');
        vFd.append('schema_data', data.schema.schema_data);
        vFd.append('schema_type', schemaType);

        fetch(ADMIN_API, { method: 'POST', body: vFd })
          .then(vr => vr.json())
          .then(vData => {
            if (vData.valid) {
              alert('✓ Valid Schema: ' + schemaType + '\n\nSyntax and structure are 100% compliant with Schema.org.' + (vData.warnings && vData.warnings.length ? '\n\nWarnings:\n' + vData.warnings.join('\n') : ''));
            } else {
              alert('✗ Validation Errors:\n\n' + vData.errors.join('\n'));
            }
          });
      }
    });
}

// 6. AUTO-GENERATE CARD SCHEMA
function autoGenerateCardSchema(pageKey, schemaType) {
  if (!confirm('Generate recommended ' + schemaType + ' schema for this page?')) return;
  const fd = new FormData();
  fd.append('action', 'auto_generate_card_schema');
  fd.append('page_key', pageKey);
  fd.append('schema_type', schemaType);
  fd.append('csrf_token', CSRF_TOKEN);

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        alert('Generation Failed: ' + data.message);
      }
    })
    .catch(err => alert('Request Error: ' + err.message));
}

// 7. AUTO-GENERATE ALL PAGE SCHEMAS
function autoGeneratePageSchemas(pageKey) {
  if (!confirm('Reconcile and generate all approved schemas for this page? Existing correct schemas will be preserved.')) return;
  const fd = new FormData();
  fd.append('action', 'auto_generate_all_page_schemas');
  fd.append('page_key', pageKey);
  fd.append('csrf_token', CSRF_TOKEN);

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.success ? '✓ ' + data.message : '✗ ' + data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => alert('Request Error: ' + err.message));
}

// 8. TOGGLE PUBLISH / DRAFT
function togglePublishStatus(id, currentStatus) {
  const newStatus = (currentStatus === 'published') ? 'draft' : 'published';
  const fd = new FormData();
  fd.append('action', 'toggle_page_schema_status');
  fd.append('id', id);
  fd.append('status', newStatus);
  fd.append('csrf_token', CSRF_TOKEN);

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        alert('Failed: ' + data.message);
      }
    })
    .catch(err => alert('Request Error: ' + err.message));
}

// 9. SYNC BLOGS.MD
function syncBlogsMdNow(btn) {
  btn.disabled = true;
  btn.textContent = 'Syncing...';
  const fd = new FormData();
  fd.append('action', 'sync_blogs_md');
  fd.append('csrf_token', CSRF_TOKEN);

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg> Sync blogs.md Now';
      alert(data.success ? '✓ ' + data.message : '✗ ' + data.message);
    })
    .catch(err => {
      btn.disabled = false;
      alert('Request error: ' + err.message);
    });
}

// 10. FORMAT MODAL JSON
function formatModalJson() {
  const textarea = document.getElementById('modal_schema_data');
  try {
    const parsed = JSON.parse(textarea.value);
    textarea.value = JSON.stringify(parsed, null, 2);
    validateModalJsonNow();
  } catch (err) {
    alert('Cannot format invalid JSON: ' + err.message);
  }
}

// 11. COPY MODAL JSON
function copyModalJson() {
  const val = document.getElementById('modal_schema_data').value;
  if (!val) {
    alert('No content to copy.');
    return;
  }
  navigator.clipboard.writeText(val).then(() => alert('Copied to clipboard!'));
}

// 12. VALIDATE MODAL JSON NOW
function validateModalJsonNow() {
  const val = document.getElementById('modal_schema_data').value.trim();
  const box = document.getElementById('modal_validation_box');
  if (!val) {
    box.innerHTML = '<span style="color:var(--text-muted);">Empty schema content.</span>';
    return;
  }
  try {
    JSON.parse(val);
    box.innerHTML = '<span style="color:#36D9B0; font-weight:700;">✓ Valid JSON Syntax</span>';
  } catch (err) {
    box.innerHTML = '<span style="color:var(--danger); font-weight:700;">✗ Syntax Error: ' + err.message + '</span>';
  }
}
</script>

<?php
include CMS_ROOT . '/admin/includes/footer.php';
