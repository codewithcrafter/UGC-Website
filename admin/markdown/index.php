<?php
/**
 * MY UGC VIDEO — Markdown & LLMs Management Center
 * File manager for public AI directives, markdown knowledge bases, and automatic blog synchronizations.
 * Premium Dark Editorial CMS
 */

define('CMS_ROOT', dirname(dirname(__DIR__)));
require_once CMS_ROOT . '/config/config.php';

$activeNav = 'markdown';
$pageTitle = 'Markdown & LLMs';

$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard.php'],
    ['name' => 'Markdown & LLMs', 'url' => ADMIN_URL . '/markdown/index.php']
];

$db = getDb();

// Helper to list all managed files
function getManagedMarkdownFiles(PDO $db): array {
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
    $blogStmt = $db->query("SELECT id, title, slug, status, is_featured, published_at FROM blog_posts");
    $blogMap = [];
    while ($row = $blogStmt->fetch()) {
        $blogMap[(int) $row['id']] = $row;
    }

    // 3. Scan subdirectories in MARKDOWN_DIR
    $categories = [
        'blogs'        => ['dir' => 'blogs', 'name' => 'Blogs', 'icon' => 'blog'],
        'pages'        => ['dir' => 'pages', 'name' => 'Pages', 'icon' => 'page'],
        'case-studies' => ['dir' => 'case-studies', 'name' => 'Case Studies', 'icon' => 'case'],
        'other'        => ['dir' => 'other', 'name' => 'Other', 'icon' => 'file']
    ];

    foreach ($categories as $catKey => $catInfo) {
        $dirPath = MARKDOWN_DIR . DIRECTORY_SEPARATOR . $catInfo['dir'];
        if (!is_dir($dirPath)) continue;

        $items = scandir($dirPath);
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
                'rel_path'      => $relPath,
                'abs_path'      => $fullPath,
                'category'      => $catKey,
                'category_name' => $catInfo['name'],
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

    // Sort files by modified time descending
    usort($files, function($a, $b) {
        // llms.txt always top
        if ($a['key'] === 'llms.txt') return -1;
        if ($b['key'] === 'llms.txt') return 1;
        return $b['mtime'] <=> $a['mtime'];
    });

    return $files;
}

// Security function to validate file key and resolve absolute path
function resolveSafeMarkdownPath(string $key): ?string {
    $key = trim($key);
    if (empty($key)) return null;

    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $key);

    if ($normalized === 'llms.txt') {
        $p = CMS_ROOT . DIRECTORY_SEPARATOR . 'llms.txt';
        return file_exists($p) ? realpath($p) : null;
    }

    $target = MARKDOWN_DIR . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
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

    if ($action === 'reconcile_blogs') {
        $res = reconcileAllBlogsToMarkdown();
        setFlash('success', "Reconciled {$res['synced']} of {$res['total']} blog posts to markdown successfully.");
        header('Location: ' . ADMIN_URL . '/markdown/index.php');
        exit;
    }

    if ($action === 'save_file') {
        $fileKey = trim($_POST['file_key'] ?? '');
        $content = $_POST['content'] ?? '';
        $syncToBlog = !empty($_POST['sync_to_blog']);

        $safePath = resolveSafeMarkdownPath($fileKey);
        if (!$safePath || !is_file($safePath)) {
            setFlash('error', 'File not found or access denied.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php');
            exit;
        }

        if (file_put_contents($safePath, $content) === false) {
            setFlash('error', 'Failed to write changes to file.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
            exit;
        }

        // Optional reverse sync to Blog CMS post
        $syncedBlogMsg = '';
        if ($syncToBlog && preg_match('/^blogs\/post-(\d+)-/i', $fileKey, $m)) {
            $postId = (int) $m[1];
            // Parse frontmatter and markdown body
            $body = $content;
            $newTitle = null;
            $newExcerpt = null;

            if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $content, $fmMatches)) {
                $yaml = $fmMatches[1];
                $body = trim($fmMatches[2]);
                if (preg_match('/^title:\s*["\']?(.*?)["\']?$/m', $yaml, $tm)) {
                    $newTitle = trim($tm[1]);
                }
                if (preg_match('/^excerpt:\s*["\']?(.*?)["\']?$/m', $yaml, $em)) {
                    $newExcerpt = trim($em[1]);
                }
            }

            // Convert body markdown to HTML
            $newHtml = markdownToHtml($body);

            $updateFields = ["content = :content"];
            $params = [':content' => $newHtml, ':id' => $postId];

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

        setFlash('success', 'File "' . htmlspecialchars(basename($safePath)) . '" updated successfully' . $syncedBlogMsg . '!');
        header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($fileKey));
        exit;
    }

    if ($action === 'create_file') {
        $cat = trim($_POST['category'] ?? 'other');
        $filename = trim($_POST['filename'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $initialContent = trim($_POST['content'] ?? '');

        if (!in_array($cat, ['blogs', 'pages', 'case-studies', 'other', 'llm'])) {
            $cat = 'other';
        }

        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '-', $filename);
        $filename = trim($filename, '-.');
        if (!str_ends_with(strtolower($filename), '.md') && !str_ends_with(strtolower($filename), '.txt')) {
            $filename .= '.md';
        }

        if (empty($filename) || $filename === '.md') {
            setFlash('error', 'Please provide a valid file name.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php');
            exit;
        }

        if ($cat === 'llm') {
            $targetPath = CMS_ROOT . DIRECTORY_SEPARATOR . $filename;
        } else {
            $targetPath = MARKDOWN_DIR . DIRECTORY_SEPARATOR . $cat . DIRECTORY_SEPARATOR . $filename;
        }

        if (file_exists($targetPath)) {
            setFlash('error', 'A file with this name already exists in this category.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php');
            exit;
        }

        $payload = "";
        if (!empty($title)) {
            $payload .= "# " . $title . "\n\n";
        }
        $payload .= $initialContent ?: "Write your markdown documentation or content here...\n";

        if (file_put_contents($targetPath, $payload) === false) {
            setFlash('error', 'Failed to create file on disk.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php');
            exit;
        }

        $newKey = ($cat === 'llm') ? $filename : ($cat . '/' . $filename);
        setFlash('success', 'File "' . htmlspecialchars($filename) . '" created successfully!');
        header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($newKey));
        exit;
    }

    if ($action === 'upload_file') {
        $cat = trim($_POST['category'] ?? 'other');
        if (!in_array($cat, ['pages', 'case-studies', 'other', 'blogs'])) {
            $cat = 'other';
        }

        if (empty($_FILES['md_file']['name']) || $_FILES['md_file']['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'Please select a valid markdown file to upload.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php');
            exit;
        }

        $origName = basename($_FILES['md_file']['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['md', 'txt', 'markdown'])) {
            setFlash('error', 'Only markdown (.md, .txt) files are accepted.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php');
            exit;
        }

        $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '-', pathinfo($origName, PATHINFO_FILENAME)) . '.md';
        $destDir = MARKDOWN_DIR . DIRECTORY_SEPARATOR . $cat;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destPath = $destDir . DIRECTORY_SEPARATOR . $cleanName;
        if (move_uploaded_file($_FILES['md_file']['tmp_name'], $destPath)) {
            setFlash('success', 'File "' . htmlspecialchars($cleanName) . '" uploaded successfully!');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=' . urlencode($cat . '/' . $cleanName));
            exit;
        } else {
            setFlash('error', 'Failed to move uploaded file.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php');
            exit;
        }
    }

    if ($action === 'delete_file') {
        $fileKey = trim($_POST['file_key'] ?? '');
        if ($fileKey === 'llms.txt') {
            setFlash('error', 'llms.txt is a required core directive and cannot be deleted.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php?file=llms.txt');
            exit;
        }

        $safePath = resolveSafeMarkdownPath($fileKey);
        if (!$safePath || !is_file($safePath)) {
            setFlash('error', 'File not found or access denied.');
            header('Location: ' . ADMIN_URL . '/markdown/index.php');
            exit;
        }

        if (unlink($safePath)) {
            setFlash('success', 'File deleted successfully.');
        } else {
            setFlash('error', 'Failed to delete file.');
        }
        header('Location: ' . ADMIN_URL . '/markdown/index.php');
        exit;
    }
}

