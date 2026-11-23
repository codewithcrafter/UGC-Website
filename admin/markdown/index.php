<?php
/**
 * MY UGC VIDEO — Markdown & LLMs Management Center
 * File manager for public AI directives, markdown knowledge bases, schemas, and automatic LLM synchronizations.
 * Premium Dark Editorial CMS
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(dirname(__DIR__)));
}
require_once CMS_ROOT . '/config/config.php';
requireAuth();
if (file_exists(CMS_ROOT . '/includes/website-markdown-sync.php')) {
    require_once CMS_ROOT . '/includes/website-markdown-sync.php';
}
if (file_exists(CMS_ROOT . '/includes/llms-sync.php')) {
    require_once CMS_ROOT . '/includes/llms-sync.php';
}

$activeNav = 'markdown';
$pageTitle = 'Markdown & LLMs';

$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard'],
    ['name' => 'Markdown & LLMs', 'url' => ADMIN_URL . '/markdown']
];

$db = getDb();

// Helper to list all managed files (Directives, Markdown Docs, Approved Schemas)
function getManagedContentFiles(PDO $db): array {
    $files = [];

    // 1. Root llms.txt if exists
    $llmsPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'llms.txt';
    if (file_exists($llmsPath)) {
        $files[] = [
            'key'           => 'llms.txt',
            'filename'      => 'llms.txt',
            'rel_path'      => 'llms.txt',
            'abs_path'      => $llmsPath,
            'category'      => 'llm',
            'category_name' => 'LLM Directives',
            'file_type'     => 'txt',
            'size'          => filesize($llmsPath),
            'mtime'         => filemtime($llmsPath),
            'is_blog'       => false,
            'blog_id'       => null,
            'blog_status'   => null,
            'blog_title'    => null,
            'is_deletable'  => false
        ];
    }

    // 2. Fetch all blog posts from DB for metadata matching
    $blogMap = [];
    try {
        $blogStmt = $db->query("SELECT id, title, slug, status, is_featured, published_at FROM blog_posts");
        while ($row = $blogStmt->fetch()) {
            $blogMap[(int) $row['id']] = $row;
        }
    } catch (Throwable $e) {}

    // 3. Scan subdirectories in MARKDOWN_DIR (blogs, pages, services, other)
    $categories = [
        'blogs'        => ['dir' => 'blogs', 'name' => 'Blogs', 'icon' => 'blog'],
        'pages'        => ['dir' => 'pages', 'name' => 'Pages', 'icon' => 'page'],
        'services'     => ['dir' => 'services', 'name' => 'Services', 'icon' => 'service'],
        'other'        => ['dir' => 'other', 'name' => 'Other', 'icon' => 'file']
    ];

    foreach ($categories as $catKey => $catInfo) {
        $dirPath = MARKDOWN_DIR . DIRECTORY_SEPARATOR . $catInfo['dir'];
        if (!is_dir($dirPath)) continue;

        $items = scandir($dirPath);
        if (!$items) continue;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $dirPath . DIRECTORY_SEPARATOR . $item;
            if (!is_file($fullPath)) continue;

            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if ($ext !== 'md' && $ext !== 'txt') continue;

            $relPath = $catInfo['dir'] . '/' . $item;
            $isBlog = ($catKey === 'blogs');
            $blogId = null;
            $blogStatus = null;
            $blogTitle = null;

            if ($isBlog && preg_match('/^post-(\d+)-/i', $item, $matches)) {
                $blogId = (int) $matches[1];
                if (isset($blogMap[$blogId])) {
                    $blogStatus = $blogMap[$blogId]['status'];
                    $blogTitle = $blogMap[$blogId]['title'];
                }
            }

            $files[] = [
                'key'           => $relPath,
                'filename'      => $item,
                'rel_path'      => 'markdown/' . $relPath,
                'abs_path'      => $fullPath,
                'category'      => $catKey,
                'category_name' => $catInfo['name'],
                'file_type'     => $ext,
                'size'          => filesize($fullPath),
                'mtime'         => filemtime($fullPath),
                'is_blog'       => $isBlog,
                'blog_id'       => $blogId,
                'blog_status'   => $blogStatus,
                'blog_title'    => $blogTitle,
                'is_deletable'  => true
            ];
        }
    }

    // Sort files by modified time descending (llms.txt always top)
    usort($files, function($a, $b) {
        if ($a['key'] === 'llms.txt') return -1;
        if ($b['key'] === 'llms.txt') return 1;
        return $b['mtime'] <=> $a['mtime'];
    });

    return $files;
}

// Security function to validate file key and resolve absolute path
function resolveSafeContentPath(string $key): ?string {
    $key = trim($key);
    if (empty($key)) return null;

    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $key);

    if ($normalized === 'llms.txt' || basename($normalized) === 'llms.txt') {
        $p = CMS_ROOT . DIRECTORY_SEPARATOR . 'llms.txt';
        return file_exists($p) ? realpath($p) : null;
    }

    // Direct match inside schemas/
    if (str_starts_with($normalized, 'schemas' . DIRECTORY_SEPARATOR)) {
        $target = CMS_ROOT . DIRECTORY_SEPARATOR . $normalized;
        $realTarget = realpath($target);
        $realSchemasDir = realpath(CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas');
        if ($realTarget && $realSchemasDir && str_starts_with($realTarget, $realSchemasDir)) {
            return $realTarget;
        }
    }

    // Direct match inside uploads/schemas/
    if (str_starts_with($normalized, 'uploads' . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR)) {
        $target = CMS_ROOT . DIRECTORY_SEPARATOR . $normalized;
        $realTarget = realpath($target);
        $realUploadsDir = realpath(CMS_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'schemas');
        if ($realTarget && $realUploadsDir && str_starts_with($realTarget, $realUploadsDir)) {
            return $realTarget;
        }
    }

    // Match inside MARKDOWN_DIR
    $cleanRel = $normalized;
    if (str_starts_with($cleanRel, 'markdown' . DIRECTORY_SEPARATOR)) {
        $cleanRel = substr($cleanRel, strlen('markdown' . DIRECTORY_SEPARATOR));
    }

    $target = MARKDOWN_DIR . DIRECTORY_SEPARATOR . ltrim($cleanRel, DIRECTORY_SEPARATOR);
    $realTarget = realpath($target);
    $realMarkdownDir = realpath(MARKDOWN_DIR);

    if ($realTarget && $realMarkdownDir && str_starts_with($realTarget, $realMarkdownDir)) {
        return $realTarget;
    }

    return null;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'reconcile_all_website_to_markdown') {
        if (class_exists('WebsiteMarkdownSync')) {
            $res = WebsiteMarkdownSync::reconcileAll(true);
            if (function_exists('triggerLlmsSynchronization')) {
                triggerLlmsSynchronization('reconcile_all_website_to_markdown');
            }
            $errCount = count($res['errors'] ?? []);
            if ($errCount === 0) {
                setFlash('success', "Website-to-Markdown Sync complete: {$res['synced']} updated, {$res['unchanged']} up-to-date across {$res['total']} website pages.");
            } else {
                setFlash('warning', "Website-to-Markdown Sync finished with {$errCount} warning(s). {$res['synced']} pages updated.");
            }
        } else {
            setFlash('error', 'WebsiteMarkdownSync handler unavailable.');
        }
        header('Location: ' . ADMIN_URL . '/markdown');
        exit;
    }

    if ($action === 'sync_website_page') {
        $pageKey = trim($_POST['page_key'] ?? '');
        if (class_exists('WebsiteMarkdownSync') && !empty($pageKey)) {
            $res = WebsiteMarkdownSync::syncHtmlPage($pageKey, true);
            if ($res['success']) {
                if (function_exists('triggerLlmsSynchronization')) {
                    triggerLlmsSynchronization('sync_website_page_' . $pageKey);
                }
                setFlash('success', "Page '{$pageKey}' synchronized successfully: " . ($res['changed'] ? 'Markdown file updated.' : 'Already in sync with source HTML.'));
            } else {
                setFlash('error', "Failed to sync page '{$pageKey}': " . ($res['error'] ?? 'Unknown error'));
            }
        } else {
            setFlash('error', 'Invalid page key or sync handler unavailable.');
        }
        header('Location: ' . ADMIN_URL . '/markdown');
        exit;
    }

    if ($action === 'reconcile_blogs') {
        if (function_exists('reconcileAllBlogsToMarkdown')) {
            $res = reconcileAllBlogsToMarkdown();
            if (function_exists('triggerLlmsSynchronization')) {
                triggerLlmsSynchronization('reconcile_blogs');
            }
            setFlash('success', "Reconciled {$res['synced']} of {$res['total']} blog posts to markdown successfully.");
        } else {
            setFlash('error', 'Reconciliation handler unavailable.');
        }
        header('Location: ' . ADMIN_URL . '/markdown');
        exit;
    }

    if ($action === 'rebuild_all') {
        if (function_exists('runFullReconciliation')) {
            $res = runFullReconciliation(['force' => true]);
            setFlash('success', "Rebuilt llms.txt ({$res['llms_count']} entries) and sitemap.xml ({$res['sitemap_count']} URLs). IndexNow and crawler endpoints updated.");
        } else {
            setFlash('error', 'Synchronization handler unavailable.');
        }
        header('Location: ' . ADMIN_URL . '/markdown');
        exit;
    }

    if ($action === 'save_file') {
        $fileKey = trim($_POST['file_key'] ?? '');
        $content = $_POST['content'] ?? '';
        $syncToBlog = !empty($_POST['sync_to_blog']);

        $safePath = resolveSafeContentPath($fileKey);
        if (!$safePath || !is_file($safePath)) {
            setFlash('error', 'File not found or access denied.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        // Validate JSON syntax if JSON file
        $ext = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                setFlash('error', 'Invalid JSON syntax: ' . json_last_error_msg());
                header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
                exit;
            }
        }

        // Validate SQL privacy if SQL file
        if ($ext === 'sql') {
            $sensitiveWords = ['admin_users', 'admins', 'password_hash', 'user_pass', 'users', 'auth_tokens', 'sessions'];
            foreach ($sensitiveWords as $w) {
                if (stripos($content, $w) !== false) {
                    setFlash('error', 'Security rejected: SQL file contains protected identifier "' . htmlspecialchars($w) . '".');
                    header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
                    exit;
                }
            }
        }

        if (file_put_contents($safePath, $content) === false) {
            setFlash('error', 'Failed to save file. Check directory permissions.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
            exit;
        }

        $syncedBlogMsg = '';
        if ($syncToBlog && preg_match('/post-(\d+)-/i', basename($safePath), $m)) {
            $postId = (int) $m[1];
            $body = $content;
            $newTitle = null;
            $newExcerpt = null;

            if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $fmMatches)) {
                $fmBlock = $fmMatches[1];
                $body = trim(substr($content, strlen($fmMatches[0])));
                if (preg_match('/^title:\s*(.+)$/m', $fmBlock, $tMatch)) {
                    $newTitle = trim($tMatch[1], " \t\n\r\0\x0B\"'");
                }
                if (preg_match('/^description:\s*(.+)$/m', $fmBlock, $dMatch)) {
                    $newExcerpt = trim($dMatch[1], " \t\n\r\0\x0B\"'");
                }
            }

            if (!$newTitle && preg_match('/^#\s+(.+)$/m', $body, $hMatch)) {
                $newTitle = trim($hMatch[1]);
            }

            $updateFields = ["content = :content", "updated_at = NOW()"];
            $params = [':content' => $body, ':id' => $postId];

            if (!empty($newTitle)) {
                $updateFields[] = "title = :title";
                $params[':title'] = $newTitle;
            }
            if (!empty($newExcerpt)) {
                $updateFields[] = "excerpt = :excerpt";
                $params[':excerpt'] = $newExcerpt;
            }

            $upStmt = $db->prepare("UPDATE blog_posts SET " . implode(', ', $updateFields) . " WHERE id = :id");
            $upStmt->execute($params);
            $syncedBlogMsg = ' & synchronized changes to Blog Article #' . $postId;
        }

        if (function_exists('triggerLlmsSynchronization')) {
            triggerLlmsSynchronization('save_file');
        }

        setFlash('success', 'File "' . htmlspecialchars(basename($safePath)) . '" updated successfully' . $syncedBlogMsg . '!');
        header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
        exit;
    }

    if ($action === 'create_file') {
        $cat = trim($_POST['category'] ?? 'other');
        $filename = trim($_POST['filename'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $initialContent = trim($_POST['content'] ?? '');

        if (!in_array($cat, ['blogs', 'pages', 'other', 'llm', 'schemas'])) {
            $cat = 'other';
        }

        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '-', $filename);
        $filename = trim($filename, '-.');

        // Phase 3 Rule: Exclude homepage markdown permanently
        $baseNameOnly = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        if (in_array($baseNameOnly, ['home', 'index', 'homepage'])) {
            setFlash('error', 'Homepage must never have a separate markdown file.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        if ($cat === 'schemas') {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['json', 'sql'])) {
                $filename .= '.json';
            }
            $targetPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . $filename;
            $newKey = 'schemas/' . $filename;
        } elseif ($cat === 'llm') {
            $filename = 'llms.txt';
            $targetPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'llms.txt';
            $newKey = 'llms.txt';
        } else {
            if (!str_ends_with(strtolower($filename), '.md') && !str_ends_with(strtolower($filename), '.txt')) {
                $filename .= '.md';
            }
            $targetPath = MARKDOWN_DIR . DIRECTORY_SEPARATOR . $cat . DIRECTORY_SEPARATOR . $filename;
            $newKey = $cat . '/' . $filename;
        }

        if (empty($filename)) {
            setFlash('error', 'Please provide a valid file name.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        if (file_exists($targetPath)) {
            setFlash('error', 'A file with this name already exists in this category.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        $payload = "";
        if (!empty($title) && str_ends_with($filename, '.md')) {
            $payload .= "# " . $title . "\n\n";
        }
        $payload .= $initialContent ?: ($cat === 'schemas' && str_ends_with($filename, '.json') ? "{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"Dataset\"\n}\n" : "Write your documentation here...\n");

        if (file_put_contents($targetPath, $payload) === false) {
            setFlash('error', 'Failed to create file on disk.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        if (function_exists('triggerLlmsSynchronization')) {
            triggerLlmsSynchronization('create_file');
        }

        setFlash('success', 'File "' . htmlspecialchars($filename) . '" created successfully!');
        header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($newKey));
        exit;
    }

    if ($action === 'upload_file') {
        $cat = trim($_POST['category'] ?? 'other');
        if (!in_array($cat, ['pages', 'other', 'blogs', 'schemas'])) {
            $cat = 'other';
        }

        if (empty($_FILES['md_file']['name']) || $_FILES['md_file']['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'Please select a valid file to upload.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        $origName = basename($_FILES['md_file']['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['md', 'txt', 'markdown', 'json', 'sql'])) {
            setFlash('error', 'Accepted file formats: Markdown (.md, .txt), JSON (.json), and SQL (.sql).');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        $cleanBase = preg_replace('/[^a-zA-Z0-9_\-\.]/', '-', pathinfo($origName, PATHINFO_FILENAME));
        $cleanBase = trim($cleanBase, '-.');

        // Phase 3 Rule: Exclude homepage markdown permanently
        $baseNameOnly = strtolower($cleanBase);
        if (in_array($baseNameOnly, ['home', 'index', 'homepage'])) {
            setFlash('error', 'Homepage must never have a separate markdown file.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        $cleanName = $cleanBase . '.' . ($ext === 'markdown' ? 'md' : $ext);

        if ($cat === 'schemas' || in_array($ext, ['json', 'sql'])) {
            $destDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas';
            $retKey = 'schemas/' . $cleanName;
        } else {
            $destDir = MARKDOWN_DIR . DIRECTORY_SEPARATOR . $cat;
            $retKey = $cat . '/' . $cleanName;
        }

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destPath = $destDir . DIRECTORY_SEPARATOR . $cleanName;
        if (move_uploaded_file($_FILES['md_file']['tmp_name'], $destPath)) {
            if (function_exists('triggerLlmsSynchronization')) {
                triggerLlmsSynchronization('upload_file');
            }
            setFlash('success', 'File "' . htmlspecialchars($cleanName) . '" uploaded successfully!');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($retKey));
            exit;
        } else {
            setFlash('error', 'Failed to move uploaded file.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }
    }

    if ($action === 'rename_file') {
        $fileKey = trim($_POST['file_key'] ?? '');
        $newFilename = trim($_POST['new_filename'] ?? '');

        if ($fileKey === 'llms.txt') {
            setFlash('error', 'llms.txt is a required core directive and cannot be renamed.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=llms.txt');
            exit;
        }

        $safePath = resolveSafeContentPath($fileKey);
        if (!$safePath || !is_file($safePath)) {
            setFlash('error', 'Original file not found or access denied.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        $origExt = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));
        $cleanBase = preg_replace('/[^a-zA-Z0-9_\-\.]/', '-', pathinfo($newFilename, PATHINFO_FILENAME));
        $cleanBase = trim($cleanBase, '-.');
        $newExt = strtolower(pathinfo($newFilename, PATHINFO_EXTENSION)) ?: $origExt;

        if (empty($cleanBase)) {
            setFlash('error', 'Please provide a valid new file name.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
            exit;
        }

        // Phase 3 Rule: Exclude homepage markdown permanently
        $lowerBase = strtolower($cleanBase);
        if (in_array($lowerBase, ['home', 'index', 'homepage'])) {
            setFlash('error', 'Homepage must never have a separate markdown file.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
            exit;
        }

        $newCleanFilename = $cleanBase . '.' . $newExt;
        $destDir = dirname($safePath);
        $destPath = $destDir . DIRECTORY_SEPARATOR . $newCleanFilename;

        if (file_exists($destPath) && realpath($destPath) !== realpath($safePath)) {
            setFlash('error', 'A file named "' . htmlspecialchars($newCleanFilename) . '" already exists.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
            exit;
        }

        if (!rename($safePath, $destPath)) {
            setFlash('error', 'Failed to rename file on disk.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
            exit;
        }

        $catDir = basename($destDir);
        $newKey = ($catDir === 'schemas' || $catDir === 'uploads') ? 'schemas/' . $newCleanFilename : $catDir . '/' . $newCleanFilename;

        if (function_exists('triggerLlmsSynchronization')) {
            triggerLlmsSynchronization('rename_file');
        }

        setFlash('success', 'File renamed to "' . htmlspecialchars($newCleanFilename) . '" successfully.');
        header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($newKey));
        exit;
    }

    if ($action === 'delete_file') {
        $fileKey = trim($_POST['file_key'] ?? '');
        if ($fileKey === 'llms.txt') {
            setFlash('error', 'llms.txt is a required core directive and cannot be deleted.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=llms.txt');
            exit;
        }

        $safePath = resolveSafeContentPath($fileKey);
        if (!$safePath || !is_file($safePath)) {
            setFlash('error', 'File not found or access denied.');
            header('Location: ' . ADMIN_URL . '/markdown');
            exit;
        }

        if (unlink($safePath)) {
            if (function_exists('triggerLlmsSynchronization')) {
                triggerLlmsSynchronization('delete_file');
            }
            setFlash('success', 'File deleted successfully.');
        } else {
            setFlash('error', 'Failed to delete file.');
        }
        header('Location: ' . ADMIN_URL . '/markdown');
        exit;
    }
}

// Load all managed files
$allFiles = getManagedContentFiles($db);

// Determine active file
$activeFileKey = trim($_GET['file'] ?? '');
if (empty($activeFileKey) && !empty($allFiles)) {
    $activeFileKey = $allFiles[0]['key'];
}

$activeFile = null;
$activeContent = '';
if (!empty($activeFileKey)) {
    foreach ($allFiles as $f) {
        if ($f['key'] === $activeFileKey) {
            $activeFile = $f;
            $safePath = resolveSafeContentPath($activeFileKey);
            if ($safePath && file_exists($safePath)) {
                $activeContent = file_get_contents($safePath);
            }
            break;
        }
    }
}

$syncStatus = function_exists('getLlmsSyncStatus') ? getLlmsSyncStatus() : [];
$websiteAuditLog = class_exists('WebsiteMarkdownSync') ? WebsiteMarkdownSync::getSyncAuditLog() : [];
$pageInventory = class_exists('WebsiteMarkdownSync') ? WebsiteMarkdownSync::PAGE_INVENTORY : [];

// Calculate category distribution counts for filter pills
$catCounts = [
    'all'      => count($allFiles),
    'pages'    => 0,
    'services' => 0,
    'blogs'    => 0,
    'llm'      => 0,
    'other'    => 0
];
foreach ($allFiles as $f) {
    $c = $f['category'] ?? 'other';
    if (isset($catCounts[$c])) {
        $catCounts[$c]++;
    } else {
        $catCounts['other']++;
    }
}

$websiteSyncedCount = 0;
foreach ($pageInventory as $pKey => $pInfo) {
    $mdRel = $pInfo['markdown_file'] ?? $pInfo['md_file'] ?? '';
    $pMd = CMS_ROOT . '/' . $mdRel;
    $pAudit = $websiteAuditLog['pages'][$pKey] ?? null;
    if (!empty($mdRel) && file_exists($pMd) && (!empty($pAudit['status']) && $pAudit['status'] !== 'error')) {
        $websiteSyncedCount++;
    }
}

include CMS_ROOT . '/admin/includes/header.php';
?>

<style>
/* Page-Specific Polish for Markdown & LLMs Manager */
.cms-action-bar form {
  margin: 0 !important;
  display: inline-flex;
}

