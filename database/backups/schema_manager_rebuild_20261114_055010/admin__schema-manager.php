<?php
/**
 * MY UGC VIDEO — Centralized Schema Manager
 * Complete Website-Wide Structured Data Management System
 * Production-conscious management for Static Pages, Service Detail Pages,
 * Global Blog Posting Templates, Individual Blog Overrides, Live Validation,
 * Centralized Organization Identity, and Static Synchronization.
 * Premium Dark Editorial CMS Layout
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}
require_once CMS_ROOT . '/config/config.php';
require_once CMS_ROOT . '/includes/schema-renderer.php';

// Enforce authentication
requireAuth();

// Guarantee active CSRF token is initialized for session & JS API
$csrfToken = generateCsrfToken();

$currentAdmin = getCurrentAdmin();
$activeNav = 'schema_manager';
$pageTitle = 'Schema Manager';

$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard'],
    ['name' => 'Schema Manager', 'url' => ADMIN_URL . '/schema-manager']
];

$db = getDb();

// =========================================================================
// AJAX & POST API Handlers
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if AJAX request
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

    $postCsrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($postCsrfToken) || !verifyCsrfToken($postCsrfToken)) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed or session expired. Please refresh the page.']);
            exit;
        }
        setFlash('error', 'CSRF verification failed. Please try again.');
        header('Location: ' . ADMIN_URL . '/schema-manager');
        exit;
    }

    $action = trim($_POST['action'] ?? '');

    // 1. Validate Schema JSON-LD Endpoint
    if ($action === 'validate_schema') {
        header('Content-Type: application/json; charset=utf-8');
        $schemaData = $_POST['schema_data'] ?? '';
        $result = validateSchemaJsonLd($schemaData);
        echo json_encode([
            'success' => true,
            'valid' => $result['valid'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings']
        ]);
        exit;
    }

    // 2. Get Default Template Endpoint
    if ($action === 'get_type_template') {
        header('Content-Type: application/json; charset=utf-8');
        $type = trim($_POST['type'] ?? '');
        $pageKey = trim($_POST['page_key'] ?? '');
        
        $autoSchema = (!empty($pageKey)) ? buildAutomaticPageSchema($pageKey, $type) : null;
        if ($autoSchema) {
            $template = json_encode($autoSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $template = getDefaultSchemaTemplate($type);
        }

        echo json_encode([
            'success' => true,
            'template' => $template
        ]);
        exit;
    }

    // 3. Save / Update Static Page Schema
    if ($action === 'save_page_schema') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? 0);
        $pageKey = trim($_POST['page_key'] ?? '');
        $pageTitleVal = trim($_POST['page_title'] ?? '');
        $schemaName = trim($_POST['schema_name'] ?? '');
        $schemaType = trim($_POST['schema_type'] ?? '');
        $schemaData = trim($_POST['schema_data'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        $allManageablePages = getAllWebsiteManageablePages();
        if (isset($allManageablePages[$pageKey])) {
            if (empty($pageTitleVal)) {
                $pageTitleVal = $allManageablePages[$pageKey]['title'];
            }
        } elseif (empty($pageTitleVal)) {
            $pageTitleVal = ucwords(str_replace(['-', '_'], ' ', $pageKey));
        }

        if (empty($schemaName)) {
            echo json_encode(['success' => false, 'message' => 'Schema name is required.']);
            exit;
        }

        $val = validateSchemaJsonLd($schemaData);
        if (!$val['valid']) {
            echo json_encode(['success' => false, 'message' => 'Schema JSON validation failed: ' . implode('; ', $val['errors'])]);
            exit;
        }

        try {
            if ($id <= 0) {
                // Prevent duplicate records for the same (page_key, schema_type)
                $chkExisting = $db->prepare("SELECT id FROM `page_schemas` WHERE `page_key` = :pk AND `schema_type` = :st LIMIT 1");
                $chkExisting->execute([':pk' => $pageKey, ':st' => $schemaType]);
                $foundId = $chkExisting->fetchColumn();
                if ($foundId) {
                    $id = (int)$foundId;
                }
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE `page_schemas` 
                    SET `page_key` = :pk, `page_title` = :pt, `schema_name` = :sn, `schema_type` = :st, `schema_data` = :sd, `status` = :stt, `updated_at` = NOW() 
                    WHERE `id` = :id");
                $stmt->execute([
                    ':pk' => $pageKey,
                    ':pt' => $pageTitleVal,
                    ':sn' => $schemaName,
                    ':st' => $schemaType,
                    ':sd' => $schemaData,
                    ':stt' => $status,
                    ':id' => $id
                ]);
            } else {
                $stmt = $db->prepare("INSERT INTO `page_schemas` 
                    (`page_key`, `page_title`, `schema_name`, `schema_type`, `schema_data`, `status`, `created_at`, `updated_at`) 
                    VALUES (:pk, :pt, :sn, :st, :sd, :stt, NOW(), NOW())");
                $stmt->execute([
                    ':pk' => $pageKey,
                    ':pt' => $pageTitleVal,
                    ':sn' => $schemaName,
                    ':st' => $schemaType,
                    ':sd' => $schemaData,
                    ':stt' => $status
                ]);
                $id = (int)$db->lastInsertId();
            }

            // Sync with physical file if applicable
            syncPublishedSchemasToStaticFile($pageKey);

            echo json_encode([
                'success' => true,
                'id' => $id,
                'message' => 'Schema object saved successfully.',
                'status' => $status
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 4. Toggle Static Page Schema Status
    if ($action === 'toggle_page_schema_status') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        try {
            $stmt = $db->prepare("SELECT `page_key`, `schema_data` FROM `page_schemas` WHERE `id` = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Schema record not found.']);
                exit;
            }

            if ($newStatus === 'published') {
                $val = validateSchemaJsonLd($row['schema_data']);
                if (!$val['valid']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot publish invalid schema: ' . implode('; ', $val['errors'])]);
                    exit;
                }
            }

            $updateStmt = $db->prepare("UPDATE `page_schemas` SET `status` = :st, `updated_at` = NOW() WHERE `id` = :id");
            $updateStmt->execute([':st' => $newStatus, ':id' => $id]);

            // Sync with physical file
            syncPublishedSchemasToStaticFile($row['page_key']);

            echo json_encode(['success' => true, 'status' => $newStatus, 'message' => 'Schema status updated to ' . ucfirst($newStatus) . '.']);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 5. Delete Static Page Schema
    if ($action === 'delete_page_schema') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? 0);

        try {
            $stmt = $db->prepare("SELECT `page_key` FROM `page_schemas` WHERE `id` = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $pageKey = $stmt->fetchColumn();

            $delStmt = $db->prepare("DELETE FROM `page_schemas` WHERE `id` = :id");
            $delStmt->execute([':id' => $id]);

            if ($pageKey) {
                syncPublishedSchemasToStaticFile($pageKey);
            }

            echo json_encode(['success' => true, 'message' => 'Schema record deleted successfully.']);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 6. Save Global Blog Schema Settings
    if ($action === 'save_global_blog_settings') {
        header('Content-Type: application/json; charset=utf-8');
        $autoEnabled = (!empty($_POST['auto_blog_schema_enabled']) && $_POST['auto_blog_schema_enabled'] === '1') ? '1' : '0';
        $template = trim($_POST['global_blog_template'] ?? '');
        $status = ($_POST['global_template_status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        $val = validateSchemaJsonLd($template);
        if (!$val['valid']) {
            echo json_encode(['success' => false, 'message' => 'Global Blog template validation failed: ' . implode('; ', $val['errors'])]);
            exit;
        }

        setSchemaSetting('auto_blog_schema_enabled', $autoEnabled);
        setSchemaSetting('global_blog_template', $template);
        setSchemaSetting('global_template_status', $status);

        echo json_encode(['success' => true, 'message' => 'Global Blog Schema settings saved successfully.']);
        exit;
    }

    // 7. Search Blogs for Individual Overrides
    if ($action === 'search_blogs') {
        header('Content-Type: application/json; charset=utf-8');
        $query = trim($_POST['q'] ?? '');
        $statusFilter = trim($_POST['status'] ?? 'all');
        $modeFilter = trim($_POST['mode'] ?? 'all');

        try {
            $sql = "SELECT p.id, p.title, p.slug, p.status, c.name AS category_name,
                           o.override_enabled, o.schema_disabled, o.status AS override_status, o.updated_at AS override_updated_at
                    FROM blog_posts p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN blog_schema_overrides o ON p.id = o.post_id
                    WHERE 1=1";
            $params = [];

            if ($query !== '') {
                $sql .= " AND (p.title LIKE :q OR p.slug LIKE :q OR c.name LIKE :q)";
                $params[':q'] = "%{$query}%";
            }

            if ($statusFilter === 'published' || $statusFilter === 'draft') {
                $sql .= " AND p.status = :st";
                $params[':st'] = $statusFilter;
            }

            if ($modeFilter === 'global') {
                $sql .= " AND (o.override_enabled IS NULL OR o.override_enabled = 0) AND (o.schema_disabled IS NULL OR o.schema_disabled = 0)";
            } elseif ($modeFilter === 'custom') {
                $sql .= " AND o.override_enabled = 1 AND (o.schema_disabled IS NULL OR o.schema_disabled = 0)";
            } elseif ($modeFilter === 'disabled') {
                $sql .= " AND o.schema_disabled = 1";
            }

            $sql .= " ORDER BY p.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'blogs' => $posts]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error querying blogs: ' . $e->getMessage()]);
            exit;
        }
    }

    // 8. Get Blog Schema Data
    if ($action === 'get_blog_schema_data') {
        header('Content-Type: application/json; charset=utf-8');
        $postId = (int)($_POST['post_id'] ?? 0);
        try {
            $stmt = $db->prepare("SELECT p.*, c.name AS category_name 
                                  FROM blog_posts p 
                                  LEFT JOIN categories c ON p.category_id = c.id 
                                  WHERE p.id = :id LIMIT 1");
            $stmt->execute([':id' => $postId]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                echo json_encode(['success' => false, 'message' => 'Blog article not found.']);
                exit;
            }

            $oStmt = $db->prepare("SELECT * FROM `blog_schema_overrides` WHERE `post_id` = :id LIMIT 1");
            $oStmt->execute([':id' => $postId]);
            $override = $oStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'post_id' => $postId,
                'override_enabled' => 0,
                'schema_disabled' => 0,
                'custom_schema' => '',
                'status' => 'published'
            ];

            $previewPost = $post;
            $previewPost['status'] = 'published';
            $compiledDefault = resolveBlogPostingSchema($previewPost);

            echo json_encode([
                'success' => true,
                'post' => [
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'slug' => $post['slug'],
                    'status' => $post['status'],
                    'category_name' => $post['category_name'] ?? 'Uncategorized',
                    'published_at' => $post['published_at']
                ],
                'override' => $override,
                'compiled_default' => $compiledDefault ? json_encode($compiledDefault, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ''
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching blog data: ' . $e->getMessage()]);
            exit;
        }
    }

    // 9. Save Individual Blog Override
    if ($action === 'save_blog_override') {
        header('Content-Type: application/json; charset=utf-8');
        $postId = (int)($_POST['post_id'] ?? 0);
        $mode = trim($_POST['mode'] ?? 'inherit');
        $customSchema = trim($_POST['custom_schema'] ?? '');
        $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

        if ($postId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid blog ID.']);
            exit;
        }

        $overrideEnabled = 0;
        $schemaDisabled = 0;

        if ($mode === 'disabled') {
            $schemaDisabled = 1;
            $overrideEnabled = 0;
        } elseif ($mode === 'custom') {
            $overrideEnabled = 1;
            $schemaDisabled = 0;
            if ($status === 'published') {
                $val = validateSchemaJsonLd($customSchema);
                if (!$val['valid']) {
                    echo json_encode(['success' => false, 'message' => 'Custom schema validation failed: ' . implode('; ', $val['errors'])]);
                    exit;
                }
            } else {
                if (!empty($customSchema)) {
                    json_decode($customSchema);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo json_encode(['success' => false, 'message' => 'JSON Syntax Error: ' . json_last_error_msg()]);
                        exit;
                    }
                }
            }
        } elseif ($mode === 'inherit') {
            $overrideEnabled = 0;
            $schemaDisabled = 0;
        }

        try {
            $checkStmt = $db->prepare("SELECT `custom_schema` FROM `blog_schema_overrides` WHERE `post_id` = :id LIMIT 1");
            $checkStmt->execute([':id' => $postId]);
            $existingCustom = $checkStmt->fetchColumn();

            $schemaToSave = ($mode === 'custom') ? $customSchema : (!empty($customSchema) ? $customSchema : $existingCustom);

            $stmt = $db->prepare("INSERT INTO `blog_schema_overrides` 
                (`post_id`, `override_enabled`, `schema_disabled`, `custom_schema`, `status`, `updated_at`) 
                VALUES (:pid, :oe, :sd, :cs, :stt, NOW()) 
                ON DUPLICATE KEY UPDATE 
                `override_enabled` = :oe2, `schema_disabled` = :sd2, `custom_schema` = :cs2, `status` = :stt2, `updated_at` = NOW()");
            $stmt->execute([
                ':pid' => $postId,
                ':oe' => $overrideEnabled,
                ':sd' => $schemaDisabled,
                ':cs' => $schemaToSave,
                ':stt' => $status,
                ':oe2' => $overrideEnabled,
                ':sd2' => $schemaDisabled,
                ':cs2' => $schemaToSave,
                ':stt2' => $status
            ]);

            $modeLabels = [
                'inherit' => 'Global Template',
                'custom' => 'Custom Override (' . ucfirst($status) . ')',
                'disabled' => 'Schema Disabled'
            ];

            echo json_encode([
                'success' => true,
                'message' => 'Blog schema configuration saved successfully. Mode set to: ' . ($modeLabels[$mode] ?? $mode),
                'mode' => $mode,
                'status' => $status
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 10. Restore Blog to Default Global Inheritance
    if ($action === 'restore_blog_default') {
        header('Content-Type: application/json; charset=utf-8');
        $postId = (int)($_POST['post_id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM `blog_schema_overrides` WHERE `post_id` = :id");
            $stmt->execute([':id' => $postId]);
            echo json_encode(['success' => true, 'message' => 'Restored blog to global schema inheritance.']);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 11. Run Full Website Audit
    if ($action === 'run_website_audit') {
        header('Content-Type: application/json; charset=utf-8');
        $audit = runWebsiteWideSchemaAudit();
        echo json_encode([
            'success' => true,
            'audit' => $audit
        ]);
        exit;
    }

    // 12. Auto Create & Publish All Missing Schemas
    if ($action === 'auto_create_missing') {
        header('Content-Type: application/json; charset=utf-8');
        $res = autoCreateAndPublishAllMissingSchemas();
        echo json_encode([
            'success' => $res['success'],
            'message' => "Auto-creation complete: {$res['created']} schemas created, {$res['updated']} updated.",
            'details' => $res
        ]);
        exit;
    }

    // 13. Auto Generate Single Schema
    if ($action === 'auto_generate_single_schema') {
        header('Content-Type: application/json; charset=utf-8');
        $pageKey = trim($_POST['page_key'] ?? '');
        $schemaType = trim($_POST['schema_type'] ?? '');

        $pages = getAllWebsiteManageablePages();
        if (!isset($pages[$pageKey])) {
            echo json_encode(['success' => false, 'message' => 'Invalid page key specified.']);
            exit;
        }

        $schemaData = buildAutomaticPageSchema($pageKey, $schemaType);
        if (!$schemaData) {
            echo json_encode(['success' => false, 'message' => "Cannot auto-generate {$schemaType} for this page."]);
            exit;
        }

        $jsonStr = json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $val = validateSchemaJsonLd($jsonStr);
        if (!$val['valid']) {
            echo json_encode(['success' => false, 'message' => 'Generated schema validation failed: ' . implode('; ', $val['errors'])]);
            exit;
        }

        $pageMeta = $pages[$pageKey];
        $schemaName = $pageMeta['title'] . ' ' . $schemaType . ' Schema';
        if ($schemaType === 'Organization' && $pageKey !== 'homepage') {
            $schemaName = 'Referenced Organization';
        }

        try {
            $stmt = $db->prepare("SELECT id FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
            $stmt->execute([':pk' => $pageKey, ':st' => $schemaType]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                $u = $db->prepare("UPDATE page_schemas SET page_title = :pt, schema_name = :sn, schema_data = :sd, status = 'published', updated_at = NOW() WHERE id = :id");
                $u->execute([':pt' => $pageMeta['title'], ':sn' => $schemaName, ':sd' => $jsonStr, ':id' => $existingId]);
                $savedId = (int)$existingId;
            } else {
                $i = $db->prepare("INSERT INTO page_schemas (page_key, page_title, schema_name, schema_type, schema_data, status, created_at, updated_at) 
                                   VALUES (:pk, :pt, :sn, :st, :sd, 'published', NOW(), NOW())");
                $i->execute([':pk' => $pageKey, ':pt' => $pageMeta['title'], ':sn' => $schemaName, ':st' => $schemaType, ':sd' => $jsonStr]);
                $savedId = (int)$db->lastInsertId();
            }

            if (function_exists('syncPublishedSchemasToStaticFile')) {
                syncPublishedSchemasToStaticFile($pageKey);
            }

            echo json_encode([
                'success' => true,
                'id' => $savedId,
                'message' => "{$schemaType} generated and published successfully for {$pageMeta['title']}."
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 14a. Get or Initialize Blog / Blog-Listing Schema for Editor Modal
    if ($action === 'get_or_init_blog_schema') {
        header('Content-Type: application/json; charset=utf-8');
        $pageKey = trim($_POST['page_key'] ?? '');
        $schemaType = trim($_POST['schema_type'] ?? '');

        if (empty($pageKey) || empty($schemaType)) {
            echo json_encode(['success' => false, 'message' => 'Missing page key or schema type.']);
            exit;
        }

        try {
            // Check if already in page_schemas
            $stmt = $db->prepare("SELECT * FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
            $stmt->execute([':pk' => $pageKey, ':st' => $schemaType]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                echo json_encode(['success' => true, 'schema' => $existing]);
                exit;
            }

            // Build template if not exists yet
            $baseUrl = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
            $pageTitle = ucwords(str_replace(['-', '_'], ' ', $pageKey));
            $schemaData = null;

            if ($pageKey === 'blog-listing') {
                $pageTitle = 'Blog Listing';
                if ($schemaType === 'BreadcrumbList') {
                    $schemaData = [
                        '@context' => 'https://schema.org',
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $baseUrl . '/'],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blogs', 'item' => $baseUrl . '/blogs']
                        ]
                    ];
                } elseif ($schemaType === 'WebPage') {
                    $schemaData = [
                        '@context' => 'https://schema.org',
                        '@type' => 'WebPage',
                        'name' => 'UGC Video Blog & Marketing Insights | MY UGC VIDEO',
                        'url' => $baseUrl . '/blogs',
                        'description' => 'Explore the latest insights, performance marketing strategies, and best practices on user-generated content from MY UGC VIDEO.'
                    ];
                }
            } elseif (str_starts_with($pageKey, 'blog-')) {
                $slug = substr($pageKey, 5);
                $bStmt = $db->prepare("SELECT p.*, c.name AS category_name FROM blog_posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = :slug LIMIT 1");
                $bStmt->execute([':slug' => $slug]);
                $bp = $bStmt->fetch(PDO::FETCH_ASSOC);

                if ($bp) {
                    $pageTitle = htmlspecialchars_decode($bp['title'], ENT_QUOTES);
                    $blogUrl = $baseUrl . '/blog/' . rawurlencode($slug);
                    $pubDate = $bp['published_at'] ?? date('Y-m-d');
                    $modDate = $bp['updated_at'] ?? $pubDate;
                    $desc = !empty($bp['meta_description']) ? $bp['meta_description'] : ($bp['excerpt'] ?? '');

                    if ($schemaType === 'Organization') {
                        $schemaData = [
                            '@context' => 'https://schema.org',
                            '@type' => 'Organization',
                            'name' => 'MY UGC VIDEO',
                            'url' => $baseUrl,
                            'logo' => [
                                '@type' => 'ImageObject',
                                'url' => $baseUrl . '/images/my-ugc-video-logo.png',
                                'width' => 200,
                                'height' => 60
                            ],
                            'sameAs' => [
                                'https://www.instagram.com/myugcvideo',
                                'https://www.linkedin.com/company/myugcvideo'
                            ]
                        ];
                    } elseif ($schemaType === 'BreadcrumbList') {
                        $schemaData = [
                            '@context' => 'https://schema.org',
                            '@type' => 'BreadcrumbList',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $baseUrl . '/'],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blogs', 'item' => $baseUrl . '/blogs'],
                                ['@type' => 'ListItem', 'position' => 3, 'name' => $pageTitle, 'item' => $blogUrl]
                            ]
                        ];
                    } elseif ($schemaType === 'BlogPosting') {
                        $schemaData = [
                            '@context' => 'https://schema.org',
                            '@type' => 'BlogPosting',
                            'headline' => $pageTitle,
                            'url' => $blogUrl,
                            'description' => $desc,
                            'datePublished' => date('c', strtotime($pubDate)),
                            'dateModified' => date('c', strtotime($modDate)),
                            'author' => ['@type' => 'Organization', 'name' => 'MY UGC VIDEO', 'url' => $baseUrl],
                            'publisher' => ['@type' => 'Organization', 'name' => 'MY UGC VIDEO', 'url' => $baseUrl, 'logo' => ['@type' => 'ImageObject', 'url' => $baseUrl . '/images/my-ugc-video-logo.png']],
                            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $blogUrl]
                        ];
                    }
                }
            }

            if (!$schemaData) {
                $schemaData = ['@context' => 'https://schema.org', '@type' => $schemaType, 'name' => $pageTitle];
            }

            $jsonStr = json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $schemaName = $pageTitle . ' ' . $schemaType . ' Schema';

            // Insert initial record into page_schemas so it has an assigned ID
            $ins = $db->prepare("INSERT INTO page_schemas (page_key, page_title, schema_name, schema_type, schema_data, status, created_at, updated_at) VALUES (:pk, :pt, :sn, :st, :sd, 'published', NOW(), NOW())");
            $ins->execute([
                ':pk' => $pageKey,
                ':pt' => $pageTitle,
                ':sn' => $schemaName,
                ':st' => $schemaType,
                ':sd' => $jsonStr
            ]);
            $newId = (int)$db->lastInsertId();

            $stmt2 = $db->prepare("SELECT * FROM page_schemas WHERE id = :id LIMIT 1");
            $stmt2->execute([':id' => $newId]);
            $createdRow = $stmt2->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'schema' => $createdRow]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 14b. Auto-generate Organization + BreadcrumbList + BlogPosting for a single blog page_key
    if ($action === 'auto_generate_blog_schemas') {
        header('Content-Type: application/json; charset=utf-8');
        $pageKey = trim($_POST['page_key'] ?? '');
        if (!preg_match('/^blog-[a-z0-9\-]+$/', $pageKey)) {
            echo json_encode(['success' => false, 'message' => 'Invalid blog page key.']);
            exit;
        }
        // Extract slug from page_key
        $slug = substr($pageKey, 5); // remove 'blog-'
        // Fetch blog post from DB
        $bStmt = $db->prepare("SELECT p.*, c.name AS category_name FROM blog_posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = :slug AND p.status = 'published' LIMIT 1");
        $bStmt->execute([':slug' => $slug]);
        $blogPost = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$blogPost) {
            echo json_encode(['success' => false, 'message' => "Published blog not found for slug: {$slug}"]);
            exit;
        }

        $baseUrl   = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
        $blogTitle = htmlspecialchars_decode($blogPost['title'], ENT_QUOTES);
        $blogUrl   = $baseUrl . '/blog/' . rawurlencode($slug);
        $published = $blogPost['published_at'] ?? date('Y-m-d');
        $modified  = $blogPost['updated_at']   ?? $published;
        $desc      = !empty($blogPost['meta_description']) ? $blogPost['meta_description'] : ($blogPost['excerpt'] ?? '');

        $typesToGenerate = [
            'Organization' => [
                '@context' => 'https://schema.org',
                '@type'    => 'Organization',
                'name'     => 'MY UGC VIDEO',
                'url'      => $baseUrl,
                'logo'     => [
                    '@type'  => 'ImageObject',
                    'url'    => $baseUrl . '/images/my-ugc-video-logo.png',
                    'width'  => 200,
                    'height' => 60
                ],
                'sameAs'   => [
                    'https://www.instagram.com/myugcvideo',
                    'https://www.linkedin.com/company/myugcvideo'
                ]
            ],
            'BreadcrumbList' => [
                '@context'        => 'https://schema.org',
                '@type'           => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',  'item' => $baseUrl . '/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blogs', 'item' => $baseUrl . '/blogs'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $blogTitle, 'item' => $blogUrl]
                ]
            ],
            'BlogPosting' => [
                '@context'      => 'https://schema.org',
                '@type'         => 'BlogPosting',
                'headline'      => $blogTitle,
                'url'           => $blogUrl,
                'description'   => $desc,
                'datePublished' => date('c', strtotime($published)),
                'dateModified'  => date('c', strtotime($modified)),
                'author'        => ['@type' => 'Organization', 'name' => 'MY UGC VIDEO', 'url' => $baseUrl],
                'publisher'     => ['@type' => 'Organization', 'name' => 'MY UGC VIDEO', 'url' => $baseUrl,
                                    'logo'  => ['@type' => 'ImageObject', 'url' => $baseUrl . '/images/my-ugc-video-logo.png']],
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $blogUrl]
            ]
        ];

        $saved   = [];
        $errors  = [];
        $pageTitle = $blogTitle;
        foreach ($typesToGenerate as $sType => $schemaData) {
            $jsonStr = json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $schemaName = $pageTitle . ' ' . $sType . ' Schema';
            try {
                $chk = $db->prepare("SELECT id FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
                $chk->execute([':pk' => $pageKey, ':st' => $sType]);
                $existingId = $chk->fetchColumn();
                if ($existingId) {
                    $db->prepare("UPDATE page_schemas SET page_title = :pt, schema_name = :sn, schema_data = :sd, status = 'published', updated_at = NOW() WHERE id = :id")
                       ->execute([':pt' => $pageTitle, ':sn' => $schemaName, ':sd' => $jsonStr, ':id' => $existingId]);
                    $saved[] = $sType . ' (updated)';
                } else {
                    $db->prepare("INSERT INTO page_schemas (page_key, page_title, schema_name, schema_type, schema_data, status, created_at, updated_at) VALUES (:pk, :pt, :sn, :st, :sd, 'published', NOW(), NOW())")
                       ->execute([':pk' => $pageKey, ':pt' => $pageTitle, ':sn' => $schemaName, ':st' => $sType, ':sd' => $jsonStr]);
                    $saved[] = $sType . ' (created)';
                }
            } catch (Throwable $e) {
                $errors[] = $sType . ': ' . $e->getMessage();
            }
        }
        if (function_exists('syncPublishedSchemasToStaticFile')) {
            syncPublishedSchemasToStaticFile($pageKey);
        }
        if (empty($errors)) {
            echo json_encode(['success' => true, 'message' => 'Blog schemas generated: ' . implode(', ', $saved)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Partial errors: ' . implode('; ', $errors), 'saved' => $saved]);
        }
        exit;
    }

    // 14c. Auto-generate blog schemas for ALL published blogs
    if ($action === 'auto_generate_all_blog_schemas') {
        header('Content-Type: application/json; charset=utf-8');
        $baseUrl = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
        $allBlogs = $db->query("SELECT p.id, p.title, p.slug, p.published_at, p.updated_at, p.meta_description, p.excerpt FROM blog_posts p WHERE p.status = 'published' AND p.published_at <= NOW() ORDER BY p.id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $totalSaved = 0;
        $allErrors  = [];
        foreach ($allBlogs as $bp) {
            $slug      = $bp['slug'];
            $pageKey   = 'blog-' . $slug;
            $blogTitle = htmlspecialchars_decode($bp['title'], ENT_QUOTES);
            $blogUrl   = $baseUrl . '/blog/' . rawurlencode($slug);
            $published = $bp['published_at'] ?? date('Y-m-d');
            $modified  = $bp['updated_at']   ?? $published;
            $desc      = !empty($bp['meta_description']) ? $bp['meta_description'] : ($bp['excerpt'] ?? '');
            $typesToGen = [
                'Organization' => ['@context'=>'https://schema.org','@type'=>'Organization','name'=>'MY UGC VIDEO','url'=>$baseUrl,'logo'=>['@type'=>'ImageObject','url'=>$baseUrl.'/images/my-ugc-video-logo.png','width'=>200,'height'=>60],'sameAs'=>['https://www.instagram.com/myugcvideo','https://www.linkedin.com/company/myugcvideo']],
                'BreadcrumbList' => ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$baseUrl.'/'],['@type'=>'ListItem','position'=>2,'name'=>'Blogs','item'=>$baseUrl.'/blogs'],['@type'=>'ListItem','position'=>3,'name'=>$blogTitle,'item'=>$blogUrl]]],
                'BlogPosting' => ['@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$blogTitle,'url'=>$blogUrl,'description'=>$desc,'datePublished'=>date('c',strtotime($published)),'dateModified'=>date('c',strtotime($modified)),'author'=>['@type'=>'Organization','name'=>'MY UGC VIDEO','url'=>$baseUrl],'publisher'=>['@type'=>'Organization','name'=>'MY UGC VIDEO','url'=>$baseUrl,'logo'=>['@type'=>'ImageObject','url'=>$baseUrl.'/images/my-ugc-video-logo.png']],'mainEntityOfPage'=>['@type'=>'WebPage','@id'=>$blogUrl]]
            ];
            foreach ($typesToGen as $sType => $sd) {
                $js = json_encode($sd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $sn = $blogTitle . ' ' . $sType . ' Schema';
                try {
                    $chk = $db->prepare("SELECT id FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
                    $chk->execute([':pk'=>$pageKey,':st'=>$sType]);
                    $eid = $chk->fetchColumn();
                    if ($eid) {
                        $db->prepare("UPDATE page_schemas SET page_title=:pt,schema_name=:sn,schema_data=:sd,status='published',updated_at=NOW() WHERE id=:id")
                           ->execute([':pt'=>$blogTitle,':sn'=>$sn,':sd'=>$js,':id'=>$eid]);
                    } else {
                        $db->prepare("INSERT INTO page_schemas (page_key,page_title,schema_name,schema_type,schema_data,status,created_at,updated_at) VALUES(:pk,:pt,:sn,:st,:sd,'published',NOW(),NOW())")
                           ->execute([':pk'=>$pageKey,':pt'=>$blogTitle,':sn'=>$sn,':st'=>$sType,':sd'=>$js]);
                    }
                    $totalSaved++;
                } catch (Throwable $e) {
                    $allErrors[] = "{$pageKey}/{$sType}: " . $e->getMessage();
                }
            }
            if (function_exists('syncPublishedSchemasToStaticFile')) {
                syncPublishedSchemasToStaticFile($pageKey);
            }
        }
        $msg = "Generated {$totalSaved} schema records across " . count($allBlogs) . " blogs.";
        if (!empty($allErrors)) $msg .= ' Errors: ' . implode('; ', $allErrors);
        echo json_encode(['success' => empty($allErrors), 'message' => $msg]);
        exit;
    }

    // 14d. Sync blogs.md now
    if ($action === 'sync_blogs_md') {
        header('Content-Type: application/json; charset=utf-8');
        if (!function_exists('syncBlogsMd')) {
            echo json_encode(['success' => false, 'message' => 'syncBlogsMd() not available.']);
            exit;
        }
        $result = syncBlogsMd();
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success']
                ? "blogs.md synchronized successfully ({$result['bytes']} bytes)."
                : "Failed to sync blogs.md: " . $result['error']
        ]);
        exit;
    }

    // 14. Get Schema Preview JSON
    if ($action === 'get_schema_preview') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("SELECT * FROM page_schemas WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Schema record not found.']);
                exit;
            }
            echo json_encode([
                'success' => true,
                'schema' => $row
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
    exit;
}

// =========================================================================
// Data Fetching for View (Audit Driven)
// =========================================================================

// Execute full audit for live real-time statistics
$auditResult = runWebsiteWideSchemaAudit();
$metrics = $auditResult['metrics'];
$auditPages = $auditResult['pages'];

$managedPages = getManagedStaticPages();

// Global Blog Settings
$autoBlogSchemaEnabled = (string)getSchemaSetting('auto_blog_schema_enabled', '1');
$globalBlogTemplate = getSchemaSetting('global_blog_template', '');
$globalTemplateStatus = getSchemaSetting('global_template_status', 'published');
$fieldMappings = getSchemaSetting('field_mappings', '');

// Fetch all blog posts for individual blog overrides list
$initialBlogs = [];
try {
    $bStmt = $db->query("SELECT p.id, p.title, p.slug, p.status, c.name AS category_name,
                                o.override_enabled, o.schema_disabled, o.status AS override_status, o.updated_at AS override_updated_at
                         FROM blog_posts p
                         LEFT JOIN categories c ON p.category_id = c.id
                         LEFT JOIN blog_schema_overrides o ON p.id = o.post_id
                         ORDER BY p.id DESC");
    $initialBlogs = $bStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Fetch published blogs for dedicated Blogs Schema tab (Tab 4)
$publishedBlogsForTab = [];
try {
    $pbStmt = $db->query(
        "SELECT p.id, p.title, p.slug, p.published_at, p.updated_at,
                c.name AS category_name
         FROM blog_posts p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.status = 'published' AND p.published_at <= NOW()
         ORDER BY p.published_at DESC, p.id DESC"
    );
    $publishedBlogsForTab = $pbStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// For each published blog, fetch its existing schemas from page_schemas
$blogPageSchemas = [];
try {
    if (!empty($publishedBlogsForTab)) {
        $slugsIn = array_map(fn($b) => "'blog-" . $db->quote($b['slug']) . "'", $publishedBlogsForTab);
        // Use individual queries per blog to avoid complex IN clause quoting
        foreach ($publishedBlogsForTab as $pb) {
            $pageKey = 'blog-' . $pb['slug'];
            $psStmt  = $db->prepare(
                "SELECT id, schema_type, status FROM page_schemas WHERE page_key = :pk AND schema_type IN ('Organization','BreadcrumbList','BlogPosting')"
            );
            $psStmt->execute([':pk' => $pageKey]);
            $existing = $psStmt->fetchAll(PDO::FETCH_ASSOC);
            $blogPageSchemas[$pb['slug']] = [];
            foreach ($existing as $row) {
                $blogPageSchemas[$pb['slug']][$row['schema_type']] = $row;
            }
        }
    }
} catch (Throwable $e) {}

// Fetch blog-listing schemas for Tab 4
$blogListingSchemas = [];
try {
    $blStmt = $db->query("SELECT id, schema_type, status FROM page_schemas WHERE page_key = 'blog-listing' AND schema_type IN ('BreadcrumbList', 'WebPage')");
    foreach ($blStmt->fetchAll(PDO::FETCH_ASSOC) as $blRow) {
        $blogListingSchemas[$blRow['schema_type']] = $blRow;
    }
} catch (Throwable $e) {}

$supportedTypes = getSupportedSchemaTypes();

// Include Admin CMS Header
require_once CMS_ROOT . '/admin/includes/header.php';
?>

<!-- Schema Manager Main Styles -->
<style>
.sm-hero {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1.5rem;
}
.sm-hero-text h1 {
  font-size: 2rem;
  color: #fff;
  margin-bottom: 0.5rem;
  font-family: var(--font-heading);
}
.sm-hero-text p {
  color: var(--text-muted);
  max-width: 680px;
  font-size: 0.95rem;
  margin: 0;
}
.sm-hero-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

/* SECTION A: Real-Time Audit Metric Stats Grid */
.sm-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}
.sm-stat-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-md);
  padding: 1.15rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: var(--transition);
}
.sm-stat-card:hover {
  border-color: var(--accent-mint);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}