// Load all managed files
$allFiles = getManagedMarkdownFiles($db);

// Determine active file
$activeFileKey = trim($_GET['file'] ?? '');
if (empty($activeFileKey) && !empty($allFiles)) {
    // Default to llms.txt if present, or first file
    $activeFileKey = $allFiles[0]['key'];
}

$activeFile = null;
$activeContent = '';
if (!empty($activeFileKey)) {
    foreach ($allFiles as $f) {
        if ($f['key'] === $activeFileKey) {
            $activeFile = $f;
            $safePath = resolveSafeMarkdownPath($activeFileKey);
            if ($safePath && file_exists($safePath)) {
                $activeContent = file_get_contents($safePath);
            }
            break;
        }
    }
}

// Category counts
$counts = [
    'all'          => count($allFiles),
    'llm'          => 0,
    'blogs'        => 0,
    'pages'        => 0,
    'case-studies' => 0,
    'other'        => 0
];
foreach ($allFiles as $f) {
    if (isset($counts[$f['category']])) {
        $counts[$f['category']]++;
    }
}

include CMS_ROOT . '/admin/includes/header.php';
?>

<!-- Header Actions Bar -->
<div class="cms-action-bar">
  <div>
    <h1 class="cms-page-heading" style="margin: 0; font-size: 1.55rem; font-family: var(--font-heading); color: var(--text-white);">
      Markdown &amp; LLMs Manager
    </h1>
    <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.2rem;">
      Public LLM knowledge directives, markdown content bases, and bi-directional Blog CMS synchronization.
    </div>
  </div>

  <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
    <!-- Reconcile Utility -->
    <form method="POST" action="<?= ADMIN_URL ?>/markdown/index.php" style="margin: 0;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reconcile_blogs">
      <button type="submit" class="btn btn-secondary btn-sm" title="Reconcile all MySQL blog posts into Markdown storage" onclick="return confirm('Reconcile all database blog posts into managed Markdown files?')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
        <span>Sync All Blogs</span>
      </button>
    </form>

    <!-- Upload MD Button -->
    <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('uploadModal')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <span>Upload .MD</span>
    </button>

    <!-- New File Button -->
    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('createModal')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>New File</span>
    </button>
  </div>