.cms-action-bar .btn {
  height: 34px;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.82rem;
  line-height: 1;
}

/* Enhanced Preview Prose Typography & Spacing */
.md-preview-pane .prose {
  color: var(--text-primary);
  font-size: 0.95rem;
  line-height: 1.75;
  max-width: 860px;
}

.md-preview-pane .prose h1,
.md-preview-pane .prose h2,
.md-preview-pane .prose h3,
.md-preview-pane .prose h4 {
  color: var(--text-white);
  font-family: var(--font-heading);
  margin-top: 1.85rem;
  margin-bottom: 0.85rem;
  line-height: 1.35;
}

.md-preview-pane .prose h1:first-child,
.md-preview-pane .prose h2:first-child {
  margin-top: 0;
}

.md-preview-pane .prose h1 { font-size: 1.85rem; }
.md-preview-pane .prose h2 { font-size: 1.4rem; border-bottom: 1px solid var(--border-sage-subtle); padding-bottom: 0.45rem; }
.md-preview-pane .prose h3 { font-size: 1.15rem; }
.md-preview-pane .prose h4 { font-size: 1rem; }

.md-preview-pane .prose p {
  margin-top: 0;
  margin-bottom: 1.15rem;
  line-height: 1.75;
}

.md-preview-pane .prose ul,
.md-preview-pane .prose ol {
  padding-left: 1.5rem;
  margin-top: 0;
  margin-bottom: 1.15rem;
}