.sm-stat-icon {
  width: 42px;
  height: 42px;
  border-radius: var(--radius-sm);
  background: rgba(54, 217, 176, 0.1);
  color: var(--accent-mint);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.sm-stat-icon.warn {
  background: rgba(229, 185, 90, 0.12);
  color: var(--status-draft);
}
.sm-stat-icon.danger {
  background: rgba(255, 107, 107, 0.12);
  color: var(--danger);
}
.sm-stat-icon svg {
  width: 20px;
  height: 20px;
}
.sm-stat-val {
  font-size: 1.45rem;
  font-weight: 800;
  color: #fff;
  line-height: 1.2;
}
.sm-stat-lbl {
  font-size: 0.75rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
}

/* Nav Tabs */
.sm-tabs-bar {
  display: flex;
  gap: 0.5rem;
  border-bottom: 1px solid var(--border-sage);
  margin-bottom: 2rem;
  overflow-x: auto;
  padding-bottom: 2px;
}
.sm-tab-btn {
  background: transparent;
  border: none;
  color: var(--text-muted);
  font-family: var(--font-heading);
  font-size: 0.95rem;
  font-weight: 700;
  padding: 0.75rem 1.25rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  border-bottom: 2px solid transparent;
  transition: var(--transition);
  white-space: nowrap;
}
.sm-tab-btn:hover {
  color: var(--text-primary);
}
.sm-tab-btn.active {
  color: var(--accent-mint);
  border-bottom-color: var(--accent-mint);
}
.sm-tab-pill {
  font-size: 0.75rem;
  padding: 0.15rem 0.55rem;
  background: var(--bg-surface-elevated);
  border-radius: 9999px;
  color: var(--text-primary);
  border: 1px solid var(--border-sage);
}

/* SECTION B: Page Card Accordion */
.sm-panel {
  display: none;
}
.sm-panel.active {
  display: block;
}
.sm-pages-list {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.sm-page-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-md);
  overflow: hidden;
  transition: var(--transition);
}
.sm-page-card:hover {
  border-color: rgba(54, 217, 176, 0.4);
}
.sm-page-card-header {
  padding: 1.25rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  background: var(--bg-surface);
  border-bottom: 1px solid transparent;
  transition: var(--transition);
  flex-wrap: wrap;
  gap: 1rem;
}
.sm-page-card-header:hover {
  background: var(--bg-surface-elevated);
}
.sm-page-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.sm-page-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.25rem 0.65rem;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.sm-page-badge.core {
  background: rgba(0, 191, 165, 0.15);
  color: var(--accent-teal);
  border: 1px solid rgba(0, 191, 165, 0.3);
}
.sm-page-badge.service {
  background: rgba(54, 217, 176, 0.15);
  color: var(--accent-mint);
  border: 1px solid rgba(54, 217, 176, 0.3);
}
.sm-page-title {
  font-weight: 700;
  color: #fff;
  font-size: 1.05rem;
  margin-bottom: 0.2rem;
}
.sm-page-url {
  font-size: 0.82rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
}
.sm-page-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