</div>

<!-- Main Markdown Two-Column Layout -->
<div class="md-manager-grid">

  <!-- LEFT COLUMN: File Explorer Sidebar -->
  <aside class="md-explorer-panel">
    
    <!-- Search Bar -->
    <div style="margin-bottom: 0.85rem;">
      <input type="text" id="fileSearchInput" class="form-control form-control-sm" placeholder="Filter files by name or slug..." oninput="filterFilesList()">
    </div>

    <!-- Category Filter Pills -->
    <div class="md-category-pills">
      <button type="button" class="md-cat-pill active" data-category="all" onclick="filterByCategory('all')">
        All <span class="badge-count"><?= $counts['all'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="llm" onclick="filterByCategory('llm')">
        LLM Directives <span class="badge-count"><?= $counts['llm'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="blogs" onclick="filterByCategory('blogs')">
        Blogs <span class="badge-count"><?= $counts['blogs'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="pages" onclick="filterByCategory('pages')">
        Pages <span class="badge-count"><?= $counts['pages'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="case-studies" onclick="filterByCategory('case-studies')">
        Case Studies <span class="badge-count"><?= $counts['case-studies'] ?></span>
      </button>
      <button type="button" class="md-cat-pill" data-category="other" onclick="filterByCategory('other')">
        Other <span class="badge-count"><?= $counts['other'] ?></span>
      </button>
    </div>

    <!-- File List Items -->
    <div class="md-file-list" id="filesListContainer">
      <?php if (empty($allFiles)): ?>
        <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted); font-size: 0.82rem;">
          No markdown files found. Click "Sync All Blogs" or "New File" to get started.
        </div>
      <?php else: ?>
        <?php foreach ($allFiles as $file): 
          $isActive = ($activeFile && $activeFile['key'] === $file['key']);
        ?>
          <a href="<?= ADMIN_URL ?>/markdown/index.php?file=<?= urlencode($file['key']) ?>" 
             class="md-file-item <?= $isActive ? 'active' : '' ?>"
             data-name="<?= strtolower(e($file['filename'])) ?>"
             data-category="<?= e($file['category']) ?>">
            
            <div class="md-file-item-header">
              <!-- Category Icon -->
              <span class="md-file-icon">
                <?php if ($file['category'] === 'llm'): ?>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><rect x="4" y="8" width="16" height="12" rx="2"/><circle cx="9" cy="13" r="1.5"/><circle cx="15" cy="13" r="1.5"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                <?php elseif ($file['is_blog']): ?>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <?php else: ?>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
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
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--border-sage-subtle)" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <h3 style="color: var(--text-white); margin: 1rem 0 0.4rem; font-family: var(--font-heading);">No File Selected</h3>
        <p style="color: var(--text-muted); font-size: 0.88rem; max-width: 380px; margin: 0 auto 1.5rem;">
          Select a markdown document from the explorer on the left, or create a new file to start drafting knowledge for AI models.
        </p>
        <div style="display: flex; gap: 0.75rem; justify-content: center;">
          <a href="<?= ADMIN_URL ?>/markdown/index.php?file=llms.txt" class="btn btn-outline-mint btn-sm">Edit llms.txt</a>
          <button type="button" class="btn btn-primary btn-sm" onclick="openModal('createModal')">Create New File</button>
        </div>
      </div>
    <?php else: 
      $publicViewUrl = SITE_URL . '/markdown/view.php?file=' . urlencode($activeFile['rel_path']);
      $renderedHtml = markdownToHtml($activeContent);
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

          <div style="font-size: 0.76rem; color: var(--text-muted); font-family: var(--font-mono); margin-top: 0.35rem;">
            Size: <?= round($activeFile['size'] / 1024, 2) ?> KB &bull; Modified: <?= date('M j, Y H:i:s', $activeFile['mtime']) ?>
          </div>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
          <!-- Public View Link -->
          <a href="<?= e($publicViewUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" title="Open public raw markdown view">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            <span>Public URL</span>
          </a>

          <!-- Edit in Blog CMS (if linked) -->
          <?php if ($activeFile['is_blog'] && $activeFile['blog_id']): ?>
            <a href="<?= ADMIN_URL ?>/posts/edit.php?id=<?= $activeFile['blog_id'] ?>" class="btn btn-outline-mint btn-sm" title="Edit this article inside the visual Blog Editor">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <span>Edit in Blog CMS</span>
            </a>
          <?php endif; ?>

          <!-- Delete File (if deletable) -->
          <?php if ($activeFile['is_deletable']): ?>
            <form method="POST" action="<?= ADMIN_URL ?>/markdown/index.php" style="margin: 0;" onsubmit="return confirm('Permanently delete <?= htmlspecialchars($activeFile['filename']) ?>?');">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete_file">
              <input type="hidden" name="file_key" value="<?= e($activeFile['key']) ?>">
              <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.25rem 0.6rem;" title="Delete file">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- View Switcher Tabs -->
      <div class="md-view-tabs">
        <button type="button" class="md-view-tab active" id="tabRawView" onclick="switchMdWorkspaceTab('raw')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          <span>Raw Markdown Editor</span>
        </button>
        <button type="button" class="md-view-tab" id="tabPreviewView" onclick="switchMdWorkspaceTab('preview')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <span>Formatted Preview</span>
        </button>
      </div>

      <!-- Form for Saving File Changes -->
      <form method="POST" action="<?= ADMIN_URL ?>/markdown/index.php" id="editMarkdownForm" style="display: flex; flex-direction: column; flex: 1;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_file">
        <input type="hidden" name="file_key" value="<?= e($activeFile['key']) ?>">

        <!-- Pane 1: Raw Markdown Editor -->
        <div id="paneRawEditor" class="md-editor-pane" style="display: flex; flex-direction: column; flex: 1;">
          <textarea id="rawMarkdownContent" name="content" class="md-raw-textarea" spellcheck="false" placeholder="Write or edit markdown..."><?= e($activeContent) ?></textarea>
        </div>

        <!-- Pane 2: Formatted HTML Preview -->
        <div id="paneFormattedPreview" class="md-preview-pane" style="display: none;">
          <div class="prose" id="previewHtmlContainer">
            <?= $renderedHtml ?>
          </div>
        </div>

        <!-- Bottom Action Strip -->
        <div class="md-footer-strip">
          <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <?php if ($activeFile['is_blog'] && $activeFile['blog_id']): ?>
              <label class="checkbox-toggle-label" style="font-size: 0.82rem; margin: 0; color: var(--text-primary);">
                <input type="checkbox" name="sync_to_blog" value="1" checked>
                <span><strong>Sync changes to Blog Post #<?= $activeFile['blog_id'] ?></strong> (updates title, excerpt &amp; body)</span>
              </label>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 0.65rem; margin-left: auto;">
            <button type="submit" class="btn btn-primary btn-sm">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              <span>Save Changes</span>
            </button>
          </div>
        </div>
      </form>

    <?php endif; ?>
  </main>

</div>

<!-- Modal 1: Create New Markdown File -->
<div id="createModal" class="editor-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="createModalTitle">
  <div class="editor-modal-card" style="max-width: 580px;">
    <div class="editor-modal-header">
      <h3 id="createModalTitle" class="editor-modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        <span>Create New Markdown File</span>
      </h3>
      <button type="button" class="editor-modal-close" onclick="closeModal('createModal')" aria-label="Close">&times;</button>
    </div>
    <form method="POST" action="<?= ADMIN_URL ?>/markdown/index.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create_file">
      
      <div class="editor-modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label for="createCategory" class="form-label">Category / Directory <span class="required">*</span></label>
            <select id="createCategory" name="category" class="form-control" required>
              <option value="pages">Pages (markdown/pages/)</option>
              <option value="case-studies">Case Studies (markdown/case-studies/)</option>
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
          <label for="createContent" class="form-label">Initial Content (Markdown)</label>
          <textarea id="createContent" name="content" class="form-control" style="min-height: 140px; font-family: var(--font-mono); font-size: 0.85rem;" placeholder="Start writing markdown content..."></textarea>
        </div>
      </div>

      <div class="editor-modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Create Document</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 2: Upload Markdown File -->
<div id="uploadModal" class="editor-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
  <div class="editor-modal-card" style="max-width: 520px;">
    <div class="editor-modal-header">
      <h3 id="uploadModalTitle" class="editor-modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span>Upload Markdown File</span>
      </h3>
      <button type="button" class="editor-modal-close" onclick="closeModal('uploadModal')" aria-label="Close">&times;</button>
    </div>
    <form method="POST" action="<?= ADMIN_URL ?>/markdown/index.php" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="upload_file">
      
      <div class="editor-modal-body">
        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="uploadCategory" class="form-label">Target Category / Directory</label>
          <select id="uploadCategory" name="category" class="form-control" required>
            <option value="pages">Pages (markdown/pages/)</option>
            <option value="case-studies">Case Studies (markdown/case-studies/)</option>
            <option value="other" selected>Other (markdown/other/)</option>
            <option value="blogs">Blogs (markdown/blogs/)</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label for="uploadFile" class="form-label">Select .md or .txt File <span class="required">*</span></label>
          <input type="file" id="uploadFile" name="md_file" class="form-control" accept=".md,.txt,.markdown" required style="cursor: pointer;">
          <div class="form-hint">Supports Markdown files (.md, .txt) with UTF-8 encoding.</div>
        </div>
      </div>

      <div class="editor-modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('uploadModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Upload File</button>
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

  if (tab === 'preview') {
    tabRaw.classList.remove('active');
    tabPreview.classList.add('active');
    paneRaw.style.display = 'none';
    panePreview.style.display = 'block';

    // Live update preview from current textarea content
    const rawVal = document.getElementById('rawMarkdownContent').value;
    document.getElementById('previewHtmlContainer').innerHTML = clientMarkdownToHtml(rawVal);
  } else {
    tabPreview.classList.remove('active');
    tabRaw.classList.add('active');
    panePreview.style.display = 'none';
    paneRaw.style.display = 'flex';
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
  const searchVal = document.getElementById('fileSearchInput').value.toLowerCase().trim();
  const items = document.querySelectorAll('.md-file-item');

  items.forEach(item => {
    const itemName = item.getAttribute('data-name') || '';
    const itemCat = item.getAttribute('data-category') || '';

    const matchesSearch = !searchVal || itemName.includes(searchVal);
    const matchesCat = (currentCategoryFilter === 'all') || (itemCat === currentCategoryFilter);

    if (matchesSearch && matchesCat) {
      item.style.display = 'flex';
    } else {
      item.style.display = 'none';
    }
  });
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('open');
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('open');
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
    if (/^<(h[1-6]|ul|ol|blockquote|hr|div)/i.test(p)) return p;
    return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
  }).join('\n');
}
</script>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>