.md-preview-pane .prose li {
  margin-bottom: 0.45rem;
  line-height: 1.65;
}

.md-preview-pane .prose blockquote {
  border-left: 3px solid var(--accent-mint);
  padding: 0.75rem 1.25rem;
  background: rgba(54, 217, 176, 0.05);
  margin: 1.35rem 0;
  border-radius: 0 var(--radius-xs) var(--radius-xs) 0;
  color: var(--text-secondary);
}

.md-preview-pane .prose blockquote p:last-child {
  margin-bottom: 0;
}

.md-preview-pane .prose code {
  background: #00120f;
  border: 1px solid var(--border-sage-subtle);
  padding: 0.15rem 0.45rem;
  border-radius: 3px;
  font-family: var(--font-mono);
  font-size: 0.85em;
  color: var(--accent-mint);
}

.md-preview-pane .prose pre {
  background: #000c0a;
  border: 1px solid var(--border-sage-subtle);
  border-radius: var(--radius-xs);
  padding: 1.25rem;
  overflow-x: auto;
  margin: 1.25rem 0;
}

.md-preview-pane .prose pre code {
  background: transparent;
  border: none;
  padding: 0;
  color: #D9E5DF;
  font-size: 0.88rem;
  line-height: 1.6;
}
</style>

<!-- Header Actions Bar -->
<div class="cms-action-bar">
  <div>
    <h1 class="cms-page-heading" style="margin: 0; font-size: 1.55rem; font-family: var(--font-heading); color: var(--text-white);">
      Markdown &amp; LLMs Manager
    </h1>
    <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.2rem;">
      Public LLM knowledge directives, markdown content bases, schemas, and bi-directional synchronization.
    </div>
  </div>

  <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
    <!-- Rebuild All Discovery -->
    <form method="POST" action="<?= ADMIN_URL ?>/markdown" style="margin: 0;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="rebuild_all">
      <button type="submit" class="btn btn-outline-mint btn-sm" title="Rebuild llms.txt, sitemap.xml and trigger crawler endpoints">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        <span>Rebuild Discovery</span>
      </button>
    </form>

    <!-- Reconcile Utility -->
    <form method="POST" action="<?= ADMIN_URL ?>/markdown" style="margin: 0;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reconcile_blogs">
      <button type="submit" class="btn btn-secondary btn-sm" title="Reconcile all database blog posts into managed Markdown files" onclick="return confirm('Reconcile all database blog posts into managed Markdown files?')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
        <span>Sync All Blogs</span>
      </button>
    </form>

    <!-- Sync All Website Pages -->
    <form method="POST" action="<?= ADMIN_URL ?>/markdown" style="margin: 0;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reconcile_all_website_to_markdown">
      <button type="submit" class="btn btn-secondary btn-sm" title="Reconcile all 13 published website pages to Markdown files" onclick="return confirm('Reconcile all 13 published website pages to Markdown files?')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <span>Sync Website Pages</span>
      </button>
    </form>

    <!-- Upload File Button -->
    <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('uploadModal')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <span>Upload File</span>
    </button>

    <!-- New File Button -->
    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('createModal')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>New File</span>
    </button>
  </div>