/* SECTION C: Individual Schema Cards Grid */
.sm-page-body {
  padding: 1.5rem;
  background: rgba(0, 15, 12, 0.4);
  border-top: 1px solid var(--border-sage-subtle);
}
.sm-schema-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.25rem;
}
.sm-card-item {
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-sm);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 1rem;
  transition: var(--transition);
}
.sm-card-item:hover {
  border-color: rgba(54, 217, 176, 0.5);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}
.sm-card-item.missing {
  border-style: dashed;
  background: rgba(229, 185, 90, 0.03);
  border-color: rgba(229, 185, 90, 0.35);
}
.sm-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
}
.sm-card-type-title {
  font-family: var(--font-mono);
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--accent-mint);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.sm-card-name {
  font-size: 0.88rem;
  color: #fff;
  font-weight: 600;
  margin-top: 0.25rem;
}
.sm-card-badges {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-top: 0.5rem;
}
.sm-status-pill {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.18rem 0.55rem;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.sm-status-pill.published {
  background: var(--status-published-bg);
  color: var(--status-published);
}
.sm-status-pill.draft {
  background: var(--status-draft-bg);
  color: var(--status-draft);
}
.sm-status-pill.missing {
  background: rgba(229, 185, 90, 0.15);
  color: #f7d283;
  border: 1px solid rgba(229, 185, 90, 0.35);
}
.sm-status-pill.valid {
  background: rgba(54, 217, 176, 0.12);
  color: var(--accent-mint);
}
.sm-card-date {
  font-size: 0.75rem;
  color: var(--text-muted);
}
.sm-card-actions {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  border-top: 1px solid var(--border-sage-subtle);
  padding-top: 0.75rem;
}

/* Table for Tab 3 Blog Overrides */
.sm-blog-table {
  width: 100%;
  border-collapse: collapse;
}
.sm-blog-table th {
  background: var(--bg-surface-elevated);
  padding: 1rem 1.25rem;
  text-align: left;
  color: var(--text-muted);
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 700;
  border-bottom: 1px solid var(--border-sage);
}
.sm-blog-table td {
  padding: 1.1rem 1.25rem;
  border-bottom: 1px solid var(--border-sage-subtle);
  color: var(--text-primary);
  font-size: 0.9rem;
  vertical-align: middle;
}
.sm-blog-table tr:hover td {
  background: rgba(54, 217, 176, 0.03);
}
.sm-blog-row-title {
  font-weight: 700;
  color: #fff;
  font-size: 0.98rem;
  line-height: 1.35;
  margin-bottom: 0.2rem;
}
.sm-blog-row-slug {
  font-size: 0.78rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
}

/* Modals */
.sm-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 10, 8, 0.85);
  backdrop-filter: blur(5px);
  z-index: 9999;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}
.sm-modal-backdrop.open {
  display: flex;
}
.sm-modal-dialog {
  background: var(--bg-surface);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 1100px;
  max-height: 92vh;
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
  background: var(--bg-surface-elevated);
}
.sm-modal-header h3 {
  font-size: 1.25rem;
  color: #fff;
  margin: 0;
}
.sm-modal-body {
  padding: 1.5rem 1.75rem;
  overflow-y: auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  flex: 1;
}
@media (max-width: 900px) {
  .sm-modal-body {
    grid-template-columns: 1fr;
  }
}
.sm-modal-footer {
  padding: 1.25rem 1.75rem;
  border-top: 1px solid var(--border-sage);
  background: var(--bg-surface-elevated);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}