</div>

<?php if (!empty($syncStatus)): ?>
<!-- Discovery Status Summary Strip -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 0.65rem 1.15rem; background: #001613; border: 1px solid var(--border-sage); border-radius: var(--radius-sm); margin-bottom: 1.25rem; font-size: 0.8rem;">
  <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
    <span style="display: inline-flex; align-items: center; gap: 0.4rem; color: var(--accent-mint); font-weight: 600;">
      <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-mint); box-shadow: 0 0 6px var(--accent-mint);"></span>
      Sync Engine Active
    </span>
    <span style="color: var(--text-muted);">
      <strong style="color: var(--text-white);">llms.txt:</strong> <?= (int)($syncStatus['total_entries'] ?? 0) ?> resources
    </span>
    <span style="color: var(--text-muted);">
      <strong style="color: var(--text-white);">sitemap.xml:</strong> <?= (int)($syncStatus['sitemap_url_count'] ?? 0) ?> URLs
    </span>
    <span style="color: var(--text-muted);">
      <strong style="color: var(--text-white);">IndexNow:</strong> <?= !empty($syncStatus['indexnow_key']) ? 'Configured' : 'Ready' ?>
    </span>
  </div>
  <div style="font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-muted);">
    Last Reconciled: <?= !empty($syncStatus['last_reconciliation']) ? date('M j, Y H:i', strtotime($syncStatus['last_reconciliation'])) : 'Active' ?>
  </div>
</div>
<?php endif; ?>

<!-- Main Markdown Two-Column Layout -->
<div class="md-manager-grid">

  <!-- LEFT COLUMN: File Explorer Sidebar -->
  <aside class="md-explorer-panel">
    
    <!-- Search Bar & Header -->
    <div class="md-search-wrap">
      <div class="md-search-header">
        <span class="md-search-title">Files &amp; Directives</span>
        <span class="md-search-count" id="visibleFilesCount"><?= count($allFiles) ?> files</span>
      </div>
      <input type="text" id="fileSearchInput" class="form-control form-control-sm md-search-input" placeholder="Search markdown docs or directives..." oninput="filterFilesList()">
    </div>

    <!-- Category Filter Pills -->
    <div class="md-category-pills" id="categoryPillsContainer">
      <button type="button" class="md-cat-pill active" data-category="all" onclick="filterByCategory('all')" title="Show all files">
        <span>All</span>
        <span class="badge-count" id="countAll"><?= $catCounts['all'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="pages" onclick="filterByCategory('pages')" title="Filter core pages">
        <span>Pages</span>
        <span class="badge-count" id="countPages"><?= $catCounts['pages'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="services" onclick="filterByCategory('services')" title="Filter service landing pages">
        <span>Services</span>
        <span class="badge-count" id="countServices"><?= $catCounts['services'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="blogs" onclick="filterByCategory('blogs')" title="Filter blog articles">
        <span>Blogs</span>
        <span class="badge-count" id="countBlogs"><?= $catCounts['blogs'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="llm" onclick="filterByCategory('llm')" title="Filter root LLM directives">
        <span>Directives</span>
        <span class="badge-count" id="countLlm"><?= $catCounts['llm'] ?></span>
      </button>
      <?php if (!empty($catCounts['other'])): ?>
      <button type="button" class="md-cat-pill" data-category="other" onclick="filterByCategory('other')" title="Filter schemas and other documents">
        <span>Other</span>
        <span class="badge-count" id="countOther"><?= $catCounts['other'] ?></span>
      </button>
      <?php endif; ?>
    </div>

    <!-- File List Items -->
    <div class="md-file-list" id="filesListContainer">
      <?php if (empty($allFiles)): ?>
        <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted); font-size: 0.82rem;">
          No files found. Click "Sync All Blogs" or "New File" to get started.
        </div>
      <?php else: ?>
        <?php foreach ($allFiles as $file): 
          $isActive = ($activeFile && $activeFile['key'] === $file['key']);
        ?>
          <a href="<?= ADMIN_URL ?>/markdown?file=<?= urlencode($file['key']) ?>" 
             class="md-file-item <?= $isActive ? 'active' : '' ?>"
             title="<?= e($file['filename']) ?>"
             data-name="<?= strtolower(e($file['filename'])) ?>"
             data-category="<?= e($file['category']) ?>">
            
            <div class="md-file-item-header">
              <!-- File-type Icon -->
              <span class="md-file-icon">
                <?php if ($file['category'] === 'llm'): ?>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><rect x="4" y="8" width="16" height="12" rx="2"/><circle cx="9" cy="13" r="1.5"/><circle cx="15" cy="13" r="1.5"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                <?php elseif ($file['file_type'] === 'json'): ?>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                <?php elseif ($file['file_type'] === 'sql'): ?>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                <?php elseif ($file['is_blog']): ?>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <?php else: ?>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?php endif; ?>
              </span>

              <span class="md-file-name" title="<?= e($file['filename']) ?>"><?= e($file['filename']) ?></span>
            </div>

            <div class="md-file-meta-row">
              <span class="md-badge-cat"><?= e($file['category_name']) ?></span>

              <?php if ($file['is_blog']): ?>
                <?php if ($file['blog_status'] === 'published'): ?>
                  <span class="md-badge-status status-pub">Published</span>
                <?php elseif ($file['blog_status'] === 'draft'): ?>
                  <span class="md-badge-status status-draft">Draft</span>
                <?php endif; ?>

                <?php if ($file['blog_id']): ?>
                  <span class="md-badge-blog">Blog #<?= $file['blog_id'] ?></span>
                <?php endif; ?>
              <?php endif; ?>

              <span class="md-file-size"><?= round($file['size'] / 1024, 1) ?> KB</span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </aside>

  <!-- RIGHT COLUMN: Markdown Workspace -->
  <main class="md-workspace-panel">
    <?php if (!$activeFile): ?>
      <!-- Empty State -->
      <div class="md-empty-workspace">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--border-sage-subtle)" stroke-width="1.5" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <h3 style="color: var(--text-white); margin: 1rem 0 0.4rem; font-family: var(--font-heading);">No File Selected</h3>
        <p style="color: var(--text-muted); font-size: 0.88rem; max-width: 380px; margin: 0 auto 1.5rem;">
          Select a documentation or schema file from the explorer on the left, or create a new file to start drafting knowledge for AI models.
        </p>
        <div style="display: flex; gap: 0.75rem; justify-content: center;">
          <a href="<?= ADMIN_URL ?>/markdown?file=llms.txt" class="btn btn-outline-mint btn-sm">Edit llms.txt</a>
          <button type="button" class="btn btn-primary btn-sm" onclick="openModal('createModal')">Create New File</button>
        </div>
      </div>
    <?php else: 
      $blogSlug = ($activeFile['is_blog'] && $activeFile['blog_id'] && !empty($blogMap[$activeFile['blog_id']]['slug'])) 
          ? $blogMap[$activeFile['blog_id']]['slug'] 
          : null;
      $canonicalPublicUrl = function_exists('getCanonicalMarkdownPublicUrl')
          ? getCanonicalMarkdownPublicUrl($activeFile['category'], $activeFile['filename'], $blogSlug, false)
          : null;
      $localPublicUrl = function_exists('getCanonicalMarkdownPublicUrl')
          ? getCanonicalMarkdownPublicUrl($activeFile['category'], $activeFile['filename'], $blogSlug, true)
          : null;

      $publicViewUrl = $localPublicUrl ?: (SITE_URL . '/markdown/view.php?file=' . urlencode($activeFile['rel_path']));
      
      // Determine rendered HTML based on file type
      if ($activeFile['file_type'] === 'json') {
          $dec = json_decode($activeContent);
          $previewFormatted = ($dec !== null) ? json_encode($dec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $activeContent;
          $renderedHtml = '<pre style="background: #000c0a; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border-sage-subtle); overflow-x: auto; color: var(--accent-mint); font-family: var(--font-mono); font-size: 0.88rem; line-height: 1.6;"><code>' . htmlspecialchars($previewFormatted) . '</code></pre>';
      } elseif ($activeFile['file_type'] === 'sql') {
          $renderedHtml = '<pre style="background: #000c0a; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border-sage-subtle); overflow-x: auto; color: var(--text-secondary); font-family: var(--font-mono); font-size: 0.88rem; line-height: 1.6;"><code>' . htmlspecialchars($activeContent) . '</code></pre>';
      } else {
          $renderedHtml = markdownToHtml($activeContent);
      }
    ?>

      <!-- Workspace Header -->
      <div class="md-workspace-header">
        <div>
          <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
            <h2 style="margin: 0; font-size: 1.25rem; font-family: var(--font-heading); color: var(--text-white);">
              <?= e($activeFile['filename']) ?>
            </h2>
            <span class="md-badge-cat"><?= e($activeFile['category_name']) ?></span>
            
            <?php if ($activeFile['is_blog']): ?>
              <?php if ($activeFile['blog_status'] === 'published'): ?>
                <span class="md-badge-status status-pub">Published</span>
              <?php elseif ($activeFile['blog_status'] === 'draft'): ?>
                <span class="md-badge-status status-draft" title="Draft article — Protected from unauthenticated public access">Protected Draft</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <div class="md-workspace-meta-row">
            <span class="md-meta-chip" title="File Size">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              <span><?= round($activeFile['size'] / 1024, 2) ?> KB</span>
            </span>
            <span class="md-meta-chip" title="Last Modified Timestamp">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span>Modified: <?= date('M j, Y H:i:s', $activeFile['mtime']) ?></span>
            </span>
            <span class="md-meta-chip md-meta-path" title="<?= e($activeFile['rel_path']) ?>">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
              <code class="md-meta-path-text"><?= e($activeFile['rel_path']) ?></code>
            </span>
            <?php if ($canonicalPublicUrl): ?>
            <span class="md-meta-chip" title="Canonical Public URL">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--accent-mint)" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
              <code style="color: var(--accent-mint); font-family: var(--font-mono); font-size: 0.76rem;"><?= e($canonicalPublicUrl) ?></code>
            </span>
            <?php endif; ?>
          </div>
        </div>

        <div class="md-workspace-actions">
          <!-- Public View Link -->
          <a href="<?= e($publicViewUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm md-action-btn" title="Open public document view">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            <span>Public URL</span>
          </a>

          <?php if ($canonicalPublicUrl): ?>
          <!-- Copy Canonical URL Button -->
          <button type="button" class="btn btn-secondary btn-sm md-action-btn" onclick="copyCanonicalUrl('<?= e($canonicalPublicUrl) ?>', this)" title="Copy canonical public URL: <?= e($canonicalPublicUrl) ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <span>Copy URL</span>
          </button>
          <?php endif; ?>

          <!-- Edit in Blog CMS (if linked) -->
          <?php if ($activeFile['is_blog'] && $activeFile['blog_id']): ?>
            <a href="<?= ADMIN_URL ?>/posts/edit.php?id=<?= $activeFile['blog_id'] ?>" class="btn btn-outline-mint btn-sm md-action-btn" title="Edit this article inside the visual Blog Editor">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <span>Edit in Blog CMS</span>
            </a>
          <?php endif; ?>

          <!-- Rename File (if deletable) -->
          <?php if ($activeFile['is_deletable']): ?>
            <button type="button" class="btn btn-secondary btn-sm md-action-btn" onclick="openRenameModal('<?= e($activeFile['key']) ?>', '<?= htmlspecialchars($activeFile['filename'], ENT_QUOTES) ?>')" title="Rename file">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <span>Rename</span>
            </button>
          <?php endif; ?>

          <!-- Delete File (if deletable) -->
          <?php if ($activeFile['is_deletable']): ?>
            <button type="button" class="btn btn-danger btn-sm md-action-btn" onclick="openDeleteModal('<?= e($activeFile['key']) ?>', '<?= htmlspecialchars($activeFile['filename'], ENT_QUOTES) ?>')" title="Delete file">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              <span>Delete</span>
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- View Switcher Tabs -->
      <div class="md-view-tabs">
        <button type="button" class="md-view-tab active" id="tabRawView" onclick="switchMdWorkspaceTab('raw')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          <span>Raw Editor</span>
        </button>
        <button type="button" class="md-view-tab" id="tabPreviewView" onclick="switchMdWorkspaceTab('preview')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <span>Formatted Preview</span>
        </button>
      </div>

      <!-- Form for Saving File Changes -->
      <form method="POST" action="<?= ADMIN_URL ?>/markdown" id="editMarkdownForm" class="md-editor-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_file">
        <input type="hidden" name="file_key" value="<?= e($activeFile['key']) ?>">

        <!-- Pane 1: Raw Editor -->
        <div id="paneRawEditor" class="md-editor-pane">
          <textarea id="rawMarkdownContent" name="content" class="md-raw-textarea" spellcheck="false" placeholder="Write or edit documentation..."><?= e($activeContent) ?></textarea>
        </div>

        <!-- Pane 2: Formatted Preview -->
        <div id="paneFormattedPreview" class="md-preview-pane" style="display: none;">
          <div class="prose" id="previewHtmlContainer">
            <?= $renderedHtml ?>
          </div>
        </div>

        <!-- Bottom Action Strip -->
        <div class="md-footer-strip">
          <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <?php if ($activeFile['is_blog'] && $activeFile['blog_id']): ?>
              <label class="checkbox-toggle-label" style="font-size: 0.82rem; margin: 0; color: var(--text-primary); cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem;">
                <input type="checkbox" name="sync_to_blog" value="1" checked>
                <span><strong>Sync changes to Blog Post #<?= $activeFile['blog_id'] ?></strong> (updates title, excerpt &amp; body)</span>
              </label>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 0.65rem; margin-left: auto;">
            <button type="submit" class="btn btn-primary btn-sm">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              <span>Save Changes</span>
            </button>
          </div>
        </div>
      </form>

    <?php endif; ?>
  </main>

</div>

<!-- Website-to-Markdown Live Synchronization Status Panel -->
<div class="panel" style="margin-top: 1.75rem;">
  <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 0.65rem;">
        <h2 class="panel-title" style="margin: 0; font-size: 1.15rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span>Website-to-Markdown Live Synchronization Status</span>
        </h2>
        <span class="sync-badge"><span class="sync-dot"></span> Source of Truth: Website Content</span>
      </div>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
        Whenever content on a public website page is added or updated, its corresponding Markdown file reflects the latest published content.
      </div>
    </div>
    <div style="display: flex; gap: 0.65rem; align-items: center;">
      <form method="POST" action="<?= ADMIN_URL ?>/markdown" style="margin: 0;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reconcile_all_website_to_markdown">
        <button type="submit" class="btn btn-outline-mint btn-sm" onclick="return confirm('Reconcile all 13 published website pages to Markdown files?')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span>Sync All Website Pages</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Summary Cards -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; padding: 1.25rem 1.5rem; background: rgba(0, 18, 15, 0.4); border-bottom: 1px solid var(--border-sage);">
    <div style="background: #00120f; border: 1px solid var(--border-sage-subtle); border-radius: var(--radius-xs); padding: 0.85rem 1rem;">
      <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">Total Website Pages</div>
      <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-white); font-family: var(--font-mono); margin-top: 0.2rem;"><?= count($pageInventory) ?></div>
      <div style="font-size: 0.72rem; color: var(--accent-mint); margin-top: 0.2rem;">4 Core + 9 Dedicated Services</div>
    </div>
    <div style="background: #00120f; border: 1px solid var(--border-sage-subtle); border-radius: var(--radius-xs); padding: 0.85rem 1rem;">
      <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">In-Sync Status</div>
      <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-mint); font-family: var(--font-mono); margin-top: 0.2rem;"><?= $websiteSyncedCount ?> / <?= count($pageInventory) ?></div>
      <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;">All eligible files active</div>
    </div>
    <div style="background: #00120f; border: 1px solid var(--border-sage-subtle); border-radius: var(--radius-xs); padding: 0.85rem 1rem;">
      <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">Blog Articles Synced</div>
      <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-white); font-family: var(--font-mono); margin-top: 0.2rem;"><?= (int)($websiteAuditLog['blogs_count'] ?? 0) ?></div>
      <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;">Published posts in markdown/blogs/</div>
    </div>
    <div style="background: #00120f; border: 1px solid var(--border-sage-subtle); border-radius: var(--radius-xs); padding: 0.85rem 1rem;">
      <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);">Last Auto-Sync</div>
      <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-white); font-family: var(--font-mono); margin-top: 0.45rem;">
        <?= !empty($websiteAuditLog['last_reconciliation']) ? date('M j, Y H:i:s', strtotime($websiteAuditLog['last_reconciliation'])) : 'Active' ?>
      </div>
      <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;">Atomic hash-verified</div>
    </div>
  </div>

  <!-- Pages Table -->
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Source Page</th>
          <th>Category</th>
          <th>Markdown File</th>
          <th>Canonical Public URL</th>
          <th>Last Synced</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pageInventory as $pKey => $pInfo): 
          $mdRel = $pInfo['markdown_file'] ?? $pInfo['md_file'] ?? '';
          $mdAbs = CMS_ROOT . '/' . $mdRel;
          $mdExists = !empty($mdRel) && file_exists($mdAbs);
          $audit = $websiteAuditLog['pages'][$pKey] ?? null;
          $syncedTime = $audit['last_synced'] ?? ($mdExists ? date('Y-m-d H:i:s', filemtime($mdAbs)) : null);
          $isSynced = $mdExists && (!empty($audit['status']) ? $audit['status'] !== 'error' : true);
          $hasError = !empty($audit['status']) && $audit['status'] === 'error';
          $editorFileKey = str_replace('markdown/', '', $mdRel);
          $sourceFile = $pInfo['source_file'] ?? $pInfo['html_file'] ?? '';
          $canonicalUrl = $pInfo['canonical_url'] ?? $pInfo['public_url'] ?? '';
        ?>
          <tr>
            <td>
              <div style="font-weight: 600; color: var(--text-white); font-size: 0.88rem;"><?= e($pInfo['title']) ?></div>
              <div style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;"><?= e($sourceFile) ?></div>
            </td>
            <td>
              <span class="md-badge-cat"><?= e($pInfo['category_name']) ?></span>
            </td>
            <td>
              <a href="<?= ADMIN_URL ?>/markdown?file=<?= urlencode($editorFileKey) ?>" style="color: var(--accent-mint); font-family: var(--font-mono); font-size: 0.82rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;" title="View or edit in Markdown Editor">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span><?= e(basename($mdRel)) ?></span>
              </a>
            </td>
            <td>
              <div style="display: flex; align-items: center; gap: 0.45rem;">
                <code style="color: var(--text-secondary); font-size: 0.75rem; font-family: var(--font-mono); max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($canonicalUrl) ?></code>
                <button type="button" class="btn btn-secondary btn-sm" style="padding: 0.15rem 0.45rem; font-size: 0.7rem; height: 24px; line-height: 1;" onclick="copyCanonicalUrl('<?= e($canonicalUrl) ?>', this)" title="Copy canonical public URL">Copy</button>
              </div>
            </td>
            <td>
              <span style="font-family: var(--font-mono); font-size: 0.76rem; color: var(--text-muted);">
                <?= $syncedTime ? date('M j, Y H:i:s', strtotime($syncedTime)) : 'Pending' ?>
              </span>
            </td>
            <td>
              <?php if ($isSynced): ?>
                <span class="sync-badge" style="font-size: 0.7rem; padding: 0.2rem 0.55rem;"><span class="sync-dot"></span> Synced</span>
              <?php elseif ($hasError): ?>
                <span class="badge" style="background: rgba(255, 107, 107, 0.15); color: #ff8585; border: 1px solid rgba(255, 107, 107, 0.3); font-size: 0.7rem;">Error</span>
              <?php else: ?>
                <span class="badge" style="background: rgba(240, 185, 11, 0.15); color: #f0b90b; border: 1px solid rgba(240, 185, 11, 0.3); font-size: 0.7rem;">Pending</span>
              <?php endif; ?>
            </td>
            <td style="text-align: right;">
              <form method="POST" action="<?= ADMIN_URL ?>/markdown" style="margin: 0; display: inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="sync_website_page">
                <input type="hidden" name="page_key" value="<?= e($pKey) ?>">
                <button type="submit" class="btn btn-secondary btn-sm" title="Re-sync <?= e($pKey) ?> from source website HTML" style="font-size: 0.75rem; padding: 0.25rem 0.6rem; height: 28px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  <span>Sync</span>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal 1: Create New File -->