.sm-editor-pane {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.sm-editor-textarea {
  width: 100%;
  min-height: 380px;
  background: var(--bg-input);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-sm);
  color: var(--accent-mint);
  font-family: var(--font-mono);
  font-size: 0.88rem;
  line-height: 1.5;
  padding: 1rem;
  resize: vertical;
  tab-size: 2;
  box-sizing: border-box;
}
.sm-editor-textarea:focus {
  outline: none;
  border-color: var(--accent-mint);
  box-shadow: 0 0 0 2px rgba(54, 217, 176, 0.2);
}
.sm-preview-pane {
  display: flex;
  flex-direction: column;
  background: var(--bg-input);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-sm);
  overflow: hidden;
}
.sm-preview-header {
  padding: 0.75rem 1rem;
  background: var(--bg-surface-elevated);
  border-bottom: 1px solid var(--border-sage);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  font-weight: 700;
  color: #fff;
}
.sm-preview-content {
  padding: 1rem;
  overflow: auto;
  max-height: 330px;
  font-family: var(--font-mono);
  font-size: 0.84rem;
  line-height: 1.5;
  color: var(--text-primary);
  white-space: pre-wrap;
  word-break: break-all;
  flex: 1;
}
.sm-validation-box {
  padding: 0.75rem 1rem;
  border-top: 1px solid var(--border-sage);
  background: rgba(0, 10, 8, 0.6);
  font-size: 0.82rem;
}
.sm-val-valid {
  color: var(--accent-mint);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.sm-val-error {
  color: var(--danger);
  margin-top: 0.25rem;
}
.sm-val-warning {
  color: var(--status-draft);
  margin-top: 0.25rem;
}
.sm-tool-bar {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.sm-tool-btn {
  background: var(--bg-surface-elevated);
  border: 1px solid var(--border-sage);
  color: var(--text-primary);
  border-radius: 4px;
  padding: 0.3rem 0.65rem;
  font-size: 0.78rem;
  cursor: pointer;
  font-family: var(--font-body);
}
.sm-tool-btn:hover {
  border-color: var(--accent-mint);
  color: var(--accent-mint);
}
.acc-chevron {
  transition: transform 0.25s ease;
}

/* Interactive Mode Cards for Overrides */
.sm-mode-cards-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}
@media (max-width: 900px) {
  .sm-mode-cards-grid {
    grid-template-columns: 1fr;
  }
}
.sm-mode-card {
  background: var(--bg-input);
  border: 1px solid var(--border-sage);
  border-radius: var(--radius-sm);
  padding: 1.15rem;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  user-select: none;
}
.sm-mode-card:hover {
  border-color: rgba(54, 217, 176, 0.5);
  background: rgba(54, 217, 176, 0.03);
}
.sm-mode-card.active {
  border-color: var(--accent-mint);
  background: rgba(54, 217, 176, 0.08);
  box-shadow: 0 0 0 1px var(--accent-mint);
}
.sm-mode-card-header {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.sm-mode-card-header input[type="radio"] {
  accent-color: var(--accent-mint);
  width: 17px;
  height: 17px;
  cursor: pointer;
}
.sm-mode-card-title {
  font-weight: 700;
  font-size: 0.95rem;
  color: #fff;
}
.sm-mode-card-desc {
  font-size: 0.82rem;
  color: var(--text-muted);
  line-height: 1.4;
}
</style>

<div class="admin-content">

  <!-- SECTION A: Top Hero Header & Actions -->
  <div class="sm-hero">
    <div class="sm-hero-text">
      <h1>Website-Wide Schema Manager</h1>
      <p>Configure, audit, validate, and synchronize Schema.org structured data across all 24 public website pages (Core Pages, Services, and Blog Articles), dynamic postings, and custom JSON-LD schemas.</p>
    </div>
    <div class="sm-hero-actions">
      <button type="button" class="btn btn-primary" onclick="openCustomSchemaModal('homepage', 'Homepage')" style="background: linear-gradient(135deg, #00C888 0%, #00a86b 100%);">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        + Add Custom Schema
      </button>
      <button type="button" class="btn btn-outline-mint" onclick="runWebsiteAudit()">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
        Run Full Website Audit
      </button>
      <button type="button" class="btn btn-secondary" onclick="autoCreateAllMissing()">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg>
        Auto-Generate &amp; Publish Missing
      </button>
      <button type="button" class="btn btn-secondary" onclick="openAuditReportModal()">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        View Audit Report
      </button>
    </div>
  </div>

  <!-- SECTION A: Real-Time Audit Metrics Dashboard (8 Stat Cards) -->
  <div class="sm-stats-grid">
    <div class="sm-stat-card">
      <div class="sm-stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
      </div>
      <div>
        <div class="sm-stat-val" id="metric_total_pages"><?= $metrics['total_pages'] ?></div>
        <div class="sm-stat-lbl">Discovered Pages</div>
      </div>
    </div>

    <div class="sm-stat-card">
      <div class="sm-stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
      </div>
      <div>
        <div class="sm-stat-val" id="metric_existing_schemas"><?= $metrics['existing_schemas'] ?></div>
        <div class="sm-stat-lbl">Existing Schemas</div>
      </div>
    </div>

    <div class="sm-stat-card">
      <div class="sm-stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
      </div>
      <div>
        <div class="sm-stat-val" id="metric_valid_schemas"><?= $metrics['valid_schemas'] ?></div>
        <div class="sm-stat-lbl">Valid Schemas</div>
      </div>
    </div>

    <div class="sm-stat-card">
      <div class="sm-stat-icon <?= $metrics['missing_schemas'] > 0 ? 'warn' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      </div>
      <div>
        <div class="sm-stat-val" id="metric_missing_schemas"><?= $metrics['missing_schemas'] ?></div>
        <div class="sm-stat-lbl">Missing Recommended</div>
      </div>
    </div>

    <div class="sm-stat-card">
      <div class="sm-stat-icon <?= $metrics['invalid_schemas'] > 0 ? 'danger' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
      </div>
      <div>
        <div class="sm-stat-val" id="metric_invalid_schemas"><?= $metrics['invalid_schemas'] ?></div>
        <div class="sm-stat-lbl">Invalid Schemas</div>
      </div>
    </div>

    <div class="sm-stat-card">
      <div class="sm-stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 16H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2m-6 12h8a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2z"></path></svg>
      </div>
      <div>
        <div class="sm-stat-val" id="metric_duplicate_schemas"><?= $metrics['duplicate_schemas'] ?></div>
        <div class="sm-stat-lbl">Duplicate Schemas</div>
      </div>
    </div>

    <div class="sm-stat-card">
      <div class="sm-stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
      </div>
      <div>
        <div class="sm-stat-val" id="metric_published_schemas"><?= $metrics['published_schemas'] ?></div>
        <div class="sm-stat-lbl">Published Schemas</div>
      </div>
    </div>

    <div class="sm-stat-card">
      <div class="sm-stat-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
      </div>
      <div>
        <div class="sm-stat-val" id="metric_draft_schemas"><?= $metrics['draft_schemas'] ?></div>
        <div class="sm-stat-lbl">Draft Schemas</div>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="sm-tabs-bar">
    <button type="button" class="sm-tab-btn active" onclick="switchSmTab('static_pages', this)">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"></path></svg>
      Page-wise Schemas
      <span class="sm-tab-pill"><?= $metrics['total_pages'] ?></span>
    </button>

    <button type="button" class="sm-tab-btn" onclick="switchSmTab('global_blog', this)">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
      Global Blog Schema Settings
      <span class="sm-tab-pill"><?= $autoBlogSchemaEnabled === '1' ? 'Enabled' : 'Disabled' ?></span>
    </button>

    <button type="button" class="sm-tab-btn" onclick="switchSmTab('blog_overrides', this)">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
      Individual Blog Overrides
      <span class="sm-tab-pill" id="overridesCountPill"><?= count($initialBlogs) ?> Articles</span>
    </button>

    <button type="button" class="sm-tab-btn" onclick="switchSmTab('blogs_section', this)">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path><rect x="2" y="17" width="4" height="4" rx="1" fill="currentColor" opacity="0.3"></rect></svg>
      Blogs Schema Management
      <span class="sm-tab-pill"><?= count($publishedBlogsForTab) ?> Articles</span>
    </button>
  </div>

  <!-- =========================================================================
       TAB 1: SECTION B & C: PAGE-WISE SCHEMAS & INDIVIDUAL SCHEMA CARDS
       ========================================================================= -->
  <div id="tab_static_pages" class="sm-panel active">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
      <div>
        <h3 style="color:#fff; margin-bottom:0.25rem;">Discovered Website Pages</h3>
        <p style="color:var(--text-muted); font-size:0.88rem; margin:0;">
          Manage and configure individual Schema.org cards for Homepage, About Us, Services Directory, all 9 Service Pages, Blog Listing, and Contact Us.
        </p>
      </div>

      <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="filterStaticPages('all')">All Pages (<?= count($auditPages) ?>)</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="filterStaticPages('core')">Core Pages (5)</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="filterStaticPages('service')">Service Pages (9)</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="filterStaticPages('blog')">Blog Articles (10)</button>
      </div>
    </div>

    <!-- Page Cards List -->
    <div class="sm-pages-list">
      <?php foreach ($auditPages as $pKey => $pageInfo): 
        $isCore = ($pageInfo['category'] === 'Core Pages');
        $isBlog = ($pageInfo['category'] === 'Blog Articles');
        $catSlug = $isCore ? 'core' : ($isBlog ? 'blog' : 'service');
        $configured = $pageInfo['configured_schemas'];
        $missing = $pageInfo['missing_types'];
        $recommended = $pageInfo['recommended_types'];
      ?>
        <div class="sm-page-card" data-category="<?= $catSlug ?>" id="page_card_<?= e($pKey) ?>">
          
          <!-- Page Header (Section B) -->
          <div class="sm-page-card-header" onclick="togglePageAccordion('<?= e($pKey) ?>')">
            <div class="sm-page-info">
              <span class="sm-page-badge <?= $catSlug ?>">
                <?= $isCore ? 'Core' : ($isBlog ? 'Blog' : 'Service') ?>
              </span>
              <div>
                <div class="sm-page-title"><?= e($pageInfo['title']) ?></div>
                <div class="sm-page-url"><?= e($pageInfo['canonical_url']) ?></div>
              </div>
            </div>

            <div class="sm-page-actions">
              <span class="badge" style="background:var(--bg-surface-elevated); color:var(--text-primary); border:1px solid var(--border-sage);">
                <?= count($configured) ?> Configured
              </span>

              <?php if (empty($missing)): ?>
                <span class="sm-status-pill published"><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Complete</span>
              <?php else: ?>
                <span class="sm-status-pill missing"><?= count($missing) ?> Missing</span>
              <?php endif; ?>

              <button type="button" class="btn btn-sm btn-outline-mint" onclick="event.stopPropagation(); openNewSchemaModal('<?= e($pKey) ?>', '<?= e(addslashes($pageInfo['title'])) ?>')">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Add Schema
              </button>

              <button type="button" class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); openCustomSchemaModal('<?= e($pKey) ?>', '<?= e(addslashes($pageInfo['title'])) ?>')">
                + Custom
              </button>

              <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="acc-chevron" id="chevron_<?= e($pKey) ?>"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>
          </div>

          <!-- Page Body: SECTION C: Individual Schema Cards Grid -->
          <div class="sm-page-body" id="body_<?= e($pKey) ?>" style="display: <?= ($pKey === 'homepage') ? 'block' : 'none' ?>;">
            
            <div class="sm-schema-cards-grid">
              <?php 
              // 1. Render all configured schemas
              foreach ($configured as $cType => $cardItem): 
              ?>
                <div class="sm-card-item configured" id="schema_card_<?= e($pKey) ?>_<?= e(preg_replace('/[^a-zA-Z0-9_-]/', '_', $cType)) ?>">
                  <div>
                    <div class="sm-card-header">
                      <div class="sm-card-type-title">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><polyline points="9 11 12 14 22 4"></polyline></svg>
                        @type: <?= e($cardItem['type'] ?? $cType) ?>
                      </div>
                      <span class="sm-status-pill <?= ($cardItem['status'] ?? 'published') === 'published' ? 'published' : 'draft' ?>">
                        <?= ucfirst(e($cardItem['status'] ?? 'published')) ?>
                      </span>
                    </div>

                    <div class="sm-card-name">
                      <?= e($cardItem['name'] ?? ($pageInfo['title'] . ' ' . $cType)) ?>
                    </div>

                    <div class="sm-card-badges">
                      <span class="badge" style="background:rgba(54,217,176,0.1); color:var(--accent-mint); font-size:0.75rem;">
                        <?= e($cardItem['source'] ?? 'Valid Schema.org') ?>
                      </span>
                      <?php if (!empty($cardItem['updated_at'])): ?>
                        <span class="sm-card-date">
                          Updated: <?= date('M d, Y', strtotime($cardItem['updated_at'])) ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Actions for this individual schema card -->
                  <div class="sm-card-actions">
                    <?php if (is_numeric($cardItem['id'])): ?>
                      <button type="button" class="btn btn-secondary btn-sm" onclick="editSchemaById(<?= $cardItem['id'] ?>)">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        Configure
                      </button>

                      <button type="button" class="btn btn-secondary btn-sm" onclick="previewSchemaById(<?= $cardItem['id'] ?>)">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        Preview
                      </button>

                      <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSchemaPublish(<?= $cardItem['id'] ?>, '<?= $cardItem['status'] ?>')">
                        <?= $cardItem['status'] === 'published' ? 'Unpublish' : 'Publish' ?>
                      </button>

                      <button type="button" class="btn btn-danger btn-sm" onclick="deleteSchema(<?= $cardItem['id'] ?>, '<?= e(addslashes($cardItem['name'])) ?>')">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                      </button>
                    <?php elseif (!empty($pageInfo['post_id'])): ?>
                      <button type="button" class="btn btn-secondary btn-sm" onclick="openBlogOverrideModal(<?= (int)$pageInfo['post_id'] ?>)">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        Configure Blog Schema
                      </button>
                      <button type="button" class="btn btn-secondary btn-sm" onclick="previewBlogPublicSchema('<?= e($pageInfo['canonical_url']) ?>')">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        Preview
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>

              <?php 
              // 2. Render missing recommended schemas
              foreach ($missing as $recType): 
              ?>
                <div class="sm-card-item missing" id="schema_card_<?= e($pKey) ?>_<?= e($recType) ?>">
                  <div>
                    <div class="sm-card-header">
                      <div class="sm-card-type-title">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><polyline points="9 11 12 14 22 4"></polyline></svg>
                        @type: <?= e($recType) ?>
                      </div>
                      <span class="sm-status-pill missing">Missing</span>
                    </div>

                    <div class="sm-card-name">
                      <?= e($pageInfo['title'] . ' ' . $recType) ?>
                    </div>

                    <div class="sm-card-badges">
                      <span class="badge" style="background:rgba(229,185,90,0.1); color:#f7d283; font-size:0.75rem;">
                        Recommended Schema
                      </span>
                    </div>
                  </div>

                  <!-- Actions for this missing schema card -->
                  <div class="sm-card-actions">
                    <button type="button" class="btn btn-outline-mint btn-sm" onclick="autoGenerateSingleSchema('<?= e($pKey) ?>', '<?= e($recType) ?>')">
                      <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                      Auto-Generate
                    </button>

                    <button type="button" class="btn btn-secondary btn-sm" onclick="openNewSchemaModalWithType('<?= e($pKey) ?>', '<?= e(addslashes($pageInfo['title'])) ?>', '<?= e($recType) ?>')">
                      Configure &amp; Add
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- =========================================================================
       TAB 2: GLOBAL BLOG SCHEMA SETTINGS
       ========================================================================= -->
  <div id="tab_global_blog" class="sm-panel">
    
    <div class="card" style="margin-bottom:2rem;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
          <h2 style="font-size:1.35rem; color:#fff; margin-bottom:0.35rem;">Automatic Blog Schema Generation</h2>
          <p style="color:var(--text-muted); font-size:0.9rem; margin:0;">
            Controls whether Schema.org <code>BlogPosting</code> JSON-LD is automatically generated and embedded for published blog posts.
          </p>
        </div>

        <div style="display:flex; align-items:center; gap:0.75rem;">
          <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer;">
            <input type="checkbox" id="auto_blog_schema_enabled" value="1" <?= $autoBlogSchemaEnabled === '1' ? 'checked' : '' ?> style="width:20px; height:20px; accent-color:var(--accent-mint);">
            <span style="font-weight:700; color:#fff;">Enable Automatic Blog Schema</span>
          </label>
        </div>
      </div>

      <div style="background:var(--bg-input); border:1px solid var(--border-sage); border-radius:var(--radius-sm); padding:1rem 1.25rem; margin-bottom:1.5rem;">
        <h4 style="font-size:0.95rem; color:var(--accent-teal); margin-bottom:0.5rem;">Available Template Variables</h4>
        <div style="display:flex; flex-wrap:wrap; gap:0.6rem;">
          <code class="sm-tool-btn" onclick="insertVariableToTemplate('{{title}}')">{{title}} &rarr; Title</code>
          <code class="sm-tool-btn" onclick="insertVariableToTemplate('{{description}}')">{{description}} &rarr; Meta Description / Excerpt</code>
          <code class="sm-tool-btn" onclick="insertVariableToTemplate('{{canonical_url}}')">{{canonical_url}} &rarr; Canonical Clean URL</code>
          <code class="sm-tool-btn" onclick="insertVariableToTemplate('{{featured_image}}')">{{featured_image}} &rarr; Featured Image</code>
          <code class="sm-tool-btn" onclick="insertVariableToTemplate('{{author_name}}')">{{author_name}} &rarr; Author Name</code>
          <code class="sm-tool-btn" onclick="insertVariableToTemplate('{{author_role}}')">{{author_role}} &rarr; Author Role</code>
          <code class="sm-tool-btn" onclick="insertVariableToTemplate('{{published_at}}')">{{published_at}} &rarr; Published ISO Date</code>
          <code class="sm-tool-btn" onclick="insertVariableToTemplate('{{updated_at}}')">{{updated_at}} &rarr; Updated ISO Date</code>
        </div>
      </div>

      <!-- Two-Column Global Blog Template Editor -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;" id="globalBlogEditorGrid">
        
        <!-- Left: Code Area -->
        <div class="sm-editor-pane">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <label style="font-weight:700; color:#fff; font-size:0.95rem;">BlogPosting Template JSON-LD</label>
            <div class="sm-tool-bar">
              <button type="button" class="sm-tool-btn" onclick="formatGlobalBlogTemplate()">Format JSON</button>
              <button type="button" class="sm-tool-btn" onclick="resetGlobalBlogTemplate()">Reset to Default</button>
            </div>
          </div>

          <textarea id="global_blog_template" class="sm-editor-textarea" style="min-height:420px;"><?= htmlspecialchars($globalBlogTemplate, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <!-- Right: Real-time Live Preview & Validation -->
        <div class="sm-preview-pane">
          <div class="sm-preview-header">
            <span>Template Syntax &amp; Validation</span>
            <span id="global_template_val_badge" class="badge" style="background:rgba(54, 217, 176, 0.15); color:var(--accent-mint);">Valid</span>
          </div>
          <div class="sm-preview-content" id="global_template_preview">
            <?= htmlspecialchars($globalBlogTemplate, ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="sm-validation-box" id="global_template_val_box">
            <div class="sm-val-valid">
              <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Standard Schema.org BlogPosting syntax is valid.
            </div>
          </div>
        </div>

      </div>

      <div style="margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div style="display:flex; align-items:center; gap:0.75rem;">
          <span style="font-size:0.88rem; color:var(--text-muted);">Template Status:</span>
          <select id="global_template_status" class="form-control" style="width:auto;">
            <option value="published" <?= $globalTemplateStatus === 'published' ? 'selected' : '' ?>>Published (Live Output)</option>
            <option value="draft" <?= $globalTemplateStatus === 'draft' ? 'selected' : '' ?>>Draft (Admin Testing Only)</option>
          </select>
        </div>

        <button type="button" class="btn btn-primary" onclick="saveGlobalBlogSettings()">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
          Save Blog Settings
        </button>
      </div>

    </div>

  </div>

  <!-- =========================================================================
       TAB 3: SECTION E: INDIVIDUAL BLOG OVERRIDES
       ========================================================================= -->
  <div id="tab_blog_overrides" class="sm-panel">
    
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
          <h2 style="font-size:1.35rem; color:#fff; margin-bottom:0.35rem;">Individual Blog Article Schema Overrides</h2>
          <p style="color:var(--text-muted); font-size:0.9rem; margin:0;">
            Configure individual Schema.org overrides for specific blog articles. You can let an article inherit the global template, supply a custom JSON-LD override, or disable schema output completely.
          </p>
        </div>
      </div>

      <!-- Search & Filter Controls -->
      <div style="background:var(--bg-input); border:1px solid var(--border-sage); border-radius:var(--radius-sm); padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; gap:1rem; flex-wrap:wrap; align-items:center; justify-content:space-between;">
        <div style="flex:1; min-width:260px; max-width:420px; position:relative;">
          <input type="text" id="blogSearchInput" class="form-control" placeholder="Search blog title, slug, or category..." oninput="debounceBlogSearch()">
        </div>

        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
          <select id="blogStatusFilter" class="form-control" onchange="performBlogSearch()" style="width:auto;">
            <option value="all">All Post Statuses</option>
            <option value="published">Published Only</option>
            <option value="draft">Draft Only</option>
          </select>

          <select id="blogModeFilter" class="form-control" onchange="performBlogSearch()" style="width:auto;">
            <option value="all">All Schema Modes</option>
            <option value="global">Global Template</option>
            <option value="custom">Custom Override</option>
            <option value="disabled">Schema Disabled</option>
          </select>

          <span class="badge" style="background:var(--bg-surface-elevated); border:1px solid var(--border-sage); padding:0.5rem 0.85rem;" id="blogCountBadge">
            <?= count($initialBlogs) ?> Articles
          </span>
        </div>
      </div>

      <!-- Structured Blog Articles Table -->
      <div style="overflow-x:auto;">
        <table class="sm-blog-table" id="blogOverridesTable">
          <thead>
            <tr>
              <th style="width:40%;">Blog Article</th>
              <th style="width:16%;">Category</th>
              <th style="width:14%;">Post Status</th>
              <th style="width:18%;">Current Schema Mode</th>
              <th style="width:12%; text-align:right;">Action</th>
            </tr>
          </thead>
          <tbody id="blogTableBody">
            <?php if (empty($initialBlogs)): ?>
              <tr>
                <td colspan="5" style="text-align:center; padding:2rem; color:var(--text-muted);">
                  No blog articles found.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($initialBlogs as $b): 
                $isOverridden = ((int)($b['override_enabled'] ?? 0) === 1);
                $isDisabled = ((int)($b['schema_disabled'] ?? 0) === 1);
                $isPublishedPost = ($b['status'] === 'published');
              ?>
                <tr id="blog_tr_<?= $b['id'] ?>">
                  <td>
                    <div class="sm-blog-row-title"><?= e($b['title']) ?></div>
                    <div class="sm-blog-row-slug">/blog/<?= e($b['slug']) ?></div>
                  </td>
                  <td>
                    <span class="badge" style="background:var(--bg-surface-elevated); border:1px solid var(--border-sage);">
                      <?= e($b['category_name'] ?? 'Uncategorized') ?>
                    </span>
                  </td>
                  <td>
                    <span class="sm-status-pill <?= $isPublishedPost ? 'published' : 'draft' ?>">
                      <?= $isPublishedPost ? 'Published' : 'Draft' ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($isDisabled): ?>
                      <span class="sm-status-pill missing" style="background:rgba(255,107,107,0.15); color:var(--danger); border:1px solid rgba(255,107,107,0.3);">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                        Schema Disabled
                      </span>
                    <?php elseif ($isOverridden): ?>
                      <span class="sm-status-pill published">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        Custom Override
                      </span>
                    <?php else: ?>
                      <span class="sm-status-pill" style="background:rgba(0,191,165,0.15); color:var(--accent-teal); border:1px solid rgba(0,191,165,0.3);">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        Global Template
                      </span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:right;">
                    <button type="button" class="btn btn-sm btn-outline-mint" onclick="openBlogOverrideModal(<?= $b['id'] ?>)">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                      Configure
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>

  </div>

  <!-- =========================================================================
       TAB 4: BLOGS SCHEMA MANAGEMENT
       ========================================================================= -->
  <div id="tab_blogs_section" class="sm-panel">

    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
          <h2 style="font-size:1.35rem; color:#fff; margin-bottom:0.35rem;">Blogs Schema Management</h2>
          <p style="color:var(--text-muted); font-size:0.9rem; margin:0;">
            Manage Schema.org structured data for the Blog Listing page and each individual published blog article.
            Schemas are stored per page key (<code>blog-{slug}</code>) in the page_schemas table.
          </p>
        </div>
        <button type="button" class="btn btn-primary" onclick="autoGenerateAllBlogSchemas()" style="flex-shrink:0;">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
          Auto-Generate All Blog Schemas
        </button>
      </div>

      <!-- Section A: Blog Listing Page Schemas -->
      <div style="margin-bottom:2rem;">
        <h3 style="font-size:1.1rem; color:var(--accent-teal); margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:1px solid var(--border-sage);">
          Blog Listing Page
        </h3>
        <?php
        $blBreadcrumbId = isset($blogListingSchemas['BreadcrumbList']) ? (int)$blogListingSchemas['BreadcrumbList']['id'] : 'null';
        $blWebPageId    = isset($blogListingSchemas['WebPage']) ? (int)$blogListingSchemas['WebPage']['id'] : 'null';
        $blBcStatus     = isset($blogListingSchemas['BreadcrumbList']) ? ($blogListingSchemas['BreadcrumbList']['status'] === 'published' ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#36D9B0;margin-right:4px;"></span>Live' : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-right:4px;"></span>Draft') : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.15);margin-right:4px;"></span>None';
        $blWpStatus     = isset($blogListingSchemas['WebPage']) ? ($blogListingSchemas['WebPage']['status'] === 'published' ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#36D9B0;margin-right:4px;"></span>Live' : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-right:4px;"></span>Draft') : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.15);margin-right:4px;"></span>None';
        ?>
        <div style="background:var(--bg-surface); border:1px solid var(--border-sage); border-radius:var(--radius-md); padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
          <div>
            <div style="font-weight:700; color:#fff; margin-bottom:0.25rem;">Blog Listing — /blogs</div>
            <div style="font-size:0.82rem; color:var(--text-muted); font-family:var(--font-mono);">page_key: blog-listing</div>
          </div>
          <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
            <div style="font-size:0.82rem; color:var(--text-muted);">
              Breadcrumbs: <?= $blBcStatus ?> &nbsp;|&nbsp; WebPage: <?= $blWpStatus ?>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
              <button class="btn btn-sm btn-secondary" onclick="openSchemaEditor('blog-listing', <?= $blBreadcrumbId ?>, 'BreadcrumbList')">
                BreadcrumbList
              </button>
              <button class="btn btn-sm btn-secondary" onclick="openSchemaEditor('blog-listing', <?= $blWebPageId ?>, 'WebPage')">
                WebPage
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Section B: Individual Published Blog Articles -->
      <div>
        <h3 style="font-size:1.1rem; color:var(--accent-teal); margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:1px solid var(--border-sage);">
          Individual Published Blog Articles
          <span style="font-size:0.85rem; color:var(--text-muted); font-weight:400; margin-left:0.75rem;"><?= count($publishedBlogsForTab) ?> articles</span>
        </h3>

        <?php if (empty($publishedBlogsForTab)): ?>
          <div style="text-align:center; padding:2.5rem; color:var(--text-muted);">
            <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin-bottom:1rem; opacity:0.4;"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <p>No published blog articles found.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Article</th>
                  <th>Category</th>
                  <th>Published</th>
                  <th style="text-align:center;">Organization</th>
                  <th style="text-align:center;">BreadcrumbList</th>
                  <th style="text-align:center;">BlogPosting</th>
                  <th style="text-align:center;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($publishedBlogsForTab as $pb):
                  $slug    = $pb['slug'];
                  $pageKey = 'blog-' . $slug;
                  $schemas = $blogPageSchemas[$slug] ?? [];
                  $catName = $pb['category_name'] ?: 'Editorial';
                  $pubDate = $pb['published_at'] ? date('d M Y', strtotime($pb['published_at'])) : '—';

                  $schemaTypes = ['Organization', 'BreadcrumbList', 'BlogPosting'];
                ?>
                <tr>
                  <td>
                    <div style="font-weight:600; color:#fff; margin-bottom:0.2rem;"><?= htmlspecialchars($pb['title']) ?></div>
                    <div style="font-size:0.78rem; color:var(--text-muted); font-family:var(--font-mono);"><?= htmlspecialchars($pageKey) ?></div>
                  </td>
                  <td style="color:var(--text-muted);"><?= htmlspecialchars($catName) ?></td>
                  <td style="color:var(--text-muted); white-space:nowrap;"><?= $pubDate ?></td>
                  <?php foreach ($schemaTypes as $sType):
                    $existing = $schemas[$sType] ?? null;
                    $statusDot = $existing
                      ? ($existing['status'] === 'published'
                        ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#36D9B0;margin-right:4px;"></span>Live'
                        : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-right:4px;"></span>Draft')
                      : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.15);margin-right:4px;"></span>None';
                    $schemaId = $existing['id'] ?? 'null';
                  ?>
                  <td style="text-align:center; font-size:0.82rem; white-space:nowrap;">
                    <?= $statusDot ?>
                  </td>
                  <?php endforeach; ?>
                  <td style="text-align:center;">
                    <div style="display:flex; gap:0.4rem; justify-content:center; flex-wrap:wrap;">
                      <?php foreach ($schemaTypes as $sType):
                        $existing = $schemas[$sType] ?? null;
                        $schemaId = $existing['id'] ?? 'null';
                      ?>
                      <button class="btn btn-sm btn-secondary" title="<?= $sType ?>"
                              onclick="openSchemaEditor('<?= htmlspecialchars($pageKey) ?>', <?= $schemaId === 'null' ? 'null' : (int)$schemaId ?>, '<?= $sType ?>')"
                              style="font-size:0.72rem; padding:0.25rem 0.5rem;">
                        <?= $sType === 'BreadcrumbList' ? 'Breadcrumb' : $sType ?>
                      </button>
                      <?php endforeach; ?>
                      <button class="btn btn-sm btn-primary" title="Auto-generate all schemas for this blog"
                              onclick="autoGenerateSingleBlogSchemas('<?= htmlspecialchars($pageKey) ?>', '<?= htmlspecialchars($pb['title']) ?>')"
                              style="font-size:0.72rem; padding:0.25rem 0.5rem;">
                        ⚡ Auto
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- blogs.md Sync Status Card -->
    <div class="card" style="margin-top:1.5rem;">
      <h3 style="font-size:1.1rem; color:var(--accent-teal); margin-bottom:1rem;">blogs.md Sync Status</h3>
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
          <?php
          $blogsMdPath = CMS_ROOT . '/blogs.md';
          $blogsMdExists = file_exists($blogsMdPath);
          $blogsMdSize   = $blogsMdExists ? filesize($blogsMdPath) : 0;
          $blogsMdMtime  = $blogsMdExists ? date('d M Y, H:i', filemtime($blogsMdPath)) : 'Never';
          ?>
          <p style="color:var(--text-muted); margin:0 0 0.5rem 0; font-size:0.9rem;">
            <?php if ($blogsMdExists): ?>
              <span style="color:#36D9B0;">●</span> <strong>blogs.md exists</strong> — <?= number_format($blogsMdSize) ?> bytes, last updated <?= $blogsMdMtime ?>
            <?php else: ?>
              <span style="color:#f87171;">●</span> <strong>blogs.md does not exist</strong> — generate it below.
            <?php endif; ?>
          </p>
          <p style="color:var(--text-muted); font-size:0.82rem; font-family:var(--font-mono); margin:0;">
            Public URL: <a href="/blogs.md" target="_blank" style="color:var(--accent-teal);">/blogs.md</a>
            &nbsp;|&nbsp; <?= count($publishedBlogsForTab) ?> published articles
          </p>
        </div>
        <button type="button" class="btn btn-secondary" onclick="syncBlogsMdNow()">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
          Sync blogs.md Now
        </button>
      </div>
    </div>

  </div>

</div>

<!-- =========================================================================
     MODAL 1: SECTION D: EDIT / CREATE SCHEMA MODAL
     ========================================================================= -->
<div id="schemaEditorModal" class="sm-modal-backdrop" onclick="closeModalOnBackdrop(event, 'schemaEditorModal')">
  <div class="sm-modal-dialog" onclick="event.stopPropagation()">
    
    <div class="sm-modal-header">
      <div>
        <h3 id="modalPageTitleHeader">Edit Schema</h3>
        <span style="font-size:0.82rem; color:var(--text-muted); font-family:var(--font-mono);" id="modalPageKeySubtitle">homepage</span>
      </div>
      <button type="button" class="btn-icon" onclick="closeSchemaModal()" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer;">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>

    <div class="sm-modal-body">
      
      <!-- Left Column: Properties & Code Editor -->
      <div class="sm-editor-pane">
        
        <input type="hidden" id="modal_schema_id" value="0">
        <input type="hidden" id="modal_page_key" value="">
        <input type="hidden" id="modal_page_title" value="">
        
        <div class="form-group" style="margin-bottom:0.75rem;">
          <label style="font-size:0.85rem; font-weight:700; color:#fff; display:block; margin-bottom:0.35rem;">Assign To Website Page</label>
          <select id="modal_page_select" class="form-control" onchange="onModalPageSelectChange()">
            <optgroup label="Core Pages (5)">
              <?php foreach ($auditPages as $pk => $pinfo): if ($pinfo['category'] === 'Core Pages'): ?>
                <option value="<?= e($pk) ?>" data-title="<?= e($pinfo['title']) ?>"><?= e($pinfo['title']) ?> (<?= e($pinfo['canonical_url']) ?>)</option>
              <?php endif; endforeach; ?>
            </optgroup>
            <optgroup label="Service Pages (9)">
              <?php foreach ($auditPages as $pk => $pinfo): if ($pinfo['category'] === 'Individual Service Pages' || $pinfo['category'] === 'Service Pages'): ?>
                <option value="<?= e($pk) ?>" data-title="<?= e($pinfo['title']) ?>"><?= e($pinfo['title']) ?> (<?= e($pinfo['canonical_url']) ?>)</option>
              <?php endif; endforeach; ?>
            </optgroup>
            <optgroup label="Blog Articles (10)">
              <?php foreach ($auditPages as $pk => $pinfo): if ($pinfo['category'] === 'Blog Articles'): ?>
                <option value="<?= e($pk) ?>" data-title="<?= e($pinfo['title']) ?>"><?= e($pinfo['title']) ?> (<?= e($pinfo['canonical_url']) ?>)</option>
              <?php endif; endforeach; ?>
            </optgroup>
          </select>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div class="form-group" style="margin-bottom:0;">
            <label style="font-size:0.85rem; font-weight:700; color:#fff; display:block; margin-bottom:0.35rem;">Schema Name</label>
            <input type="text" id="modal_schema_name" class="form-control" placeholder="e.g. Primary Organization Schema">
          </div>

          <div class="form-group" style="margin-bottom:0;">
            <label style="font-size:0.85rem; font-weight:700; color:#fff; display:block; margin-bottom:0.35rem;">Schema.org Type</label>
            <select id="modal_schema_type" class="form-control" onchange="onModalTypeSelectChange()">
              <?php foreach ($supportedTypes as $typeKey => $typeLabel): ?>
                <option value="<?= e($typeKey) ?>"><?= e($typeLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.75rem;">
          <label style="font-size:0.85rem; font-weight:700; color:#fff;">JSON-LD Payload</label>
          <div class="sm-tool-bar">
            <button type="button" class="sm-tool-btn" onclick="document.getElementById('modal_schema_file_input').click()">Upload JSON File</button>
            <input type="file" id="modal_schema_file_input" accept=".json,application/json" style="display:none;" onchange="handleModalFileUpload(event)">
            <button type="button" class="sm-tool-btn" onclick="copyModalJson()">Copy JSON</button>
            <button type="button" class="sm-tool-btn" onclick="insertTemplateSnippetIntoModal()">Insert Default Snippet</button>
            <button type="button" class="sm-tool-btn" onclick="formatModalJson()">Format JSON</button>
            <button type="button" class="sm-tool-btn" onclick="revertModalToOriginal()">Revert</button>
          </div>
        </div>

        <textarea id="modal_schema_data" class="sm-editor-textarea" placeholder="Paste or type JSON-LD here..."></textarea>
      </div>

      <!-- Right Column: Live Preview & Validation Status -->
      <div class="sm-preview-pane">
        <div class="sm-preview-header">
          <span>Live Preview &amp; Syntax Check</span>
          <span id="modal_val_badge" class="badge" style="background:rgba(54, 217, 176, 0.15); color:var(--accent-mint);">Valid</span>
        </div>

        <div class="sm-preview-content" id="modal_json_preview"></div>

        <div class="sm-validation-box" id="modal_validation_box">
          <div class="sm-val-valid">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
            Valid Schema.org JSON-LD
          </div>
        </div>
      </div>

    </div>

    <div class="sm-modal-footer">
      <div style="display:flex; align-items:center; gap:0.75rem;">
        <span style="font-size:0.85rem; color:var(--text-muted);">Publication Status:</span>
        <select id="modal_status" class="form-control" style="width:auto; padding:0.35rem 0.75rem; font-size:0.85rem;">
          <option value="published">Published (Live On Site)</option>
          <option value="draft">Draft (Admin Only)</option>
        </select>
      </div>

      <div style="display:flex; gap:0.75rem;">
        <button type="button" class="btn btn-secondary" onclick="closeSchemaModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitModalSave()">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
          Save Schema
        </button>
      </div>
    </div>

  </div>
</div>

<!-- =========================================================================
     MODAL 2: SECTION E: CONFIGURE INDIVIDUAL BLOG SCHEMA OVERRIDE
     ========================================================================= -->
<div id="blogOverrideModal" class="sm-modal-backdrop" onclick="closeModalOnBackdrop(event, 'blogOverrideModal')">
  <div class="sm-modal-dialog" style="max-width:1150px;" onclick="event.stopPropagation()">
    
    <div class="sm-modal-header">
      <div>
        <h3 id="overrideModalBlogTitle">Configure Blog Schema</h3>
        <div style="display:flex; align-items:center; gap:0.75rem; margin-top:0.35rem; flex-wrap:wrap;">
          <span class="sm-blog-row-slug" id="overrideModalBlogSlug">/blog/...</span>
          <span class="badge" style="background:var(--bg-surface-elevated); border:1px solid var(--border-sage);" id="overrideModalCategory">Category</span>
          <span class="sm-status-pill published" id="overrideModalPostStatus">Published</span>
        </div>
      </div>
      <button type="button" class="btn-icon" onclick="closeBlogOverrideModal()" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer;">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>

    <div style="padding:1.5rem 1.75rem; overflow-y:auto; flex:1;">
      
      <input type="hidden" id="override_modal_post_id" value="0">

      <div class="sm-alert-warn" id="overrideModalDraftAlert" style="display:none;">
        <strong>Draft Article Warning:</strong> This post is currently saved as a draft. Schema will be compiled and ready, but per strict security and SEO rules, it will <em>never</em> be publicly output until the post is published.
      </div>

      <!-- Mode Selection Cards (3 Modes) -->
      <label style="font-size:0.9rem; font-weight:700; color:#fff; display:block; margin-bottom:0.75rem;">Select Schema Mode for This Article</label>
      <div class="sm-mode-cards-grid">
        
        <!-- Mode 1: Inherit Global -->
        <div class="sm-mode-card active" id="modeCard_inherit" onclick="selectOverrideMode('inherit')">
          <div class="sm-mode-card-header">
            <input type="radio" name="modal_override_mode" id="radio_mode_inherit" value="inherit" checked>
            <span class="sm-mode-card-title">Inherit Global Template</span>
          </div>
          <div class="sm-mode-card-desc">
            Automatically compile Schema.org <code>BlogPosting</code> metadata from active global template variables.
          </div>
        </div>

        <!-- Mode 2: Custom Override -->
        <div class="sm-mode-card" id="modeCard_custom" onclick="selectOverrideMode('custom')">
          <div class="sm-mode-card-header">
            <input type="radio" name="modal_override_mode" id="radio_mode_custom" value="custom">
            <span class="sm-mode-card-title">Custom JSON-LD Override</span>
          </div>
          <div class="sm-mode-card-desc">
            Supply hand-crafted JSON-LD payload specifically for this article. Takes precedence over global template.
          </div>
        </div>

        <!-- Mode 3: Disable Schema -->
        <div class="sm-mode-card" id="modeCard_disabled" onclick="selectOverrideMode('disabled')">
          <div class="sm-mode-card-header">
            <input type="radio" name="modal_override_mode" id="radio_mode_disabled" value="disabled">
            <span class="sm-mode-card-title">Disable Schema</span>
          </div>
          <div class="sm-mode-card-desc">
            Suppress <code>BlogPosting</code> output completely for this post. Preserves global Organization and Breadcrumbs.
          </div>
        </div>

      </div>

      <!-- Mode 1 Display: Inherit View -->
      <div id="overrideSection_inherit">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
          <label style="font-size:0.85rem; font-weight:700; color:#fff;">Compiled Global Preview for this Article</label>
          <button type="button" class="btn btn-secondary btn-sm" onclick="copyCompiledToCustomEditor()">
            Copy to Custom Editor &rarr;
          </button>
        </div>
        <pre class="sm-editor-textarea" style="min-height:220px; max-height:300px; margin:0;" id="modal_inherited_preview">Loading compiled preview...</pre>
      </div>

      <!-- Mode 2 Display: Custom Editor -->
      <div id="overrideSection_custom" style="display:none;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
          
          <div class="sm-editor-pane">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <label style="font-size:0.85rem; font-weight:700; color:#fff;">Custom JSON-LD Payload</label>
              <div class="sm-tool-bar">
                <button type="button" class="sm-tool-btn" onclick="formatOverrideModalJson()">Format</button>
                <button type="button" class="sm-tool-btn" onclick="loadBaselineIntoCustomModal()">Load Default</button>
                <button type="button" class="sm-tool-btn" onclick="revertOverrideModalJson()">Revert</button>
              </div>
            </div>
            <textarea id="modal_custom_blog_schema" class="sm-editor-textarea" style="min-height:280px;" placeholder="Paste custom Schema.org BlogPosting JSON-LD..."></textarea>
          </div>

          <div class="sm-preview-pane">
            <div class="sm-preview-header">
              <span>Live Preview &amp; Syntax</span>
              <span id="modal_custom_val_badge" class="badge" style="background:rgba(54, 217, 176, 0.15); color:var(--accent-mint);">Valid</span>
            </div>
            <div class="sm-preview-content" id="modal_custom_preview" style="max-height:240px;"></div>
            <div class="sm-validation-box" id="modal_custom_val_box">
              <div class="sm-val-valid">Valid Schema.org syntax</div>
            </div>
          </div>

        </div>

        <div style="margin-top:1rem; display:flex; align-items:center; gap:0.75rem;">
          <span style="font-size:0.85rem; color:var(--text-muted);">Custom Override Status:</span>
          <select id="modal_custom_status" class="form-control" style="width:auto; padding:0.35rem 0.75rem; font-size:0.85rem;">
            <option value="published">Published (Active on Live Blog)</option>
            <option value="draft">Draft (Testing Only - Global used in public)</option>
          </select>
        </div>
      </div>

      <!-- Mode 3 Display: Disabled View -->
      <div id="overrideSection_disabled" style="display:none;">
        <div class="sm-alert-warn" style="margin:0;">
          <strong>Schema Output Disabled:</strong> When set to Disabled, no <code>BlogPosting</code> structured data will be rendered for this post. Any custom JSON-LD previously entered will remain safely preserved in the database if you choose to re-enable it later.
        </div>
      </div>

    </div>

    <div class="sm-modal-footer">
      <div>
        <button type="button" class="btn btn-secondary btn-sm" id="modalRestoreDefaultBtn" onclick="restoreCurrentBlogDefault()" style="display:none;">
          Restore to Inherit Global
        </button>
      </div>

      <div style="display:flex; gap:0.75rem;">
        <button type="button" class="btn btn-secondary" onclick="closeBlogOverrideModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitBlogOverrideSave()">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
          Save Configuration
        </button>
      </div>
    </div>

  </div>
</div>

<!-- =========================================================================
     MODAL 3: SECTION F: FULL WEBSITE-WIDE AUDIT REPORT MODAL
     ========================================================================= -->
<div id="auditReportModal" class="sm-modal-backdrop" onclick="closeModalOnBackdrop(event, 'auditReportModal')">
  <div class="sm-modal-dialog" style="max-width:1200px;" onclick="event.stopPropagation()">
    
    <div class="sm-modal-header">
      <div>
        <h3>Website-Wide Schema Audit Report</h3>
        <span style="font-size:0.82rem; color:var(--text-muted); font-family:var(--font-mono);" id="auditReportTimestamp">Timestamp: <?= date('Y-m-d H:i:s T') ?></span>
      </div>
      <button type="button" class="btn-icon" onclick="closeAuditReportModal()" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer;">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>

    <div style="padding:1.5rem 1.75rem; overflow-y:auto; flex:1;">
      
      <div style="overflow-x:auto;">
        <table class="sm-blog-table" id="auditReportTable">
          <thead>
            <tr>
              <th style="width:26%;">Page Name &amp; URL</th>
              <th style="width:14%;">Page Type</th>
              <th style="width:24%;">Configured Schemas</th>
              <th style="width:18%;">Recommended &amp; Missing</th>
              <th style="width:18%;">Implementation Status</th>
            </tr>
          </thead>
          <tbody id="auditReportTableBody">
            <?php foreach ($auditPages as $pKey => $pInfo): 
              $hasMissing = !empty($pInfo['missing_types']);
              $hasErrors = !empty($pInfo['errors']);
              $statusClass = 'published';
              $statusText = 'Already Correct';
              if ($hasMissing) {
                $statusClass = 'draft';
                $statusText = 'Missing Recommended';
              } elseif ($hasErrors) {
                $statusClass = 'disabled';
                $statusText = 'Requires Fix';
              }
            ?>
              <tr>
                <td>
                  <div style="font-weight:700; color:#fff;"><?= e($pInfo['title']) ?></div>
                  <div style="font-size:0.75rem; color:var(--text-muted); font-family:var(--font-mono);"><?= e($pInfo['canonical_url']) ?></div>
                </td>
                <td>
                  <span class="badge" style="background:var(--bg-surface-elevated); border:1px solid var(--border-sage);">
                    <?= e($pInfo['category']) ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                    <?php foreach ($pInfo['configured_schemas'] as $t => $cfg): ?>
                      <span class="badge" style="background:rgba(54,217,176,0.15); color:var(--accent-mint); font-size:0.75rem;">
                        <?= e($t) ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td>
                  <?php if (empty($pInfo['missing_types'])): ?>
                    <span style="color:var(--accent-mint); font-size:0.82rem; font-weight:700;">&check; All <?= count($pInfo['recommended_types']) ?> Recommended Present</span>
                  <?php else: ?>
                    <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                      <?php foreach ($pInfo['missing_types'] as $mt): ?>
                        <span class="badge" style="background:rgba(229,185,90,0.15); color:#f7d283; font-size:0.75rem;">
                          <?= e($mt) ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="sm-status-pill <?= $statusClass ?>">
                    <?= $statusText ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>

    <div class="sm-modal-footer">
      <div style="font-size:0.85rem; color:var(--text-muted);">
        Audit strictly excludes Digital Audit. Output verified on live HTTP endpoints.
      </div>
      <div>
        <button type="button" class="btn btn-primary" onclick="closeAuditReportModal()">Close Report</button>
      </div>
    </div>

  </div>
</div>

<!-- =========================================================================
     MODAL 4: QUICK SCHEMA PREVIEW MODAL
     ========================================================================= -->
<div id="quickPreviewModal" class="sm-modal-backdrop" onclick="closeModalOnBackdrop(event, 'quickPreviewModal')">
  <div class="sm-modal-dialog" style="max-width:850px;" onclick="event.stopPropagation()">
    <div class="sm-modal-header">
      <div>
        <h3 id="quickPreviewTitle">Schema JSON-LD Preview</h3>
        <span style="font-size:0.82rem; color:var(--text-muted); font-family:var(--font-mono);" id="quickPreviewType"></span>
      </div>
      <button type="button" class="btn-icon" onclick="closeQuickPreviewModal()" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer;">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <div style="padding:1.5rem; overflow-y:auto; flex:1;">
      <pre id="quickPreviewContent" class="sm-editor-textarea" style="min-height:360px; max-height:480px; margin:0;" readonly></pre>
    </div>
    <div class="sm-modal-footer">
      <button type="button" class="btn btn-secondary" onclick="copyQuickPreviewCode()">Copy JSON-LD</button>
      <button type="button" class="btn btn-primary" onclick="closeQuickPreviewModal()">Done</button>
    </div>
  </div>
</div>

<!-- =========================================================================
     JAVASCRIPT CONTROLLERS
     ========================================================================= -->
<script>
const CSRF_TOKEN = '<?= e($csrfToken ?? $_SESSION['csrf_token'] ?? '') ?>';
const ADMIN_API = '<?= ADMIN_URL ?>/schema-manager';

let currentEditingOriginalData = '';
let currentOverrideOriginalSchema = '';
let currentOverrideOriginalMode = 'inherit';
let currentActiveBlogData = null;
let searchDebounceTimer = null;

// Tab switcher
function switchSmTab(tabId, btn) {
  document.querySelectorAll('.sm-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.sm-tab-btn').forEach(b => b.classList.remove('active'));
  
  const target = document.getElementById('tab_' + tabId);
  if (target) target.classList.add('active');
  if (btn) btn.classList.add('active');
}

// Category filter for static pages
function filterStaticPages(cat) {
  const cards = document.querySelectorAll('.sm-page-card');
  cards.forEach(card => {
    if (cat === 'all') {
      card.style.display = 'block';
    } else {
      card.style.display = (card.getAttribute('data-category') === cat) ? 'block' : 'none';
    }
  });
}

// Accordion toggle
function togglePageAccordion(pageKey) {
  const body = document.getElementById('body_' + pageKey);
  const chevron = document.getElementById('chevron_' + pageKey);
  if (body) {
    const isHidden = (body.style.display === 'none' || body.style.display === '');
    body.style.display = isHidden ? 'block' : 'none';
    if (chevron) {
      chevron.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
    }
  }
}

// Reset Schema Modal Form to completely clean state (prevents stale data leakage)
function resetSchemaModalForm() {
  document.getElementById('modal_schema_id').value = '0';
  document.getElementById('modal_page_key').value = '';
  document.getElementById('modal_page_title').value = '';
  
  const pageSelect = document.getElementById('modal_page_select');
  if (pageSelect) pageSelect.selectedIndex = 0;

  document.getElementById('modalPageTitleHeader').innerText = 'Add New Schema';
  document.getElementById('modalPageKeySubtitle').innerText = 'Select Page';
  document.getElementById('modal_schema_name').value = '';
  document.getElementById('modal_schema_type').value = 'WebPage';
  document.getElementById('modal_schema_data').value = '';
  document.getElementById('modal_status').value = 'published';

  const fileInput = document.getElementById('modal_schema_file_input');
  if (fileInput) fileInput.value = '';

  currentEditingOriginalData = '';
  document.getElementById('modal_json_preview').textContent = '';

  const badge = document.getElementById('modal_val_badge');
  if (badge) {
    badge.className = 'badge';
    badge.style.background = 'rgba(255, 255, 255, 0.1)';
    badge.style.color = 'var(--text-muted)';
    badge.textContent = 'Empty';
  }

  const valBox = document.getElementById('modal_validation_box');
  if (valBox) {
    valBox.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;">Enter or load JSON-LD payload to validate.</div>';
  }
}

// Open Modal for New Schema
function openNewSchemaModal(pageKey, pageTitle) {
  openNewSchemaModalWithType(pageKey, pageTitle, '');
}

function openNewSchemaModalWithType(pageKey, pageTitle, reqType) {
  resetSchemaModalForm();

  pageKey = pageKey || 'homepage';
  pageTitle = pageTitle || 'Homepage';

  const pageSelect = document.getElementById('modal_page_select');
  if (pageSelect) {
    pageSelect.value = pageKey;
    if (pageSelect.selectedIndex >= 0) {
      pageTitle = pageSelect.options[pageSelect.selectedIndex].getAttribute('data-title') || pageTitle;
    }
  }

  document.getElementById('modal_schema_id').value = '0';
  document.getElementById('modal_page_key').value = pageKey;
  document.getElementById('modal_page_title').value = pageTitle;
  document.getElementById('modalPageTitleHeader').innerText = 'Add Schema for ' + pageTitle;
  document.getElementById('modalPageKeySubtitle').innerText = pageKey;
  document.getElementById('modal_schema_name').value = pageTitle + ' ' + (reqType || 'Schema');
  document.getElementById('modal_status').value = 'published';

  let defaultType = reqType || 'WebPage';
  if (!reqType) {
    if (pageKey === 'homepage') defaultType = 'Organization';
    else if (pageKey === 'about') defaultType = 'AboutPage';
    else if (pageKey === 'contact') defaultType = 'ContactPage';
    else if (pageKey.startsWith('service-')) defaultType = 'Service';
    else if (pageKey === 'services') defaultType = 'CollectionPage';
    else if (pageKey === 'blog-listing') defaultType = 'CollectionPage';
    else if (pageKey.startsWith('blog-') || pageKey.startsWith('blog:')) defaultType = 'BlogPosting';
  }

  document.getElementById('modal_schema_type').value = defaultType;
  fetchTemplateForModal(defaultType, pageKey);

  const modal = document.getElementById('schemaEditorModal');
  modal.classList.add('open');
}

// Open Modal specifically for Custom Schema
function openCustomSchemaModal(pageKey, pageTitle) {
  resetSchemaModalForm();

  pageKey = pageKey || 'homepage';
  pageTitle = pageTitle || 'Homepage';

  const pageSelect = document.getElementById('modal_page_select');
  if (pageSelect) {
    pageSelect.value = pageKey;
    if (pageSelect.selectedIndex >= 0) {
      pageTitle = pageSelect.options[pageSelect.selectedIndex].getAttribute('data-title') || pageTitle;
    }
  }

  document.getElementById('modal_schema_id').value = '0';
  document.getElementById('modal_page_key').value = pageKey;
  document.getElementById('modal_page_title').value = pageTitle;
  document.getElementById('modalPageTitleHeader').innerText = 'Add Custom Schema';
  document.getElementById('modalPageKeySubtitle').innerText = pageKey;
  document.getElementById('modal_schema_name').value = pageTitle + ' Custom Schema';
  document.getElementById('modal_schema_type').value = 'Custom';
  document.getElementById('modal_status').value = 'published';

  fetchTemplateForModal('Custom', pageKey);

  const modal = document.getElementById('schemaEditorModal');
  modal.classList.add('open');
}

// When Page Selector in Modal Changes
function onModalPageSelectChange() {
  const select = document.getElementById('modal_page_select');
  if (!select) return;
  const selOption = select.options[select.selectedIndex];
  if (!selOption || !select.value) return;

  const newKey = select.value;
  const newTitle = selOption.getAttribute('data-title') || selOption.text;

  document.getElementById('modal_page_key').value = newKey;
  document.getElementById('modal_page_title').value = newTitle;
  document.getElementById('modalPageKeySubtitle').innerText = newKey;

  const nameField = document.getElementById('modal_schema_name');
  if (!nameField.value || nameField.value.includes('Schema')) {
    const curType = document.getElementById('modal_schema_type').value;
    nameField.value = newTitle + ' ' + (curType || 'Schema');
  }

  const type = document.getElementById('modal_schema_type').value;
  if (!currentEditingOriginalData || document.getElementById('modal_schema_data').value === currentEditingOriginalData) {
    fetchTemplateForModal(type, newKey);
  }
}

// Handle Modal JSON File Upload
function handleModalFileUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  if (!file.name.toLowerCase().endsWith('.json')) {
    alert('Invalid file format. Only .json files are accepted.');
    return;
  }

  if (file.size > 524288) {
    alert('File size exceeds maximum allowed 500KB.');
    return;
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const parsed = JSON.parse(e.target.result);
      if (!parsed || typeof parsed !== 'object') {
        throw new Error('Root JSON must be an object or array.');
      }
      const formatted = JSON.stringify(parsed, null, 2);
      document.getElementById('modal_schema_data').value = formatted;
      currentEditingOriginalData = formatted;

      const nameField = document.getElementById('modal_schema_name');
      if (!nameField.value.trim()) {
        nameField.value = file.name.replace(/\.json$/i, '');
      }

      if (parsed['@type'] && typeof parsed['@type'] === 'string') {
        const typeSelect = document.getElementById('modal_schema_type');
        for (let i = 0; i < typeSelect.options.length; i++) {
          if (typeSelect.options[i].value.toLowerCase() === parsed['@type'].toLowerCase()) {
            typeSelect.selectedIndex = i;
            break;
          }
        }
      }

      updateModalLivePreview(formatted);
    } catch (err) {
      alert('Schema File Parse Error: ' + err.message);
    }
  };
  reader.readAsText(file);
}

// Copy Modal JSON
function copyModalJson() {
  const val = document.getElementById('modal_schema_data').value;
  if (!val) {
    alert('No JSON content to copy.');
    return;
  }
  navigator.clipboard.writeText(val).then(() => {
    alert('Schema JSON copied to clipboard!');
  });
}

// Edit Existing Schema by ID
function editSchemaById(id) {
  const fd = new FormData();
  fd.append('action', 'get_schema_preview');
  fd.append('id', id);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.schema) {
        editExistingSchema(data.schema);
      } else {
        alert('Could not load schema: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(err => alert('Error: ' + err.message));
}

// Edit Existing Static/Page Schema object
function editExistingSchema(schemaObj) {
  resetSchemaModalForm();

  document.getElementById('modal_schema_id').value = schemaObj.id;
  document.getElementById('modal_page_key').value = schemaObj.page_key;
  document.getElementById('modal_page_title').value = schemaObj.page_title;

  const pageSelect = document.getElementById('modal_page_select');
  if (pageSelect) {
    pageSelect.value = schemaObj.page_key;
  }

  document.getElementById('modalPageTitleHeader').innerText = 'Edit Schema: ' + schemaObj.schema_name;
  document.getElementById('modalPageKeySubtitle').innerText = schemaObj.page_key + ' (ID #' + schemaObj.id + ')';
  document.getElementById('modal_schema_name').value = schemaObj.schema_name;
  document.getElementById('modal_schema_type').value = schemaObj.schema_type;
  document.getElementById('modal_status').value = schemaObj.status;

  currentEditingOriginalData = schemaObj.schema_data;
  document.getElementById('modal_schema_data').value = schemaObj.schema_data;

  updateModalLivePreview(schemaObj.schema_data);

  const modal = document.getElementById('schemaEditorModal');
  modal.classList.add('open');
}

function closeSchemaModal() {
  document.getElementById('schemaEditorModal').classList.remove('open');
  resetSchemaModalForm();
}

function closeModalOnBackdrop(e, modalId) {
  if (e.target.id === modalId) {
    if (modalId === 'schemaEditorModal') closeSchemaModal();
    if (modalId === 'blogOverrideModal') closeBlogOverrideModal();
    if (modalId === 'auditReportModal') closeAuditReportModal();
    if (modalId === 'quickPreviewModal') closeQuickPreviewModal();
  }
}

// Fetch template from server for static modal
function fetchTemplateForModal(type, pageKey) {
  pageKey = pageKey || document.getElementById('modal_page_key').value;
  const fd = new FormData();
  fd.append('action', 'get_type_template');
  fd.append('type', type);
  fd.append('page_key', pageKey);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => {
      if (!r.ok) {
        throw new Error('Server returned HTTP ' + r.status);
      }
      return r.json();
    })
    .then(data => {
      if (data && data.success) {
        document.getElementById('modal_schema_data').value = data.template;
        currentEditingOriginalData = data.template;
        updateModalLivePreview(data.template);
      }
    })
    .catch(err => {
      console.error('Error fetching template from server:', err);
    });
}

function onModalTypeSelectChange() {
  const type = document.getElementById('modal_schema_type').value;
  fetchTemplateForModal(type);
}

function insertTemplateSnippetIntoModal() {
  const type = document.getElementById('modal_schema_type').value;
  fetchTemplateForModal(type);
}

function revertModalToOriginal() {
  if (currentEditingOriginalData) {
    document.getElementById('modal_schema_data').value = currentEditingOriginalData;
    updateModalLivePreview(currentEditingOriginalData);
  }
}

function formatModalJson() {
  const textarea = document.getElementById('modal_schema_data');
  try {
    const parsed = JSON.parse(textarea.value);
    textarea.value = JSON.stringify(parsed, null, 2);
    updateModalLivePreview(textarea.value);
  } catch (err) {
    alert('Cannot format invalid JSON: ' + err.message);
  }
}

// Live preview & validation handler for modal
const modalTextarea = document.getElementById('modal_schema_data');
if (modalTextarea) {
  modalTextarea.addEventListener('input', function() {
    updateModalLivePreview(this.value);
  });
  modalTextarea.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
      e.preventDefault();
      const start = this.selectionStart;
      const end = this.selectionEnd;
      this.value = this.value.substring(0, start) + '  ' + this.value.substring(end);
      this.selectionStart = this.selectionEnd = start + 2;
    }
  });
}

function updateModalLivePreview(jsonText) {
  const preview = document.getElementById('modal_json_preview');
  const badge = document.getElementById('modal_val_badge');
  const valBox = document.getElementById('modal_validation_box');

  preview.textContent = jsonText;

  const fd = new FormData();
  fd.append('action', 'validate_schema');
  fd.append('schema_data', jsonText);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.valid) {
        badge.className = 'badge';
        badge.style.background = 'rgba(54, 217, 176, 0.15)';
        badge.style.color = 'var(--accent-mint)';
        badge.textContent = 'Valid';

        let warnHtml = '';
        if (res.warnings && res.warnings.length > 0) {
          warnHtml = res.warnings.map(w => `<div class="sm-val-warning">&bull; Warning: ${w}</div>`).join('');
        }
        valBox.innerHTML = `<div class="sm-val-valid"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Valid Schema.org JSON-LD</div>${warnHtml}`;
      } else {
        badge.className = 'badge';
        badge.style.background = 'rgba(255, 107, 107, 0.15)';
        badge.style.color = 'var(--danger)';
        badge.textContent = 'Syntax Error';
        valBox.innerHTML = res.errors.map(err => `<div class="sm-val-error">&bull; ${err}</div>`).join('');
      }
    })
    .catch(() => {
      try {
        JSON.parse(jsonText);
        badge.textContent = 'Valid JSON';
        valBox.innerHTML = '<div class="sm-val-valid">JSON is syntactically valid.</div>';
      } catch (e) {
        badge.textContent = 'Invalid JSON';
        valBox.innerHTML = `<div class="sm-val-error">${e.message}</div>`;
      }
    });
}