<div id="createModal" class="editor-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="createModalTitle">
  <div class="editor-modal-card" style="max-width: 580px;">
    <div class="editor-modal-header">
      <h3 id="createModalTitle" class="editor-modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        <span>Create New File</span>
      </h3>
      <button type="button" class="editor-modal-close" onclick="closeModal('createModal')" aria-label="Close">&times;</button>
    </div>
    <form method="POST" action="<?= ADMIN_URL ?>/markdown">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create_file">
      
      <div class="editor-modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label for="createCategory" class="form-label">Category / Directory <span class="required">*</span></label>
            <select id="createCategory" name="category" class="form-control" onchange="handleCategoryChange(this.value)" required>
              <option value="pages">Pages (markdown/pages/)</option>
              <option value="services">Services (markdown/services/)</option>
              <option value="blogs">Blogs (markdown/blogs/)</option>
              <option value="other" selected>Other (markdown/other/)</option>
              <option value="llm">LLM Directives (root directory)</option>
            </select>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label for="createFilename" class="form-label">Filename <span class="required">*</span></label>
            <input type="text" id="createFilename" name="filename" class="form-control" placeholder="e.g. ad-testing-guide.md" required>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="createTitle" class="form-label">Document Title (H1)</label>
          <input type="text" id="createTitle" name="title" class="form-control" placeholder="e.g. High-ROAS TikTok Creative Framework">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label for="createContent" class="form-label">Initial Content</label>
          <textarea id="createContent" name="content" class="form-control" style="min-height: 140px; font-family: var(--font-mono); font-size: 0.85rem;" placeholder="Start writing documentation content..."></textarea>
        </div>
      </div>

      <div class="editor-modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Create Document</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 2: Upload File -->