// Submit Static Modal Save
function submitModalSave() {
  const id = document.getElementById('modal_schema_id').value;
  const pageKey = document.getElementById('modal_page_key').value;
  const pageTitle = document.getElementById('modal_page_title').value;
  const schemaName = document.getElementById('modal_schema_name').value.trim();
  const schemaType = document.getElementById('modal_schema_type').value;
  const schemaData = document.getElementById('modal_schema_data').value.trim();
  const status = document.getElementById('modal_status').value;

  if (!schemaName) {
    alert('Please provide a schema name.');
    return;
  }
  if (!schemaData) {
    alert('Schema JSON-LD payload cannot be empty.');
    return;
  }

  const fd = new FormData();
  fd.append('action', 'save_page_schema');
  fd.append('id', id);
  fd.append('page_key', pageKey);
  fd.append('page_title', pageTitle);
  fd.append('schema_name', schemaName);
  fd.append('schema_type', schemaType);
  fd.append('schema_data', schemaData);
  fd.append('status', status);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        closeSchemaModal();
        window.location.reload();
      } else {
        alert('Save Failed: ' + data.message);
      }
    })
    .catch(err => alert('Save Request Error: ' + err.message));
}

// Toggle Publish Status
function toggleSchemaPublish(id, currentStatus) {
  const newStatus = (currentStatus === 'published') ? 'draft' : 'published';
  const fd = new FormData();
  fd.append('action', 'toggle_page_schema_status');
  fd.append('id', id);
  fd.append('status', newStatus);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        alert('Update Failed: ' + data.message);
      }
    })
    .catch(err => alert('Request Error: ' + err.message));
}

// Delete Schema
function deleteSchema(id, schemaName) {
  if (!confirm('Are you sure you want to delete schema "' + schemaName + '"?')) {
    return;
  }

  const fd = new FormData();
  fd.append('action', 'delete_page_schema');
  fd.append('id', id);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        alert('Delete Failed: ' + data.message);
      }
    })
    .catch(err => alert('Request Error: ' + err.message));
}

// Auto-Generate Single Schema
function autoGenerateSingleSchema(pageKey, schemaType) {
  const fd = new FormData();
  fd.append('action', 'auto_generate_single_schema');
  fd.append('page_key', pageKey);
  fd.append('schema_type', schemaType);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        window.location.reload();
      } else {
        alert('Auto-generation failed: ' + data.message);
      }
    })
    .catch(err => alert('Request Error: ' + err.message));
}

// Open Schema Editor for a specific pageKey and schemaType (loads existing or initializes template)
function openSchemaEditor(pageKey, schemaId, schemaType) {
  if (schemaId && schemaId > 0) {
    editSchemaById(schemaId);
  } else {
    const fd = new FormData();
    fd.append('action', 'get_or_init_blog_schema');
    fd.append('page_key', pageKey);
    fd.append('schema_type', schemaType);
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('ajax', '1');

    fetch(ADMIN_API, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.schema) {
          editExistingSchema(data.schema);
        } else {
          alert('Could not open schema editor: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(err => alert('Request Error: ' + err.message));
  }
}

// Auto-generate Organization + BreadcrumbList + BlogPosting for a single blog
function autoGenerateSingleBlogSchemas(pageKey, blogTitle) {
  if (!confirm('Auto-generate Organization, BreadcrumbList, and BlogPosting schemas for:\n"' + blogTitle + '"?\n\nThis will overwrite any existing schemas for this page key.')) return;
  const fd = new FormData();
  fd.append('action', 'auto_generate_blog_schemas');
  fd.append('page_key', pageKey);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');
  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.success ? '✓ ' + data.message : '✗ ' + data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => alert('Request Error: ' + err.message));
}

// Auto-generate schemas for ALL published blogs
function autoGenerateAllBlogSchemas() {
  if (!confirm('Auto-generate Organization, BreadcrumbList, and BlogPosting schemas for ALL published blogs?\n\nThis will overwrite existing schemas for all blog page keys.')) return;
  const btn = event.currentTarget;
  btn.disabled = true;
  btn.textContent = 'Generating…';
  const fd = new FormData();
  fd.append('action', 'auto_generate_all_blog_schemas');
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');
  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg> Auto-Generate All Blog Schemas';
      alert(data.success ? '✓ ' + data.message : '✗ ' + data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => { btn.disabled = false; alert('Request Error: ' + err.message); });
}

// Sync blogs.md now
function syncBlogsMdNow() {
  const btn = event.currentTarget;
  btn.disabled = true;
  btn.textContent = 'Syncing…';
  const fd = new FormData();
  fd.append('action', 'sync_blogs_md');
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');
  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg> Sync blogs.md Now';
      alert(data.success ? '✓ ' + data.message : '✗ ' + data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => { btn.disabled = false; alert('Request Error: ' + err.message); });
}

// Preview Schema by ID
function previewSchemaById(id) {
  const fd = new FormData();
  fd.append('action', 'get_schema_preview');
  fd.append('id', id);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.schema) {
        document.getElementById('quickPreviewTitle').innerText = data.schema.schema_name;
        document.getElementById('quickPreviewType').innerText = '@type: ' + data.schema.schema_type + ' (' + data.schema.page_key + ')';
        document.getElementById('quickPreviewContent').textContent = data.schema.schema_data;
        document.getElementById('quickPreviewModal').classList.add('open');
      }
    });
}