<div id="uploadModal" class="editor-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
  <div class="editor-modal-card" style="max-width: 520px;">
    <div class="editor-modal-header">
      <h3 id="uploadModalTitle" class="editor-modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span>Upload File</span>
      </h3>
      <button type="button" class="editor-modal-close" onclick="closeModal('uploadModal')" aria-label="Close">&times;</button>
    </div>
    <form method="POST" action="<?= ADMIN_URL ?>/markdown" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="upload_file">
      
      <div class="editor-modal-body">
        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="uploadCategory" class="form-label">Target Category / Directory</label>
          <select id="uploadCategory" name="category" class="form-control" required>
            <option value="pages">Pages (markdown/pages/)</option>
            <option value="services">Services (markdown/services/)</option>
            <option value="blogs">Blogs (markdown/blogs/)</option>
            <option value="other" selected>Other (markdown/other/)</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label for="uploadFile" class="form-label">Select File (.md, .txt) <span class="required">*</span></label>
          <input type="file" id="uploadFile" name="md_file" class="form-control" accept=".md,.txt,.markdown" required style="cursor: pointer;">
          <div class="form-hint">Accepted file types: Markdown (.md, .markdown) or plain text (.txt).</div>
        </div>
      </div>

      <div class="editor-modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('uploadModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Upload File</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 3: Delete Confirmation Modal -->
<div id="deleteModal" class="editor-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
  <div class="editor-modal-card" style="max-width: 480px;">
    <div class="editor-modal-header">
      <h3 id="deleteModalTitle" class="editor-modal-title" style="color: #ff6b6b;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        <span>Confirm File Deletion</span>
      </h3>
      <button type="button" class="editor-modal-close" onclick="closeModal('deleteModal')" aria-label="Close">&times;</button>
    </div>
    <form method="POST" action="<?= ADMIN_URL ?>/markdown" id="deleteFileForm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete_file">
      <input type="hidden" name="file_key" id="deleteModalFileKey" value="">

      <div class="editor-modal-body">
        <p style="color: var(--text-primary); font-size: 0.92rem; margin-bottom: 0.75rem; line-height: 1.5;">
          Are you sure you want to permanently delete this Markdown document?
        </p>
        <div style="background: #00120f; border: 1px solid rgba(255, 107, 107, 0.3); border-radius: var(--radius-xs); padding: 0.75rem 1rem; margin-bottom: 0.75rem;">
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem;">Target file:</div>
          <div id="deleteModalFileName" style="font-family: var(--font-mono); font-size: 0.9rem; font-weight: 600; color: #ff8585; word-break: break-all;"></div>
        </div>
        <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0; line-height: 1.45;">
          This action cannot be undone. Any linked discovery indexes and LLM directives will be automatically reconciled.
        </p>
      </div>

      <div class="editor-modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('deleteModal')">Cancel</button>
        <button type="submit" class="btn btn-danger btn-sm" id="confirmDeleteBtn" style="display: inline-flex; align-items: center; gap: 0.45rem;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          <span>Confirm Delete</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 4: Rename File Modal -->
<div id="renameModal" class="editor-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="renameModalTitle">
  <div class="editor-modal-card" style="max-width: 480px;">
    <div class="editor-modal-header">
      <h3 id="renameModalTitle" class="editor-modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        <span>Rename File</span>
      </h3>
      <button type="button" class="editor-modal-close" onclick="closeModal('renameModal')" aria-label="Close">&times;</button>
    </div>
    <form method="POST" action="<?= ADMIN_URL ?>/markdown" id="renameFileForm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="rename_file">
      <input type="hidden" name="file_key" id="renameModalFileKey" value="">

      <div class="editor-modal-body">
        <div class="form-group" style="margin-bottom: 0;">
          <label for="renameModalNewFilename" class="form-label">New Filename <span class="required">*</span></label>
          <input type="text" id="renameModalNewFilename" name="new_filename" class="form-control" required>
          <div class="form-hint" style="margin-top: 0.4rem; font-size: 0.78rem; color: var(--text-muted);">
            Filename must not represent homepage. Existing extension will be retained if omitted.
          </div>
        </div>
      </div>

      <div class="editor-modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('renameModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.45rem;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span>Save New Name</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
let currentCategoryFilter = 'all';

function switchMdWorkspaceTab(tab) {
  const tabRaw = document.getElementById('tabRawView');
  const tabPreview = document.getElementById('tabPreviewView');
  const paneRaw = document.getElementById('paneRawEditor');
  const panePreview = document.getElementById('paneFormattedPreview');

  if (!tabRaw || !tabPreview || !paneRaw || !panePreview) return;

  if (tab === 'preview') {
    tabRaw.classList.remove('active');
    tabPreview.classList.add('active');
    paneRaw.style.display = 'none';
    panePreview.style.display = 'block';

    const rawVal = document.getElementById('rawMarkdownContent') ? document.getElementById('rawMarkdownContent').value : '';
    const activeFileType = "<?= e($activeFile['file_type'] ?? 'md') ?>";

    if (activeFileType === 'json') {
      try {
        const obj = JSON.parse(rawVal);
        document.getElementById('previewHtmlContainer').innerHTML = '<pre style="background: #000c0a; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border-sage-subtle); overflow-x: auto; color: var(--accent-mint); font-family: var(--font-mono); font-size: 0.88rem; line-height: 1.6;"><code>' + escapeHtml(JSON.stringify(obj, null, 2)) + '</code></pre>';
      } catch (e) {
        document.getElementById('previewHtmlContainer').innerHTML = '<div style="color: #ff5252; font-family: var(--font-mono); font-size: 0.85rem; padding: 1rem; background: rgba(255,82,82,0.1); border-radius: 6px;">Invalid JSON: ' + escapeHtml(e.message) + '</div>';
      }
    } else if (activeFileType === 'sql') {
      document.getElementById('previewHtmlContainer').innerHTML = '<pre style="background: #000c0a; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border-sage-subtle); overflow-x: auto; color: var(--text-secondary); font-family: var(--font-mono); font-size: 0.88rem; line-height: 1.6;"><code>' + escapeHtml(rawVal) + '</code></pre>';
    } else {
      document.getElementById('previewHtmlContainer').innerHTML = clientMarkdownToHtml(rawVal);
    }
  } else {
    tabPreview.classList.remove('active');
    tabRaw.classList.add('active');
    panePreview.style.display = 'none';
    paneRaw.style.display = 'flex';
  }
}

function handleCategoryChange(cat) {
  const fnInput = document.getElementById('createFilename');
  if (!fnInput) return;
  if (cat === 'schemas') {
    if (!fnInput.value || fnInput.value.endsWith('.md')) {
      fnInput.placeholder = 'e.g. event-tracking.json or public-stats.sql';
    }
  } else {
    fnInput.placeholder = 'e.g. ad-testing-guide.md';
  }
}

function filterByCategory(category) {
  currentCategoryFilter = category;
  document.querySelectorAll('.md-cat-pill').forEach(btn => {
    btn.classList.toggle('active', btn.getAttribute('data-category') === category);
  });
  applyFilterRules();
}

function filterFilesList() {
  applyFilterRules();
}

function applyFilterRules() {
  const searchInput = document.getElementById('fileSearchInput');
  const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
  const items = document.querySelectorAll('.md-file-item');
  let visibleCount = 0;

  items.forEach(item => {
    const itemName = item.getAttribute('data-name') || '';
    const itemCat = item.getAttribute('data-category') || '';
    const matchesCat = (currentCategoryFilter === 'all' || itemCat === currentCategoryFilter);
    const matchesSearch = (!searchVal || itemName.includes(searchVal));

    if (matchesCat && matchesSearch) {
      item.style.display = 'flex';
      visibleCount++;
    } else {
      item.style.display = 'none';
    }
  });

  const countEl = document.getElementById('visibleFilesCount');
  if (countEl) {
    countEl.textContent = visibleCount + ' files';
  }
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('open');
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('open');
}

function openDeleteModal(fileKey, fileName) {
  const keyInput = document.getElementById('deleteModalFileKey');
  const nameDisplay = document.getElementById('deleteModalFileName');
  if (keyInput) keyInput.value = fileKey;
  if (nameDisplay) nameDisplay.textContent = fileName;
  openModal('deleteModal');
}

function openRenameModal(fileKey, fileName) {
  const keyInput = document.getElementById('renameModalFileKey');
  const nameInput = document.getElementById('renameModalNewFilename');
  if (keyInput) keyInput.value = fileKey;
  if (nameInput) nameInput.value = fileName;
  openModal('renameModal');
}

function escapeHtml(text) {
  const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function clientMarkdownToHtml(md) {
  if (!md) return '';
  let text = md.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

  // Strip YAML frontmatter for preview if present
  if (text.startsWith('---')) {
    const endFm = text.indexOf('\n---', 3);
    if (endFm !== -1) {
      text = text.substring(endFm + 4).trim();
    }
  }

  // Headings
  text = text.replace(/^######\s+(.+)$/gm, '<h6>$1</h6>');
  text = text.replace(/^#####\s+(.+)$/gm, '<h5>$1</h5>');
  text = text.replace(/^####\s+(.+)$/gm, '<h4>$1</h4>');
  text = text.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');
  text = text.replace(/^##\s+(.+)$/gm, '<h2>$1</h2>');
  text = text.replace(/^#\s+(.+)$/gm, '<h1>$1</h1>');

  // Blockquotes
  text = text.replace(/^>\s*(.+)$/gm, '<blockquote><p>$1</p></blockquote>');

  // Bold & Italic
  text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  text = text.replace(/__(.+?)__/g, '<strong>$1</strong>');
  text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');
  text = text.replace(/_([^_]+)_/g, '<em>$1</em>');

  // Links
  text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

  // Lists
  text = text.replace(/^[-*]\s+(.+)$/gm, '<li>$1</li>');
  text = text.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');

  // Paragraphs
  const paragraphs = text.split(/\n{2,}/);
  return paragraphs.map(p => {
    p = p.trim();
    if (!p) return '';
    if (/^<(h[1-6]|ul|ol|blockquote|hr|div|pre)/i.test(p)) return p;
    return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
  }).join('\n');
}

function copyCanonicalUrl(url, btnElement) {
  if (!url) return;
  navigator.clipboard.writeText(url).then(function() {
    const span = btnElement.querySelector('span');
    const origText = span ? span.textContent : 'Copy URL';
    if (span) span.textContent = 'Copied!';
    btnElement.style.borderColor = 'var(--accent-mint)';
    btnElement.style.color = 'var(--accent-mint)';
    setTimeout(function() {
      if (span) span.textContent = origText;
      btnElement.style.borderColor = '';
      btnElement.style.color = '';
    }, 2000);
  }).catch(function(err) {
    prompt('Canonical Public URL:', url);
  });
}

// Reset scroll position of editor textarea on load to guarantee first line visibility
document.addEventListener('DOMContentLoaded', function() {
  const rawTextarea = document.getElementById('rawMarkdownContent');
  if (rawTextarea) {
    rawTextarea.scrollTop = 0;
  }
});
</script>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>