function previewBlogPublicSchema(canonicalUrl) {
  const slug = canonicalUrl.split('/blog/')[1] || '';
  if (!slug) return;

  document.getElementById('quickPreviewTitle').innerText = 'Blog Schema: /blog/' + slug;
  document.getElementById('quickPreviewType').innerText = 'Schema.org @graph (Live Public Blog Header)';
  document.getElementById('quickPreviewContent').textContent = 'Fetching live rendered schema...';
  document.getElementById('quickPreviewModal').classList.add('open');

  fetch('<?= SITE_URL ?>/blog/' + encodeURIComponent(slug))
    .then(r => r.text())
    .then(html => {
      const match = html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/i);
      if (match && match[1]) {
        try {
          const parsed = JSON.parse(match[1]);
          document.getElementById('quickPreviewContent').textContent = JSON.stringify(parsed, null, 2);
        } catch (e) {
          document.getElementById('quickPreviewContent').textContent = match[1].trim();
        }
      } else {
        document.getElementById('quickPreviewContent').textContent = 'No JSON-LD script found in rendered blog HTML.';
      }
    })
    .catch(err => {
      document.getElementById('quickPreviewContent').textContent = 'Error fetching blog HTML: ' + err.message;
    });
}

function closeQuickPreviewModal() {
  document.getElementById('quickPreviewModal').classList.remove('open');
}

function copyQuickPreviewCode() {
  const text = document.getElementById('quickPreviewContent').textContent;
  navigator.clipboard.writeText(text).then(() => {
    alert('Schema JSON-LD copied to clipboard!');
  });
}

// Run Full Website Audit
function runWebsiteAudit() {
  const fd = new FormData();
  fd.append('action', 'run_website_audit');
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.audit) {
        const m = data.audit.metrics;
        document.getElementById('metric_total_pages').innerText = m.total_pages;
        document.getElementById('metric_existing_schemas').innerText = m.existing_schemas;
        document.getElementById('metric_valid_schemas').innerText = m.valid_schemas;
        document.getElementById('metric_missing_schemas').innerText = m.missing_schemas;
        document.getElementById('metric_invalid_schemas').innerText = m.invalid_schemas;
        document.getElementById('metric_duplicate_schemas').innerText = m.duplicate_schemas;
        document.getElementById('metric_published_schemas').innerText = m.published_schemas;
        document.getElementById('metric_draft_schemas').innerText = m.draft_schemas;

        // Re-render audit table dynamically
        const tbody = document.getElementById('auditReportTableBody');
        if (tbody && data.audit.pages) {
          let rowsHtml = '';
          Object.values(data.audit.pages).forEach(p => {
            const hasMissing = p.missing_types && p.missing_types.length > 0;
            const hasErrors = p.errors && p.errors.length > 0;
            const statusClass = hasMissing ? 'draft' : (hasErrors ? 'disabled' : 'published');
            const statusText = hasMissing ? 'Missing Recommended' : (hasErrors ? 'Requires Fix' : 'Already Correct');

            let cfgBadges = '';
            Object.keys(p.configured_schemas || {}).forEach(t => {
              cfgBadges += `<span class="badge" style="background:rgba(54,217,176,0.15); color:var(--accent-mint); font-size:0.75rem;">${t}</span> `;
            });

            let missHtml = (hasMissing) 
              ? p.missing_types.map(m => `<span class="badge" style="background:rgba(255,107,107,0.15); color:var(--danger); font-size:0.75rem;">Missing: ${m}</span>`).join(' ')
              : '<span style="font-size:0.8rem; color:var(--text-muted);">&#10003; Complete</span>';

            rowsHtml += `<tr>
              <td>
                <div style="font-weight:700; color:#fff;">${p.title}</div>
                <div style="font-size:0.75rem; color:var(--text-muted); font-family:var(--font-mono);">${p.canonical_url}</div>
              </td>
              <td><span class="badge" style="background:var(--bg-surface-elevated); border:1px solid var(--border-sage);">${p.category}</span></td>
              <td><div style="display:flex; gap:0.35rem; flex-wrap:wrap;">${cfgBadges}</div></td>
              <td>${missHtml}</td>
              <td><span class="sm-status-pill ${statusClass}">${statusText}</span></td>
            </tr>`;
          });
          tbody.innerHTML = rowsHtml;
        }

        alert(`Full Website Audit Complete:\n• Discovered Pages: ${m.total_pages}\n• Existing Schemas: ${m.existing_schemas}\n• Valid: ${m.valid_schemas}\n• Missing: ${m.missing_schemas}\n• Duplicates: ${m.duplicate_schemas}`);
      }
    })
    .catch(err => alert('Audit Request Error: ' + err.message));
}

// Auto-Create All Missing Schemas
function autoCreateAllMissing() {
  if (!confirm('Auto-generate, validate, and publish all applicable missing schemas across all discovered pages?')) {
    return;
  }

  const fd = new FormData();
  fd.append('action', 'auto_create_missing');
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        window.location.reload();
      } else {
        alert('Auto-generation reported errors: ' + (data.details.errors ? data.details.errors.join('; ') : data.message));
      }
    })
    .catch(err => alert('Request Error: ' + err.message));
}

// Audit Report Modal
function openAuditReportModal() {
  document.getElementById('auditReportModal').classList.add('open');
}

function closeAuditReportModal() {
  document.getElementById('auditReportModal').classList.remove('open');
}

// Global Blog Template handlers
function insertVariableToTemplate(varName) {
  const textarea = document.getElementById('global_blog_template');
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const text = textarea.value;
  textarea.value = text.substring(0, start) + varName + text.substring(end);
  textarea.selectionStart = textarea.selectionEnd = start + varName.length;
  textarea.focus();
  updateGlobalBlogLivePreview(textarea.value);
}

function formatGlobalBlogTemplate() {
  const ta = document.getElementById('global_blog_template');
  try {
    const parsed = JSON.parse(ta.value);
    ta.value = JSON.stringify(parsed, null, 2);
    updateGlobalBlogLivePreview(ta.value);
  } catch (err) {
    alert('Cannot format invalid JSON: ' + err.message);
  }
}

function resetGlobalBlogTemplate() {
  if (!confirm('Reset global template to standard default Schema.org BlogPosting?')) return;
  fetchTemplateForModal('BlogPosting');
}

const globalBlogTextarea = document.getElementById('global_blog_template');
if (globalBlogTextarea) {
  globalBlogTextarea.addEventListener('input', function() {
    updateGlobalBlogLivePreview(this.value);
  });
}

function updateGlobalBlogLivePreview(val) {
  document.getElementById('global_template_preview').textContent = val;
  const badge = document.getElementById('global_template_val_badge');
  const box = document.getElementById('global_template_val_box');

  const fd = new FormData();
  fd.append('action', 'validate_schema');
  fd.append('schema_data', val);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.valid) {
        badge.style.background = 'rgba(54, 217, 176, 0.15)';
        badge.style.color = 'var(--accent-mint)';
        badge.textContent = 'Valid';
        box.innerHTML = '<div class="sm-val-valid">Template syntax is valid.</div>';
      } else {
        badge.style.background = 'rgba(255, 107, 107, 0.15)';
        badge.style.color = 'var(--danger)';
        badge.textContent = 'Errors Found';
        box.innerHTML = res.errors.map(e => `<div class="sm-val-error">&bull; ${e}</div>`).join('');
      }
    });
}

function saveGlobalBlogSettings() {
  const enabled = document.getElementById('auto_blog_schema_enabled').checked ? '1' : '0';
  const template = document.getElementById('global_blog_template').value;
  const status = document.getElementById('global_template_status').value;

  const fd = new FormData();
  fd.append('action', 'save_global_blog_settings');
  fd.append('auto_blog_schema_enabled', enabled);
  fd.append('global_blog_template', template);
  fd.append('global_template_status', status);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        window.location.reload();
      } else {
        alert('Save Failed: ' + data.message);
      }
    });
}

// Individual Blog Overrides handlers
function debounceBlogSearch() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(performBlogSearch, 250);
}

function performBlogSearch() {
  const q = document.getElementById('blogSearchInput').value.trim();
  const status = document.getElementById('blogStatusFilter').value;
  const mode = document.getElementById('blogModeFilter').value;

  const fd = new FormData();
  fd.append('action', 'search_blogs');
  fd.append('q', q);
  fd.append('status', status);
  fd.append('mode', mode);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        renderBlogTable(data.blogs);
      }
    });
}

function renderBlogTable(blogs) {
  const tbody = document.getElementById('blogTableBody');
  const countBadge = document.getElementById('blogCountBadge');
  
  if (countBadge) {
    countBadge.innerText = `${blogs.length} Articles`;
  }

  if (!blogs || blogs.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-muted);">No matching blog articles found.</td></tr>`;
    return;
  }

  tbody.innerHTML = blogs.map(b => {
    const isOverridden = (parseInt(b.override_enabled) === 1);
    const isDisabled = (parseInt(b.schema_disabled) === 1);
    const isPubPost = (b.status === 'published');

    let modeHtml = `<span class="sm-status-pill" style="background:rgba(0,191,165,0.15); color:var(--accent-teal); border:1px solid rgba(0,191,165,0.3);"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg> Global Template</span>`;
    if (isDisabled) {
      modeHtml = `<span class="sm-status-pill missing" style="background:rgba(255,107,107,0.15); color:var(--danger); border:1px solid rgba(255,107,107,0.3);"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg> Schema Disabled</span>`;
    } else if (isOverridden) {
      modeHtml = `<span class="sm-status-pill published"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Custom Override</span>`;
    }

    return `
      <tr id="blog_tr_${b.id}">
        <td>
          <div class="sm-blog-row-title">${escapeHtml(b.title)}</div>
          <div class="sm-blog-row-slug">/blog/${escapeHtml(b.slug)}</div>
        </td>
        <td>
          <span class="badge" style="background:var(--bg-surface-elevated); border:1px solid var(--border-sage);">
            ${escapeHtml(b.category_name || 'Uncategorized')}
          </span>
        </td>
        <td>
          <span class="sm-status-pill ${isPubPost ? 'published' : 'draft'}">
            ${isPubPost ? 'Published' : 'Draft'}
          </span>
        </td>
        <td>
          ${modeHtml}
        </td>
        <td style="text-align:right;">
          <button type="button" class="btn btn-sm btn-outline-mint" onclick="openBlogOverrideModal(${b.id})">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            Configure
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

// Open Blog Override Modal
function openBlogOverrideModal(postId) {
  const fd = new FormData();
  fd.append('action', 'get_blog_schema_data');
  fd.append('post_id', postId);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => {
      if (!r.ok) {
        throw new Error('Server returned HTTP ' + r.status);
      }
      return r.json();
    })
    .then(data => {
      if (data.success) {
        currentActiveBlogData = data;
        populateBlogOverrideModal(data);
      } else {
        alert('Error loading blog schema: ' + data.message);
      }
    })
    .catch(err => alert('Request Error: ' + err.message));
}

function populateBlogOverrideModal(data) {
  document.getElementById('override_modal_post_id').value = data.post.id;
  document.getElementById('overrideModalBlogTitle').innerText = 'Configure Schema: ' + data.post.title;
  document.getElementById('overrideModalBlogSlug').innerText = '/blog/' + data.post.slug;
  document.getElementById('overrideModalCategory').innerText = data.post.category_name || 'Uncategorized';
  
  const isPub = (data.post.status === 'published');
  const postStPill = document.getElementById('overrideModalPostStatus');
  postStPill.innerText = isPub ? 'Published' : 'Draft';
  postStPill.className = 'sm-status-pill ' + (isPub ? 'published' : 'draft');

  document.getElementById('overrideModalDraftAlert').style.display = isPub ? 'none' : 'block';

  document.getElementById('modal_inherited_preview').textContent = data.compiled_default || '{\n  "@context": "https://schema.org",\n  "@type": "BlogPosting"\n}';

  const o = data.override;
  let mode = 'inherit';
  if (parseInt(o.schema_disabled) === 1) {
    mode = 'disabled';
  } else if (parseInt(o.override_enabled) === 1) {
    mode = 'custom';
  }
  currentOverrideOriginalMode = mode;

  const existingCustomText = o.custom_schema || '';
  currentOverrideOriginalSchema = existingCustomText;
  document.getElementById('modal_custom_blog_schema').value = existingCustomText;
  document.getElementById('modal_custom_status').value = o.status || 'published';

  const restoreBtn = document.getElementById('modalRestoreDefaultBtn');
  if (restoreBtn) {
    restoreBtn.style.display = (mode !== 'inherit') ? 'inline-flex' : 'none';
  }

  selectOverrideMode(mode);

  if (existingCustomText) {
    updateModalCustomPreview(existingCustomText);
  }

  const modal = document.getElementById('blogOverrideModal');
  modal.classList.add('open');
}

function selectOverrideMode(mode) {
  const r = document.getElementById('radio_mode_' + mode);
  if (r) r.checked = true;

  document.querySelectorAll('.sm-mode-card').forEach(c => c.classList.remove('active'));
  const card = document.getElementById('modeCard_' + mode);
  if (card) card.classList.add('active');

  document.getElementById('overrideSection_inherit').style.display = (mode === 'inherit') ? 'block' : 'none';
  document.getElementById('overrideSection_custom').style.display = (mode === 'custom') ? 'block' : 'none';
  document.getElementById('overrideSection_disabled').style.display = (mode === 'disabled') ? 'block' : 'none';
}

function closeBlogOverrideModal() {
  const modal = document.getElementById('blogOverrideModal');
  modal.classList.remove('open');
}

const customModalTextarea = document.getElementById('modal_custom_blog_schema');
if (customModalTextarea) {
  customModalTextarea.addEventListener('input', function() {
    updateModalCustomPreview(this.value);
  });
  customModalTextarea.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
      e.preventDefault();
      const start = this.selectionStart;
      const end = this.selectionEnd;
      this.value = this.value.substring(0, start) + '  ' + this.value.substring(end);
      this.selectionStart = this.selectionEnd = start + 2;
    }
  });
}

function updateModalCustomPreview(val) {
  document.getElementById('modal_custom_preview').textContent = val;
  const badge = document.getElementById('modal_custom_val_badge');
  const box = document.getElementById('modal_custom_val_box');

  if (!val.trim()) {
    badge.textContent = 'Empty';
    badge.style.background = 'rgba(229, 185, 90, 0.15)';
    badge.style.color = 'var(--status-draft)';
    box.innerHTML = '<div class="sm-val-warning">&bull; Schema editor is empty.</div>';
    return;
  }

  const fd = new FormData();
  fd.append('action', 'validate_schema');
  fd.append('schema_data', val);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.valid) {
        badge.style.background = 'rgba(54, 217, 176, 0.15)';
        badge.style.color = 'var(--accent-mint)';
        badge.textContent = 'Valid';

        let warnHtml = '';
        if (res.warnings && res.warnings.length > 0) {
          warnHtml = res.warnings.map(w => `<div class="sm-val-warning">&bull; Warning: ${w}</div>`).join('');
        }
        box.innerHTML = `<div class="sm-val-valid"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Valid Schema.org BlogPosting</div>${warnHtml}`;
      } else {
        badge.style.background = 'rgba(255, 107, 107, 0.15)';
        badge.style.color = 'var(--danger)';
        badge.textContent = 'Errors';
        box.innerHTML = res.errors.map(e => `<div class="sm-val-error">&bull; ${e}</div>`).join('');
      }
    })
    .catch(() => {
      try {
        JSON.parse(val);
        badge.textContent = 'Valid JSON';
        box.innerHTML = '<div class="sm-val-valid">JSON is syntactically valid.</div>';
      } catch (e) {
        badge.textContent = 'Syntax Error';
        box.innerHTML = `<div class="sm-val-error">${e.message}</div>`;
      }
    });
}

function copyCompiledToCustomEditor() {
  if (currentActiveBlogData && currentActiveBlogData.compiled_default) {
    document.getElementById('modal_custom_blog_schema').value = currentActiveBlogData.compiled_default;
    selectOverrideMode('custom');
    updateModalCustomPreview(currentActiveBlogData.compiled_default);
  }
}

function loadBaselineIntoCustomModal() {
  if (currentActiveBlogData && currentActiveBlogData.compiled_default) {
    if (confirm('Load the auto-compiled global template into the editor? This will replace current text.')) {
      document.getElementById('modal_custom_blog_schema').value = currentActiveBlogData.compiled_default;
      updateModalCustomPreview(currentActiveBlogData.compiled_default);
    }
  }
}

function formatOverrideModalJson() {
  const ta = document.getElementById('modal_custom_blog_schema');
  try {
    const parsed = JSON.parse(ta.value);
    ta.value = JSON.stringify(parsed, null, 2);
    updateModalCustomPreview(ta.value);
  } catch (err) {
    alert('Cannot format invalid JSON: ' + err.message);
  }
}

function revertOverrideModalJson() {
  if (confirm('Revert editor to previously saved schema?')) {
    document.getElementById('modal_custom_blog_schema').value = currentOverrideOriginalSchema;
    updateModalCustomPreview(currentOverrideOriginalSchema);
  }
}

function submitBlogOverrideSave() {
  const postId = document.getElementById('override_modal_post_id').value;
  const mode = document.querySelector('input[name="modal_override_mode"]:checked').value;
  const customSchema = document.getElementById('modal_custom_blog_schema').value.trim();
  const status = document.getElementById('modal_custom_status').value;

  if (mode === 'custom' && !customSchema) {
    alert('Please enter or paste your custom JSON-LD schema, or switch to Inherit Global Template.');
    return;
  }

  const fd = new FormData();
  fd.append('action', 'save_blog_override');
  fd.append('post_id', postId);
  fd.append('mode', mode);
  fd.append('custom_schema', customSchema);
  fd.append('status', status);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        closeBlogOverrideModal();
        performBlogSearch();
      } else {
        alert('Save Failed: ' + data.message);
      }
    })
    .catch(err => alert('Save Request Error: ' + err.message));
}

function restoreCurrentBlogDefault() {
  const postId = document.getElementById('override_modal_post_id').value;
  if (!confirm('Revert this blog post to inherit the global BlogPosting schema template?')) {
    return;
  }

  const fd = new FormData();
  fd.append('action', 'restore_blog_default');
  fd.append('post_id', postId);
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('ajax', '1');

  fetch(ADMIN_API, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        closeBlogOverrideModal();
        performBlogSearch();
      } else {
        alert('Restore Failed: ' + data.message);
      }
    })
    .catch(err => alert('Request Error: ' + err.message));
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
</script>

<?php
// Include Admin CMS Footer
require_once CMS_ROOT . '/admin/includes/footer.php';