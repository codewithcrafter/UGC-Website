<?php
/**
 * MY UGC VIDEO — Edit Existing Blog Article with Toggle-Based Full-Width Preview
 * Premium Dark Editorial CMS
 */

define('CMS_ROOT', dirname(dirname(__DIR__)));
require_once CMS_ROOT . '/config/config.php';
requireAuth();

$activeNav = 'posts';
$pageTitle = 'Edit Blog Article';

$db = getDb();
$postId = (int) ($_GET['id'] ?? 0);

if ($postId <= 0) {
    setFlash('error', 'Invalid article ID.');
    header('Location: ' . ADMIN_URL . '/posts');
    exit;
}

$stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = :id");
$stmt->execute([':id' => $postId]);
$post = $stmt->fetch();

if (!$post) {
    setFlash('error', 'Article not found.');
    header('Location: ' . ADMIN_URL . '/posts');
    exit;
}

$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard'],
    ['name' => 'All Articles', 'url' => ADMIN_URL . '/posts'],
    ['name' => 'Edit: ' . mb_strimwidth($post['title'], 0, 24, '...'), 'url' => ADMIN_URL . '/posts/edit?id=' . $postId]
];

$categories = getAllCategories();
$errors = [];

$title = $post['title'];
$slug = $post['slug'];
$categoryId = $post['category_id'];
$status = $post['status'];
$isFeatured = $post['is_featured'];
$excerpt = $post['excerpt'];
$content = $post['content'];
$featuredImage = $post['featured_image'];
$imageAlt = $post['featured_image_alt'];
$authorName = $post['author_name'];
$authorRole = $post['author_role'];
$authorBio = $post['author_bio'];
$readingTime = $post['reading_time'];
$publishedAt = date('Y-m-d\TH:i', strtotime($post['published_at']));
$metaTitle = $post['meta_title'];
$metaDescription = $post['meta_description'];
$canonicalUrl = $post['canonical_url'];
$keywords = $post['keywords'];

$rawSchemaType = $post['schema_type'] ?? 'recommended';
$schemaType = ($rawSchemaType === 'upload' || $rawSchemaType === 'custom') ? 'upload' : 'recommended';
$customSchema = $post['custom_schema'] ?? '';
$schemaFilename = $post['schema_filename'] ?? '';
$faqsJson = $post['faqs_json'] ?? '[]';
$navigationJson = $post['navigation_json'] ?? '[]';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'draft';
    $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $imageAlt = trim($_POST['image_alt'] ?? '');
    $authorName = trim($_POST['author_name'] ?? 'MY UGC VIDEO Editorial Team');
    $authorRole = trim($_POST['author_role'] ?? 'UGC Creative Studio');
    $authorBio = trim($_POST['author_bio'] ?? '');
    $readingTime = trim($_POST['reading_time'] ?? '');
    $publishedAtInput = trim($_POST['published_at'] ?? '');
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $canonicalUrl = trim($_POST['canonical_url'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');

    $rawSchemaType = $_POST['schema_type'] ?? 'recommended';
    $schemaType = ($rawSchemaType === 'upload' || $rawSchemaType === 'custom') ? 'upload' : 'recommended';
    $customSchema = trim($_POST['custom_schema'] ?? '');
    $schemaFilename = trim($_POST['schema_filename'] ?? '');
    $faqsJson = trim($_POST['faqs_json'] ?? '[]');
    $navigationJson = trim($_POST['navigation_json'] ?? '[]');

    if (empty($title)) {
        $errors[] = 'Article title is required.';
    }

    if (empty($slug)) {
        $slug = slugify($title);
    } else {
        $slug = slugify($slug);
    }

    // Check slug uniqueness excluding this post
    $checkStmt = $db->prepare("SELECT id FROM blog_posts WHERE slug = :slug AND id != :id");
    $checkStmt->execute([':slug' => $slug, ':id' => $postId]);
    if ($checkStmt->fetchColumn()) {
        $errors[] = 'The URL slug "' . $slug . '" is already in use by another article. Please modify it.';
    }

    if (empty($content)) {
        $errors[] = 'Article content cannot be empty.';
    }

    // Handle Uploaded Schema JSON File if provided
    if (!empty($_FILES['schema_upload']['name'])) {
        $schemaRes = validateUploadedSchemaFile($_FILES['schema_upload']);
        if ($schemaRes['success']) {
            $customSchema = $schemaRes['clean_json'];
            $schemaFilename = $schemaRes['orig_filename'];
            $schemaType = 'upload';
        } else {
            $errors[] = 'Schema File Error: ' . $schemaRes['error'];
        }
    } elseif ($schemaType === 'upload') {
        if (empty($customSchema)) {
            $errors[] = 'Upload JSON File mode requires a valid .json file. Please upload a JSON schema or select Recommended mode.';
        } else {
            $valRes = validateJsonLd($customSchema);
            if (!$valRes['valid']) {
                $errors[] = 'Uploaded Schema Error: ' . $valRes['error'];
            }
        }
    }

    if (empty($readingTime)) {
        $readingTime = estimateReadingTime($content);
    }

    $publishedAtSql = !empty($publishedAtInput) ? date('Y-m-d H:i:s', strtotime($publishedAtInput)) : $post['published_at'];

    // Handle Image Replacement If Uploaded
    if (!empty($_FILES['featured_image']['name'])) {
        $uploadRes = handleImageUpload($_FILES['featured_image']);
        if ($uploadRes['success']) {
            $featuredImage = $uploadRes['path'];
        } else {
            $errors[] = 'Featured Image Error: ' . $uploadRes['error'];
        }
    }

    if (empty($errors)) {
        // Save current version into revisions table for history
        $currentAdmin = getCurrentAdmin();
        $revStmt = $db->prepare(
            "INSERT INTO blog_post_revisions (post_id, admin_id, title, content, created_at) 
             VALUES (:post_id, :admin_id, :title, :content, NOW())"
        );
        $revStmt->execute([
            ':post_id'  => $postId,
            ':admin_id' => $currentAdmin['id'] ?? null,
            ':title'    => $post['title'],
            ':content'  => $post['content']
        ]);

        if ($isFeatured) {
            $unfeat = $db->prepare("UPDATE blog_posts SET is_featured = 0 WHERE id != :id");
            $unfeat->execute([':id' => $postId]);
        }

        if (empty($canonicalUrl) || str_contains($canonicalUrl, 'example.com') || str_contains($canonicalUrl, 'demo.myugcvideo') || str_ends_with($canonicalUrl, '.html') || str_contains($canonicalUrl, 'blog.php')) {
            $canonicalUrl = 'https://myugcvideo.in/blog/' . $slug;
        }

        $update = $db->prepare(
            "UPDATE blog_posts SET 
                title = :title,
                slug = :slug,
                excerpt = :excerpt,
                content = :content,
                featured_image = :featured_image,
                featured_image_alt = :featured_image_alt,
                author_name = :author_name,
                author_role = :author_role,
                author_bio = :author_bio,
                category_id = :category_id,
                status = :status,
                is_featured = :is_featured,
                published_at = :published_at,
                reading_time = :reading_time,
                schema_type = :schema_type,
                custom_schema = :custom_schema,
                schema_filename = :schema_filename,
                faqs_json = :faqs_json,
                navigation_json = :navigation_json,
                meta_title = :meta_title,
                meta_description = :meta_description,
                canonical_url = :canonical_url,
                keywords = :keywords,
                updated_at = NOW()
             WHERE id = :id"
        );

        $update->execute([
            ':title'              => $title,
            ':slug'               => $slug,
            ':excerpt'            => $excerpt,
            ':content'            => $content,
            ':featured_image'     => $featuredImage,
            ':featured_image_alt' => $imageAlt,
            ':author_name'        => $authorName,
            ':author_role'        => $authorRole,
            ':author_bio'         => $authorBio,
            ':category_id'        => $categoryId,
            ':status'             => $status,
            ':is_featured'        => $isFeatured,
            ':published_at'       => $publishedAtSql,
            ':reading_time'       => $readingTime,
            ':schema_type'        => $schemaType,
            ':custom_schema'      => $customSchema,
            ':schema_filename'    => $schemaFilename,
            ':faqs_json'          => $faqsJson,
            ':navigation_json'    => $navigationJson,
            ':meta_title'         => $metaTitle,
            ':meta_description'   => $metaDescription,
            ':canonical_url'      => $canonicalUrl,
            ':keywords'           => $keywords,
            ':id'                 => $postId
        ]);

        // Automatically synchronize to Markdown & BlogPosting Schema
        $oldSlug = $post['slug'];
        $syncRes = syncBlogPostArtifacts((int) $postId, $oldSlug);
        $syncNote = $syncRes['success'] ? ' & Markdown/Schema synchronized' : '';

        // Sync blogs.md listing
        if (function_exists('syncBlogsMd')) {
            syncBlogsMd();
        }

        // Automatically regenerate discovery systems & notify engines
        $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
        $postUrl = $canonicalBase . '/blog/' . urlencode($slug);
        if (function_exists('runFullReconciliation')) {
            $changed = ($status === 'published') ? [$postUrl, $canonicalBase . '/blogs'] : [$canonicalBase . '/blogs'];
            runFullReconciliation(['changed_urls' => $changed]);
        } else {
            syncLlmsTxt();
        }

        setFlash('success', 'Article "' . $title . '" updated successfully' . $syncNote . ' & LLM documentation synchronized!');
        header('Location: ' . ADMIN_URL . '/posts/edit?id=' . $postId);
        exit;
    }
}

// Prepare preview URL for initial image
$initialHeroUrl = '';
if (!empty($featuredImage)) {
    $initialHeroUrl = str_starts_with($featuredImage, 'http') ? $featuredImage : SITE_URL . '/' . ltrim($featuredImage, '/');
}
$publicArticleUrl = SITE_URL . '/blog/' . urlencode($slug);

include CMS_ROOT . '/admin/includes/header.php';
?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger" style="display: block;">
    <strong>Please resolve the following errors:</strong>
    <ul style="margin: 0.5rem 0 0 1.25rem;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Top Workspace Bar (Toggle-Based Action Header) -->
<div class="split-workspace-bar">
  
  <!-- 1. Controls shown when in Editor Mode (Default) -->
  <div id="editorBarControls" style="display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 1rem; flex-wrap: wrap;">
    <div class="workspace-left-status">
      <span class="status-pill <?= $status === 'published' ? 'published' : 'draft' ?>">
        <?= ucfirst($status) ?>
      </span>
      <?php if ($isFeatured): ?>
        <span class="badge-featured">★ Hero Spotlight</span>
      <?php endif; ?>
      <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Full Workspace Editor</span>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <!-- Clearly Visible Preview Button -->
      <button type="button" class="btn btn-outline-mint btn-sm" id="btnOpenPreview" onclick="togglePreviewMode(true)" title="Open Full-Width Live Preview">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        <span>Preview</span>
      </button>

      <a href="<?= e($publicArticleUrl) ?>" target="_blank" class="btn btn-secondary btn-sm" title="View Public Page">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" style="width: 14px; height: 14px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline></svg>
        <span>Live URL</span>
      </a>

      <button type="button" class="btn btn-secondary btn-sm" onclick="submitAsStatus('draft')">
        Save Draft
      </button>

      <button type="button" class="btn btn-primary btn-sm" onclick="submitAsStatus('published')">
        Save &amp; Update &rarr;
      </button>
    </div>
  </div>

  <!-- 2. Controls shown when in Preview Mode -->
  <div id="previewBarControls" style="display: none; align-items: center; justify-content: space-between; width: 100%; gap: 1rem; flex-wrap: wrap;">
    <div class="workspace-left-status">
      <!-- Clearly Visible Back to Editor Button -->
      <button type="button" class="btn btn-primary btn-sm" id="btnBackToEditor" onclick="togglePreviewMode(false)">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <span>&larr; Back to Editor</span>
      </button>

      <div class="sync-badge">
        <span class="sync-dot"></span>
        <span>Full-Width Live Preview</span>
      </div>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <!-- Device Viewport Simulator Controls -->
      <div class="device-switcher" title="Simulate Screen Width in Live Preview">
        <button type="button" class="device-btn active" onclick="setDeviceView('desktop', this)">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
          <span>Desktop</span>
        </button>
        <button type="button" class="device-btn" onclick="setDeviceView('tablet', this)">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
          <span>Tablet</span>
        </button>
        <button type="button" class="device-btn" onclick="setDeviceView('mobile', this)">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
          <span>Mobile</span>
        </button>
      </div>

      <button type="button" class="btn btn-secondary btn-sm" onclick="submitAsStatus('draft')">
        Save Draft
      </button>

      <button type="button" class="btn btn-primary btn-sm" onclick="submitAsStatus('published')">
        Save &amp; Update &rarr;
      </button>
    </div>
  </div>

</div>

<!-- WORKSPACE 1: FULL-WIDTH EDITOR (Default) -->
<div id="editorWorkspace" class="editor-workspace">
  <form id="postForm" method="POST" action="" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" id="formStatus" name="status" value="<?= e($status) ?>">

    <!-- Main Content Panel (Spacious Full Width) -->
    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
          <span>Article Editorial Details</span>
        </h2>
        <button type="button" class="btn btn-outline-mint btn-sm" onclick="togglePreviewMode(true)">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          <span>Preview Article</span>
        </button>
      </div>
      <div class="panel-body">
        <div class="form-group">
          <label for="title" class="form-label">Article Title <span class="required">*</span></label>
          <input type="text" id="title" name="title" class="form-control" value="<?= e($title) ?>" placeholder="e.g. 10 High-Converting UGC Video Ad Hooks for 2026" required oninput="onTitleInput(this.value)">
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
            <label for="slug" class="form-label" style="margin-bottom: 0;">URL Slug <span class="required">*</span></label>
            <button type="button" id="slugLockBtn" onclick="toggleSlugLock()" class="btn btn-secondary btn-sm" style="padding: 0.15rem 0.5rem; font-size: 0.72rem;">
              <span id="slugLockLabel">Unlock Custom Slug</span>
            </button>
          </div>
          <input type="text" id="slug" name="slug" class="form-control" value="<?= e($slug) ?>" placeholder="e.g. 10-high-converting-ugc-hooks" oninput="onSlugInput(this.value)">
          <div class="form-hint">Public URL: <span id="slugUrlPreview" style="color: var(--accent-mint); font-family: var(--font-mono); font-size: 0.8rem;"><?= SITE_URL ?>/blog/<?= e($slug ?: 'article-slug') ?>/</span></div>
        </div>

        <div class="form-group">
          <label for="excerpt" class="form-label">Lead Excerpt / Summary</label>
          <textarea id="excerpt" name="excerpt" class="form-control" style="min-height: 80px;" placeholder="Introductory hook or key summary displayed prominently beneath the article title..." oninput="updateLivePreview()"><?= e($excerpt) ?></textarea>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
            <label class="form-label" style="margin-bottom: 0;">Article Body Content <span class="required">*</span></label>
            <span style="font-size: 0.75rem; color: var(--text-muted);" id="wordCountDisplay">0 words</span>
          </div>

          <!-- Editorial Mode Navigation (Visual Editor vs Markdown) -->
          <div class="editor-mode-nav">
            <button type="button" class="mode-tab active" id="tabVisualMode" onclick="switchBodyEditorMode('visual')">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <span>Visual Document Editor</span>
            </button>
            <button type="button" class="mode-tab" id="tabMarkdownMode" onclick="switchBodyEditorMode('markdown')">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M7 15V9l3 3 3-3v6"/><path d="M17 9v6l-2-2"/><path d="M17 15l2-2"/></svg>
              <span>Markdown Editor &amp; Import</span>
            </button>
          </div>

          <!-- 1. Document-Style Visual Editor Wrapper -->
          <div id="visualEditorWrapper" class="visual-editor-wrapper">
            <!-- Visual Formatting Toolbar -->
            <div class="visual-editor-toolbar" role="toolbar" aria-label="Visual Text Formatting">
              <!-- Undo / Redo -->
              <div class="toolbar-btn-group">
                <button type="button" class="toolbar-btn" onclick="execCmd('undo')" title="Undo (Ctrl+Z)" aria-label="Undo">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="execCmd('redo')" title="Redo (Ctrl+Y)" aria-label="Redo">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
                </button>
              </div>

              <span class="toolbar-separator" aria-hidden="true"></span>

              <!-- Paragraph & Headings -->
              <div class="toolbar-btn-group">
                <button type="button" class="toolbar-btn toolbar-btn-text" data-block="p" onclick="formatBlockCommand('p')" title="Normal Paragraph" aria-label="Paragraph">
                  P
                </button>
                <button type="button" class="toolbar-btn toolbar-btn-text" data-block="h2" onclick="formatBlockCommand('h2')" title="Heading 2 (Major Section)" aria-label="Heading 2">
                  H2
                </button>
                <button type="button" class="toolbar-btn toolbar-btn-text" data-block="h3" onclick="formatBlockCommand('h3')" title="Heading 3 (Sub-section)" aria-label="Heading 3">
                  H3
                </button>
              </div>

              <span class="toolbar-separator" aria-hidden="true"></span>

              <!-- Text Styling: Bold, Italic, Underline -->
              <div class="toolbar-btn-group">
                <button type="button" class="toolbar-btn" data-cmd="bold" onclick="execCmd('bold')" title="Bold (Ctrl+B)" aria-label="Bold">
                  <strong>B</strong>
                </button>
                <button type="button" class="toolbar-btn" data-cmd="italic" onclick="execCmd('italic')" title="Italic (Ctrl+I)" aria-label="Italic">
                  <em style="font-style: italic; font-family: serif;">I</em>
                </button>
                <button type="button" class="toolbar-btn" data-cmd="underline" onclick="execCmd('underline')" title="Underline (Ctrl+U)" aria-label="Underline">
                  <u style="text-decoration: underline;">U</u>
                </button>
              </div>

              <span class="toolbar-separator" aria-hidden="true"></span>

              <!-- Lists & Quote -->
              <div class="toolbar-btn-group">
                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList" onclick="execCmd('insertUnorderedList')" title="Bulleted List" aria-label="Bulleted List">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
                </button>
                <button type="button" class="toolbar-btn" data-cmd="insertOrderedList" onclick="execCmd('insertOrderedList')" title="Numbered List" aria-label="Numbered List">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                </button>
                <button type="button" class="toolbar-btn" data-block="blockquote" onclick="formatBlockCommand('blockquote')" title="Blockquote" aria-label="Blockquote">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179z"/></svg>
                </button>
              </div>

              <span class="toolbar-separator" aria-hidden="true"></span>

              <!-- Working Link Dialog & Unlink -->
              <div class="toolbar-btn-group">
                <button type="button" class="toolbar-btn" onclick="openLinkModal()" title="Insert or Edit Hyperlink (Ctrl+K)" aria-label="Insert Link">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="execCmd('unlink')" title="Remove Link" aria-label="Remove Link">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M5.16 11.75l-1.72 1.71a5 5 0 0 0 7.07 7.07l1.72-1.71"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                </button>
              </div>

              <span class="toolbar-separator" aria-hidden="true"></span>

              <!-- Visual Table Insertion Tool -->
              <div class="toolbar-btn-group">
                <button type="button" class="toolbar-btn" onclick="openInsertTableModal()" title="Insert Table" aria-label="Insert Table">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                </button>
              </div>

              <span class="toolbar-separator" aria-hidden="true"></span>

              <!-- Extra editorial block -->
              <div class="toolbar-btn-group">
                <button type="button" class="toolbar-btn toolbar-pill-btn" onclick="insertTakeawayBox()" title="Insert Callout Card">
                  + Key Takeaway
                </button>
              </div>
            </div>

            <!-- Contextual Table Editing Toolbar (Revealed when focused inside a table) -->
            <div id="tableContextBar" class="table-context-bar" style="display: none;">
              <span style="font-weight: 700; color: var(--accent-mint); display: inline-flex; align-items: center; gap: 0.3rem; margin-right: 0.35rem;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                TABLE
              </span>
              <button type="button" class="table-context-btn" onclick="tableAddRow('above')" title="Insert row above current row">+ Row Above</button>
              <button type="button" class="table-context-btn" onclick="tableAddRow('below')" title="Insert row below current row">+ Row Below</button>
              <button type="button" class="table-context-btn" onclick="tableAddCol('left')" title="Insert column to the left">+ Col Left</button>
              <button type="button" class="table-context-btn" onclick="tableAddCol('right')" title="Insert column to the right">+ Col Right</button>
              <button type="button" class="table-context-btn" onclick="tableToggleHeader()" title="Toggle table header row">Toggle Header</button>
              <button type="button" class="table-context-btn btn-danger-subtle" onclick="tableDeleteRow()" title="Delete current row">&minus; Del Row</button>
              <button type="button" class="table-context-btn btn-danger-subtle" onclick="tableDeleteCol()" title="Delete current column">&minus; Del Col</button>
              <button type="button" class="table-context-btn btn-danger-subtle" onclick="tableDeleteEntire()" title="Permanently delete entire table" style="font-weight: 700;">&times; Delete Table</button>
            </div>

            <!-- Clean visual document writing interface -->
            <div id="visualEditor"
                 class="visual-document-editor"
                 contenteditable="true"
                 role="textbox"
                 aria-multiline="true"
                 spellcheck="true"
                 data-placeholder="Start typing your editorial article body here... Use the toolbar above to add headings, bullet points, quotes, and links."><?= $content ?></div>
          </div>

          <!-- 2. Markdown Writing & Import Workspace (Write, Preview, Split) -->
          <div id="markdownWorkspace" class="markdown-workspace">
            <div class="markdown-toolbar">
              <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <div class="markdown-view-toggle">
                  <button type="button" class="md-view-btn active" id="btnMdWrite" onclick="setMdViewMode('write')">Write</button>
                  <button type="button" class="md-view-btn" id="btnMdPreview" onclick="setMdViewMode('preview')">Preview</button>
                  <button type="button" class="md-view-btn" id="btnMdSplit" onclick="setMdViewMode('split')">Split</button>
                </div>

                <!-- Upload Markdown File -->
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('mdFileInput').click()" style="padding: 0.25rem 0.65rem; font-size: 0.76rem;">
                  <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                  <span>Upload .md File</span>
                </button>
                <input type="file" id="mdFileInput" accept=".md,.markdown,text/markdown" style="display: none;" onchange="handleMdFileUpload(event)">
              </div>

              <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span id="mdMetricsDisplay" style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">0 words &bull; 0 chars</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="applyMarkdownToArticle('append')" style="padding: 0.25rem 0.65rem; font-size: 0.76rem;">
                  + Append to Body
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="applyMarkdownToArticle('replace')" style="padding: 0.25rem 0.65rem; font-size: 0.76rem;">
                  Apply (Replace Body) &rarr;
                </button>
              </div>
            </div>

            <div id="mdSplitContainer" class="markdown-split-container view-write">
              <div class="markdown-editor-pane">
                <textarea id="markdownInput" class="markdown-textarea" placeholder="Type Markdown here (# Headings, **bold**, - lists, > quotes, [links](url)...)" oninput="onMarkdownInput()"></textarea>
              </div>
              <div id="markdownPreview" class="markdown-preview-pane">
                <p style="color: var(--text-muted); font-style: italic;">Markdown preview will render here...</p>
              </div>
            </div>
          </div>

          <!-- Hidden textarea for native POST submission -->
          <textarea id="content" name="content" style="display: none;" required><?= e($content) ?></textarea>
        </div>
      </div>
    </div>

    <!-- 1. Schema Markup Card (Must appear before FAQ Builder) -->
    <div class="panel" style="margin-top: 1.5rem;">
      <div class="panel-header">
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
          <h2 class="panel-title" style="margin-bottom: 0;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            <span>Schema Markup</span>
          </h2>
          <span id="schemaStatusBadge" class="schema-status-badge badge-active">Active &amp; Validated</span>
        </div>
      </div>
      <div class="panel-body">
        <p class="form-hint" style="margin-top: 0; margin-bottom: 1.25rem;">
          Select the structured data mode for this blog article. Recommended mode automatically generates verified Schema.org JSON-LD, or you can upload a custom JSON file.
        </p>

        <!-- Two Options Only: Mode Selector -->
        <div style="display: flex; gap: 1.75rem; margin-bottom: 1.25rem; flex-wrap: wrap;">
          <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-white); font-weight: 600; font-size: 0.9rem;">
            <input type="radio" id="schemaModeRecommended" name="schema_type" value="recommended" <?= ($schemaType ?? 'recommended') !== 'upload' ? 'checked' : '' ?> onchange="toggleSchemaMode('recommended')">
            <span>Option A: Recommended (Default)</span>
          </label>
          <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-white); font-weight: 600; font-size: 0.9rem;">
            <input type="radio" id="schemaModeUpload" name="schema_type" value="upload" <?= ($schemaType ?? 'recommended') === 'upload' ? 'checked' : '' ?> onchange="toggleSchemaMode('upload')">
            <span>Option B: Upload JSON File</span>
          </label>
        </div>

        <!-- Option A: Recommended View -->
        <div id="recommendedSchemaSection" style="<?= ($schemaType ?? 'recommended') === 'upload' ? 'display: none;' : '' ?>">
          
          <!-- Schema Type Checkboxes -->
          <div class="schema-checkboxes-row" style="display: flex; flex-wrap: wrap; gap: 1.25rem; margin: 0.75rem 0 1rem; padding: 0.75rem 1rem; background: #001411; border: 1px solid var(--border-sage-subtle); border-radius: var(--radius-sm);">
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-white); font-size: 0.88rem; font-weight: 500;">
              <input type="checkbox" id="schemaCheckOrg" class="schema-checkbox" value="organization" onchange="handleSchemaCheckboxChange(this)">
              <span>Organization Schema</span>
            </label>
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-white); font-size: 0.88rem; font-weight: 500;">
              <input type="checkbox" id="schemaCheckBreadcrumb" class="schema-checkbox" value="breadcrumb" onchange="handleSchemaCheckboxChange(this)">
              <span>Breadcrumb Schema</span>
            </label>
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-white); font-size: 0.88rem; font-weight: 500;">
              <input type="checkbox" id="schemaCheckFaq" class="schema-checkbox" value="faq" onchange="handleSchemaCheckboxChange(this)">
              <span>FAQ Schema</span>
            </label>
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-white); font-size: 0.88rem; font-weight: 500;">
              <input type="checkbox" id="schemaCheckBlog" class="schema-checkbox" value="blog" onchange="handleSchemaCheckboxChange(this)">
              <span>Blog Schema</span>
            </label>
          </div>

          <!-- Individual Schema Editors (Toggled by Checkboxes) -->
          <div id="schemaEditorsContainer">
            <!-- 1. Organization Schema Editor -->
            <div id="schemaEditorOrgContainer" class="schema-editor-pane" style="display: none; margin-bottom: 1rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <span style="font-size: 0.74rem; font-family: var(--font-mono); color: var(--accent-mint); font-weight: 600;">Organization Schema Editor (JSON-LD)</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copySchemaEditorText('schemaEditorOrg')" style="padding: 0.15rem 0.5rem; font-size: 0.72rem;">
                  Copy JSON
                </button>
              </div>
              <textarea id="schemaEditorOrg" class="form-control" style="font-family: var(--font-mono); font-size: 0.8rem; height: 180px; width: 100%; line-height: 1.4; background: #000a08; color: #a5d6a7;" oninput="this.dataset.userEdited = 'true';"></textarea>
            </div>

            <!-- 2. Breadcrumb Schema Editor -->
            <div id="schemaEditorBreadcrumbContainer" class="schema-editor-pane" style="display: none; margin-bottom: 1rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <span style="font-size: 0.74rem; font-family: var(--font-mono); color: var(--accent-mint); font-weight: 600;">Breadcrumb Schema Editor (JSON-LD)</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copySchemaEditorText('schemaEditorBreadcrumb')" style="padding: 0.15rem 0.5rem; font-size: 0.72rem;">
                  Copy JSON
                </button>
              </div>
              <textarea id="schemaEditorBreadcrumb" class="form-control" style="font-family: var(--font-mono); font-size: 0.8rem; height: 180px; width: 100%; line-height: 1.4; background: #000a08; color: #a5d6a7;" oninput="this.dataset.userEdited = 'true';"></textarea>
            </div>

            <!-- 3. FAQ Schema Editor -->
            <div id="schemaEditorFaqContainer" class="schema-editor-pane" style="display: none; margin-bottom: 1rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <span style="font-size: 0.74rem; font-family: var(--font-mono); color: var(--accent-mint); font-weight: 600;">FAQ Schema Editor (JSON-LD)</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copySchemaEditorText('schemaEditorFaq')" style="padding: 0.15rem 0.5rem; font-size: 0.72rem;">
                  Copy JSON
                </button>
              </div>
              <textarea id="schemaEditorFaq" class="form-control" style="font-family: var(--font-mono); font-size: 0.8rem; height: 180px; width: 100%; line-height: 1.4; background: #000a08; color: #a5d6a7;" oninput="this.dataset.userEdited = 'true';"></textarea>
            </div>

            <!-- 4. Blog Schema Editor -->
            <div id="schemaEditorBlogContainer" class="schema-editor-pane" style="display: none; margin-bottom: 1rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <span style="font-size: 0.74rem; font-family: var(--font-mono); color: var(--accent-mint); font-weight: 600;">Blog Schema Editor (JSON-LD)</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copySchemaEditorText('schemaEditorBlog')" style="padding: 0.15rem 0.5rem; font-size: 0.72rem;">
                  Copy JSON
                </button>
              </div>
              <textarea id="schemaEditorBlog" class="form-control" style="font-family: var(--font-mono); font-size: 0.8rem; height: 220px; width: 100%; line-height: 1.4; background: #000a08; color: #a5d6a7;" oninput="this.dataset.userEdited = 'true';"></textarea>
            </div>
          </div>

            <div style="position: relative;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <span style="font-size: 0.74rem; font-family: var(--font-mono); color: var(--text-muted);">Generated Schema JSON-LD (Read-Only Preview)</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyAutoSchemaJson()" style="padding: 0.15rem 0.5rem; font-size: 0.72rem;">
                  Copy JSON
                </button>
              </div>
              <div id="autoSchemaPreview" class="schema-code-box"></div>
            </div>
          </div>
        </div>

        <!-- Option B: Upload JSON File View -->
        <div id="uploadSchemaSection" style="<?= ($schemaType ?? 'recommended') === 'upload' ? '' : 'display: none;' ?>">
          
          <!-- Validation Alert Box -->
          <div id="schemaUploadAlert" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.85rem; font-family: var(--font-mono); background: rgba(255, 82, 82, 0.15); color: #ff5252; border: 1px solid rgba(255, 82, 82, 0.4);"></div>

          <!-- File Card (displayed if a validated file exists) -->
          <div id="uploadedSchemaCard" class="schema-file-card" style="<?= !empty($customSchema) ? 'display: flex;' : 'display: none;' ?>">
            <div class="schema-file-info">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-mint)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              <div>
                <div id="schemaFileNameDisplay" style="font-weight: 700; color: #fff; font-size: 0.9rem;"><?= e($schemaFilename ?: 'custom-schema.json') ?></div>
                <div style="font-size: 0.74rem; color: var(--text-muted); display: flex; gap: 0.5rem; align-items: center; margin-top: 0.2rem;">
                  <span id="schemaFileSizeDisplay"><?= !empty($customSchema) ? round(strlen($customSchema) / 1024, 1) . ' KB' : '0 KB' ?></span>
                  <span>&bull;</span>
                  <span style="color: var(--accent-mint); font-weight: 600;">Validated JSON-LD</span>
                </div>
              </div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
              <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('schema_file_input').click()">
                Replace File
              </button>
              <button type="button" class="table-context-btn btn-danger-subtle" onclick="removeUploadedSchema()" style="padding: 0.35rem 0.65rem;">
                Remove
              </button>
            </div>
          </div>

          <!-- Dropzone for Uploading JSON -->
          <div id="schemaDropzone" class="schema-dropzone" style="<?= !empty($customSchema) ? 'display: none;' : 'display: block;' ?>" onclick="document.getElementById('schema_file_input').click()">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--accent-mint)" stroke-width="2" style="margin-bottom: 0.5rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div style="font-weight: 700; color: #fff; font-size: 0.92rem; margin-bottom: 0.25rem;">Click to upload JSON file (.json)</div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">Must contain Schema.org structured data (max 500KB)</div>
          </div>

          <input type="file" id="schema_file_input" name="schema_upload" accept=".json,application/json" style="display: none;" onchange="handleSchemaFileUpload(event)">
          <input type="hidden" id="custom_schema" name="custom_schema" value="<?= e($customSchema ?? '') ?>">
          <input type="hidden" id="schema_filename" name="schema_filename" value="<?= e($schemaFilename ?? '') ?>">

          <!-- Formatted Preview of Uploaded Schema -->
          <div id="uploadedSchemaPreviewContainer" style="<?= !empty($customSchema) ? 'display: block;' : 'display: none;' ?>">
            <div style="font-size: 0.74rem; font-family: var(--font-mono); color: var(--text-muted); margin-bottom: 0.4rem;">Uploaded Schema JSON-LD (Formatted Preview)</div>
            <div id="uploadedSchemaPreview" class="schema-code-box"><?= e($customSchema ?? '') ?></div>
          </div>

        </div>

      </div>
    </div>

    <!-- 2. Frequently Asked Questions (FAQ Builder) Card (Must appear after Schema Markup) -->
    <div class="panel" style="margin-top: 1.5rem;">
      <div class="panel-header">
        <h2 class="panel-title">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
          <span>Frequently Asked Questions (FAQ Builder)</span>
        </h2>
        <button type="button" class="btn btn-outline-mint btn-sm" onclick="addNewFaqItem()">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          <span>Add FAQ</span>
        </button>
      </div>
      <div class="panel-body">
        <p class="form-hint" style="margin-top: 0; margin-bottom: 1rem;">
          Add relevant questions and answers for your readers. These will be displayed on the article and automatically generated into verified Google FAQPage structured data.
        </p>

        <div id="faqItemsContainer" class="faq-items-list">
          <!-- Dynamic FAQ cards injected here -->
        </div>

        <input type="hidden" id="faqs_json" name="faqs_json" value="<?= e($faqsJson ?? '[]') ?>">
      </div>
    </div>

    <!-- Secondary Settings Grid (Spacious 2-column on desktop) -->
    <div class="editor-secondary-grid">

      <!-- Left Column: Publishing & Author -->
      <div>
        <!-- Publishing & Categorization Card -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
              <span>Publishing &amp; Categorization</span>
            </h2>
          </div>
          <div class="panel-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
              <div class="form-group">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-control" onchange="updateLivePreview()">
                  <option value="">Select Category...</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                      <?= e($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="published_at" class="form-label">Publication Date</label>
                <input type="datetime-local" id="published_at" name="published_at" class="form-control" value="<?= e($publishedAt) ?>" onchange="updateLivePreview()">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
              <div class="form-group">
                <label for="reading_time" class="form-label">Reading Time</label>
                <input type="text" id="reading_time" name="reading_time" class="form-control" value="<?= e($readingTime) ?>" placeholder="e.g. 7 Min Read" oninput="updateLivePreview()">
                <div class="form-hint">Auto-calculated from word count if blank</div>
              </div>

              <div class="form-group" style="padding-top: 1.85rem;">
                <label class="checkbox-toggle-label">
                  <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= $isFeatured ? 'checked' : '' ?> onchange="updateLivePreview()">
                  <span><strong>Hero Spotlight Featured</strong><br><small style="color: var(--text-muted);">Displays in top Hero Spotlight</small></span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Author Profile Card -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              <span>Author Bylines</span>
            </h2>
          </div>
          <div class="panel-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
              <div class="form-group">
                <label for="author_name" class="form-label">Author Name</label>
                <input type="text" id="author_name" name="author_name" class="form-control" value="<?= e($authorName) ?>" oninput="updateLivePreview()">
              </div>
              <div class="form-group">
                <label for="author_role" class="form-label">Author Role / Designation</label>
                <input type="text" id="author_role" name="author_role" class="form-control" value="<?= e($authorRole) ?>" oninput="updateLivePreview()">
              </div>
            </div>
            <div class="form-group">
              <label for="author_bio" class="form-label">Author Bio Snippet</label>
              <textarea id="author_bio" name="author_bio" class="form-control" style="min-height: 70px;" oninput="updateLivePreview()"><?= e($authorBio) ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Media & SEO -->
      <div>
        <!-- Featured Image Card -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
              <span>Hero Cover Media (16:9)</span>
            </h2>
          </div>
          <div class="panel-body">
            <div class="image-uploader-card">
              <label for="featured_image" style="cursor: pointer; display: block;">
                <div style="font-weight: 700; color: var(--text-white); margin-bottom: 0.25rem;">Replace Cover Image File</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem;">Supports JPG, PNG, WebP (Recommended 1600 &times; 900)</div>
                <input type="file" id="featured_image" name="featured_image" class="form-control" accept="image/*" onchange="previewUpload(event)" style="cursor: pointer;">
              </label>
              <div id="imageThumbBox" class="image-thumb-preview" style="<?= !empty($initialHeroUrl) ? 'display: block;' : '' ?>">
                <img id="imageThumbImg" src="<?= e($initialHeroUrl) ?>" alt="Cover Preview">
              </div>
            </div>

            <div class="form-group" style="margin-top: 1.25rem;">
              <label for="image_alt" class="form-label">Cover Image Alt Text</label>
              <input type="text" id="image_alt" name="image_alt" class="form-control" value="<?= e($imageAlt) ?>" placeholder="Accessible description of the cover photo for SEO">
            </div>
          </div>
        </div>

        <!-- SEO & Metadata Card -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
              <span>Search Engine Optimization (SEO)</span>
            </h2>
          </div>
          <div class="panel-body">
            <div class="form-group">
              <label for="meta_title" class="form-label">Custom Meta Title</label>
              <input type="text" id="meta_title" name="meta_title" class="form-control" value="<?= e($metaTitle) ?>" placeholder="Defaults to Article Title | MY UGC VIDEO">
            </div>
            <div class="form-group">
              <label for="meta_description" class="form-label">Meta Description</label>
              <textarea id="meta_description" name="meta_description" class="form-control" style="min-height: 65px;" placeholder="Brief summary under 160 characters for Google SERP..."><?= e($metaDescription) ?></textarea>
            </div>
            <div class="form-group">
              <label for="canonical_url" class="form-label">Canonical URL (Optional)</label>
              <input type="url" id="canonical_url" name="canonical_url" class="form-control" value="<?= e($canonicalUrl) ?>" placeholder="Defaults to this article's canonical URL">
            </div>
            <div class="form-group">
              <label for="keywords" class="form-label">Keywords / Tags</label>
              <input type="text" id="keywords" name="keywords" class="form-control" value="<?= e($keywords) ?>" placeholder="e.g. UGC ads, TikTok strategy, direct response video">
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Bottom Submission Buttons -->
    <div style="display: flex; gap: 1rem; margin-top: 1.5rem; margin-bottom: 3rem; flex-wrap: wrap;">
      <button type="button" class="btn btn-primary btn-lg" style="flex: 2; min-width: 220px;" onclick="submitAsStatus('published')">
        Save &amp; Update Live Article &rarr;
      </button>
      <button type="button" class="btn btn-secondary btn-lg" style="flex: 1; min-width: 160px;" onclick="submitAsStatus('draft')">
        Save as Draft
      </button>
      <button type="button" class="btn btn-outline-mint btn-lg" onclick="togglePreviewMode(true)">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        <span>Preview Full Article</span>
      </button>
    </div>
  </form>
</div>

<!-- WORKSPACE 2: FULL-WIDTH LIVE PREVIEW (Activated via Preview Button) -->
<div id="previewWorkspace" class="preview-workspace">
  <div id="previewFrameWrapper" class="preview-frame-wrapper">
    
    <!-- Simulated Browser Bar -->
    <div class="preview-browser-bar">
      <div class="browser-dots">
        <div class="browser-dot"></div>
        <div class="browser-dot"></div>
        <div class="browser-dot"></div>
      </div>
      <div class="browser-address-bar">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        <span id="previewAddressUrl">myugcvideo.in/blog/<?= e($slug ?: 'article') ?>/</span>
      </div>
      <div id="previewDraftIndicator" style="<?= $status === 'draft' ? '' : 'display: none;' ?>">
        <span style="font-size: 0.68rem; font-weight: 700; background: var(--status-draft-bg); color: var(--status-draft); border: 1px solid rgba(229, 185, 90, 0.4); padding: 0.15rem 0.45rem; border-radius: 4px; text-transform: uppercase;">Draft</span>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="togglePreviewMode(false)" style="padding: 0.25rem 0.65rem; font-size: 0.76rem;">
        &times; Exit Preview
      </button>
    </div>

    <!-- Rendered Public Article Simulation -->
    <div class="preview-scroll-viewport">
      <div class="live-preview-root">
        
        <!-- Breadcrumbs -->
        <div class="preview-breadcrumbs">
          <span>Home</span>
          <span class="sep">/</span>
          <span>Blogs</span>
          <span class="sep">/</span>
          <span id="previewBreadcrumbTitle" class="current"><?= e($title ?: 'Untitled Article') ?></span>
        </div>

        <!-- Article Header -->
        <header class="preview-article-header">
          <div class="preview-meta-strip">
            <span class="tag-mono accent" id="previewCategoryTag">UGC Strategy</span>
            <span class="tag-mono" id="previewReadingTimeTag"><?= e($readingTime ?: '7 Min Read') ?></span>
            <span class="tag-mono" id="previewDateTag"><?= date('F j, Y', strtotime($post['published_at'])) ?></span>
            <span class="tag-mono" id="previewAuthorTag">By <?= e($authorName) ?></span>
          </div>

          <h1 class="preview-title" id="previewTitleDisplay">
            <?= e($title ?: 'Your Headline Will Appear Here in Real-Time') ?>
          </h1>

          <p class="preview-lead-excerpt" id="previewExcerptDisplay">
            <?= e($excerpt ?: 'The lead excerpt paragraph providing a high-level overview of the strategic analysis will be rendered live right here.') ?>
          </p>
        </header>

        <!-- Hero Media -->
        <div class="preview-hero-media" id="previewHeroMediaContainer">
          <div class="preview-hero-placeholder" id="previewHeroPlaceholder" style="<?= !empty($initialHeroUrl) ? 'display: none;' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
            <span>Upload a 16:9 featured cover image to preview live</span>
          </div>
          <img id="previewHeroImg" src="<?= e($initialHeroUrl) ?>" alt="Preview Cover" style="<?= !empty($initialHeroUrl) ? 'display: block;' : 'display: none;' ?>">
        </div>

        <!-- Body Prose -->
        <article class="preview-prose" id="previewProseBody">
          <?= $content ?>
        </article>

        <!-- Live FAQ Preview Section -->
        <section id="previewFaqSection" style="display: none; margin: 2.5rem 0 1.5rem; padding-top: 2rem; border-top: 1px solid rgba(49, 83, 77, 0.4);">
          <div style="margin-bottom: 1.25rem;">
            <span class="tag-mono accent" style="font-size: 0.72rem;">QUICK ANSWERS</span>
            <h3 style="font-family: var(--font-heading); color: var(--text-white); margin: 0.35rem 0 0; font-size: 1.35rem;">Frequently Asked Questions</h3>
          </div>
          <div id="previewFaqList" style="display: flex; flex-direction: column; gap: 0.85rem;"></div>
        </section>

        <!-- Author Bio Card -->
        <div class="preview-author-card">
          <div class="preview-author-avatar" id="previewAuthorAvatar">
            <?= strtoupper(substr($authorName, 0, 1)) ?>
          </div>
          <div>
            <div class="preview-author-name" id="previewAuthorNameDisplay"><?= e($authorName) ?></div>
            <div class="preview-author-role" id="previewAuthorRoleDisplay"><?= e($authorRole) ?></div>
            <p class="preview-author-bio" id="previewAuthorBioDisplay"><?= e($authorBio) ?></p>
          </div>
        </div>

        <!-- Studio CTA Box -->
        <div class="preview-cta-box">
          <h3>Ready to Scale Your Brand with High-Converting UGC?</h3>
          <p>We build end-to-end creator pipelines, test viral creative angles, and run performance-driven video ads that deliver measurable ROAS.</p>
          <a href="#" class="preview-cta-btn" onclick="return false;">Book a 30-Min Strategy Call &rarr;</a>
        </div>

        <!-- Bottom Return Button in Preview -->
        <div style="text-align: center; margin: 3rem 0 1.5rem;">
          <button type="button" class="btn btn-primary" onclick="togglePreviewMode(false)">
            &larr; Back to Full-Width Editor
          </button>
        </div>

      </div>
    </div>

  </div>
</div>

<!-- Real-time Live Engine & Toggle JavaScript -->
<script>
let slugIsManuallyEdited = true; // In edit mode, don't auto-overwrite existing slug unless unlocked
let uploadedDataUrl = '<?= e($initialHeroUrl) ?>';
let currentBodyMode = 'visual'; // 'visual' or 'markdown'
let currentMdView = 'write';    // 'write', 'preview', 'split'
let currentSchemaMode = '<?= e($schemaType ?? "recommended") ?>';
let savedSelectionRange = null;
let activeLinkNode = null;
let activeTableCell = null;

// FAQ State
let faqs = [];
try {
  const initFaqs = document.getElementById('faqs_json').value;
  faqs = JSON.parse(initFaqs || '[]');
  if (!Array.isArray(faqs)) faqs = [];
} catch (e) {
  faqs = [];
}

/**
 * Toggle between Full-Width Editor Mode and Full-Width Preview Mode
 */
function togglePreviewMode(showPreview) {
  const editorWorkspace = document.getElementById('editorWorkspace');
  const previewWorkspace = document.getElementById('previewWorkspace');
  const editorBarControls = document.getElementById('editorBarControls');
  const previewBarControls = document.getElementById('previewBarControls');

  if (showPreview) {
    syncEditorToContent();
    updateLivePreview();
    updateFaqLivePreview();
    editorWorkspace.style.display = 'none';
    previewWorkspace.style.display = 'block';
    editorBarControls.style.display = 'none';
    previewBarControls.style.display = 'flex';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } else {
    previewWorkspace.style.display = 'none';
    editorWorkspace.style.display = 'block';
    previewBarControls.style.display = 'none';
    editorBarControls.style.display = 'flex';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function toggleSlugLock() {
  slugIsManuallyEdited = !slugIsManuallyEdited;
  const btn = document.getElementById('slugLockLabel');
  btn.textContent = slugIsManuallyEdited ? 'Re-lock Auto Slug' : 'Unlock Custom Slug';
}

function onTitleInput(val) {
  if (!slugIsManuallyEdited) {
    const generated = slugifyClient(val);
    document.getElementById('slug').value = generated;
    onSlugInput(generated);
  }
  updateLivePreview();
  updateLiveSchema();
}

function onSlugInput(val) {
  const clean = slugifyClient(val);
  const preview = '<?= SITE_URL ?>/blog/' + (clean || 'article-slug') + '/';
  document.getElementById('slugUrlPreview').textContent = preview;
  document.getElementById('previewAddressUrl').textContent = 'myugcvideo.in/blog/' + (clean || 'article-slug') + '/';
  updateLiveSchema();
}

function slugifyClient(str) {
  return str.toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .trim()
    .replace(/[\s_-]+/g, '-');
}

/**
 * Visual Document Editor Commands & Helpers
 */
function execCmd(command, value = null) {
  const visualEditor = document.getElementById('visualEditor');
  visualEditor.focus();
  document.execCommand(command, false, value);
  syncEditorToContent();
  updateToolbarActiveStates();
}

function formatBlockCommand(tag) {
  const visualEditor = document.getElementById('visualEditor');
  visualEditor.focus();
  try {
    document.execCommand('formatBlock', false, tag);
  } catch (e) {
    document.execCommand('formatBlock', false, '<' + tag + '>');
  }
  syncEditorToContent();
  updateToolbarActiveStates();
}

/**
 * =========================================================================
 * Visual Table Editor Engine
 * =========================================================================
 */
function openInsertTableModal() {
  const visualEditor = document.getElementById('visualEditor');
  visualEditor.focus();
  const sel = window.getSelection();
  if (sel && sel.rangeCount > 0) {
    savedSelectionRange = sel.getRangeAt(0).cloneRange();
  }
  document.getElementById('tableRowsInput').value = '3';
  document.getElementById('tableColsInput').value = '3';
  document.getElementById('tableHeaderCheck').checked = true;
  document.getElementById('insertTableModal').classList.add('open');
}

function closeInsertTableModal() {
  document.getElementById('insertTableModal').classList.remove('open');
}

function insertTableFromModal() {
  const rows = parseInt(document.getElementById('tableRowsInput').value, 10) || 3;
  const cols = parseInt(document.getElementById('tableColsInput').value, 10) || 3;
  const hasHeader = document.getElementById('tableHeaderCheck').checked;

  const visualEditor = document.getElementById('visualEditor');
  visualEditor.focus();

  if (savedSelectionRange) {
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(savedSelectionRange);
  }

  let tableHtml = '<div class="table-responsive"><table class="editorial-table">';
  if (hasHeader) {
    tableHtml += '<thead><tr>';
    for (let c = 1; c <= cols; c++) {
      tableHtml += `<th>Header ${c}</th>`;
    }
    tableHtml += '</tr></thead>';
  }
  tableHtml += '<tbody>';
  const bodyRows = hasHeader ? Math.max(1, rows - 1) : rows;
  for (let r = 1; r <= bodyRows; r++) {
    tableHtml += '<tr>';
    for (let c = 1; c <= cols; c++) {
      tableHtml += `<td>Row ${r}, Col ${c}</td>`;
    }
    tableHtml += '</tr>';
  }
  tableHtml += '</tbody></table></div><p><br></p>';

  document.execCommand('insertHTML', false, tableHtml);
  closeInsertTableModal();
  syncEditorToContent();
  checkTableContext();
}

function checkTableContext() {
  const sel = window.getSelection();
  const contextBar = document.getElementById('tableContextBar');
  if (!contextBar) return;

  if (sel && sel.anchorNode) {
    let node = sel.anchorNode;
    if (node.nodeType === 3) node = node.parentNode;
    const cell = node ? node.closest('td, th') : null;
    const table = node ? node.closest('table') : null;

    if (cell && table) {
      activeTableCell = cell;
      contextBar.style.display = 'flex';
      return;
    }
  }

  activeTableCell = null;
  contextBar.style.display = 'none';
}

function tableAddRow(direction) {
  if (!activeTableCell) return;
  const row = activeTableCell.closest('tr');
  const table = activeTableCell.closest('table');
  const colCount = row.children.length;

  const newRow = document.createElement('tr');
  for (let i = 0; i < colCount; i++) {
    const td = document.createElement('td');
    td.textContent = 'Cell';
    newRow.appendChild(td);
  }

  if (direction === 'above') {
    row.parentNode.insertBefore(newRow, row);
  } else {
    row.parentNode.insertBefore(newRow, row.nextSibling);
  }
  syncEditorToContent();
}

function tableAddCol(direction) {
  if (!activeTableCell) return;
  const table = activeTableCell.closest('table');
  const row = activeTableCell.closest('tr');
  const colIdx = Array.from(row.children).indexOf(activeTableCell);

  const allRows = table.querySelectorAll('tr');
  allRows.forEach(r => {
    const isHeader = r.querySelector('th') !== null;
    const cell = document.createElement(isHeader ? 'th' : 'td');
    cell.textContent = isHeader ? 'Header' : 'Cell';
    const targetCell = r.children[colIdx];
    if (direction === 'left') {
      r.insertBefore(cell, targetCell);
    } else {
      r.insertBefore(cell, targetCell ? targetCell.nextSibling : null);
    }
  });
  syncEditorToContent();
}

function tableDeleteRow() {
  if (!activeTableCell) return;
  const row = activeTableCell.closest('tr');
  const table = activeTableCell.closest('table');
  const allRows = table.querySelectorAll('tr');

  if (allRows.length <= 1) {
    tableDeleteEntire();
    return;
  }
  row.remove();
  activeTableCell = null;
  checkTableContext();
  syncEditorToContent();
}

function tableDeleteCol() {
  if (!activeTableCell) return;
  const table = activeTableCell.closest('table');
  const row = activeTableCell.closest('tr');
  const colIdx = Array.from(row.children).indexOf(activeTableCell);
  const allRows = table.querySelectorAll('tr');

  if (row.children.length <= 1) {
    tableDeleteEntire();
    return;
  }

  allRows.forEach(r => {
    if (r.children[colIdx]) {
      r.children[colIdx].remove();
    }
  });
  activeTableCell = null;
  checkTableContext();
  syncEditorToContent();
}

function tableToggleHeader() {
  if (!activeTableCell) return;
  const table = activeTableCell.closest('table');
  const thead = table.querySelector('thead');
  if (thead) {
    const firstRow = thead.querySelector('tr');
    if (firstRow) {
      const newTr = document.createElement('tr');
      Array.from(firstRow.children).forEach(th => {
        const td = document.createElement('td');
        td.innerHTML = th.innerHTML;
        newTr.appendChild(td);
      });
      const tbody = table.querySelector('tbody') || table;
      tbody.insertBefore(newTr, tbody.firstChild);
    }
    thead.remove();
  } else {
    const firstRow = table.querySelector('tr');
    if (firstRow) {
      const newThead = document.createElement('thead');
      const newTr = document.createElement('tr');
      Array.from(firstRow.children).forEach(cell => {
        const th = document.createElement('th');
        th.innerHTML = cell.innerHTML;
        newTr.appendChild(th);
      });
      newThead.appendChild(newTr);
      firstRow.remove();
      table.insertBefore(newThead, table.firstChild);
    }
  }
  syncEditorToContent();
}

function tableDeleteEntire() {
  if (!activeTableCell) return;
  if (!confirm('Are you sure you want to permanently delete this entire table?')) return;
  const tableWrapper = activeTableCell.closest('.table-responsive') || activeTableCell.closest('table');
  tableWrapper.remove();
  activeTableCell = null;
  checkTableContext();
  syncEditorToContent();
}

/**
 * Hyperlink Modal Manager
 */
function openLinkModal() {
  const visualEditor = document.getElementById('visualEditor');
  visualEditor.focus();
  const selection = window.getSelection();
  savedSelectionRange = (selection && selection.rangeCount > 0) ? selection.getRangeAt(0).cloneRange() : null;

  activeLinkNode = null;
  if (selection && selection.anchorNode) {
    let node = selection.anchorNode;
    if (node.nodeType === 3) node = node.parentNode;
    activeLinkNode = node.closest('a');
  }

  const urlInput = document.getElementById('modalLinkUrl');
  const textInput = document.getElementById('modalLinkText');
  const newTabCheck = document.getElementById('modalLinkNewTab');
  const removeBtn = document.getElementById('modalRemoveLinkBtn');
  const errBox = document.getElementById('modalLinkError');
  errBox.style.display = 'none';

  if (activeLinkNode) {
    urlInput.value = activeLinkNode.getAttribute('href') || '';
    textInput.value = activeLinkNode.textContent || '';
    newTabCheck.checked = (activeLinkNode.getAttribute('target') === '_blank');
    removeBtn.style.display = 'inline-flex';
  } else {
    const selectedText = selection ? selection.toString() : '';
    urlInput.value = '';
    textInput.value = selectedText;
    newTabCheck.checked = true;
    removeBtn.style.display = 'none';
  }

  document.getElementById('linkModal').classList.add('open');
  setTimeout(() => { urlInput.focus(); }, 60);
}

function closeLinkModal() {
  document.getElementById('linkModal').classList.remove('open');
  activeLinkNode = null;
}

function applyLinkFromModal() {
  const urlInput = document.getElementById('modalLinkUrl');
  const textInput = document.getElementById('modalLinkText');
  const newTabCheck = document.getElementById('modalLinkNewTab');
  const errBox = document.getElementById('modalLinkError');

  const url = urlInput.value.trim();
  if (!url || url === 'http://' || url === 'https://') {
    errBox.textContent = 'Please enter a valid destination URL.';
    errBox.style.display = 'block';
    urlInput.focus();
    return;
  }

  if (!/^https?:\/\/|^\/|^#|^mailto:/i.test(url)) {
    errBox.textContent = 'URL should start with https://, http://, /, or #';
    errBox.style.display = 'block';
    urlInput.focus();
    return;
  }

  errBox.style.display = 'none';

  const visualEditor = document.getElementById('visualEditor');
  visualEditor.focus();

  if (savedSelectionRange) {
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(savedSelectionRange);
  }

  const text = textInput.value.trim() || url;
  const isNewTab = newTabCheck.checked;

  if (activeLinkNode) {
    activeLinkNode.setAttribute('href', url);
    activeLinkNode.textContent = text;
    if (isNewTab) {
      activeLinkNode.setAttribute('target', '_blank');
      activeLinkNode.setAttribute('rel', 'noopener noreferrer');
    } else {
      activeLinkNode.removeAttribute('target');
      activeLinkNode.removeAttribute('rel');
    }
  } else {
    const targetAttr = isNewTab ? ' target="_blank" rel="noopener noreferrer"' : '';
    const aHtml = `<a href="${escapeHtml(url)}"${targetAttr}>${escapeHtml(text)}</a>`;
    document.execCommand('insertHTML', false, aHtml);
  }

  closeLinkModal();
  syncEditorToContent();
  updateToolbarActiveStates();
}

function removeLinkFromModal() {
  if (activeLinkNode) {
    const parent = activeLinkNode.parentNode;
    while (activeLinkNode.firstChild) {
      parent.insertBefore(activeLinkNode.firstChild, activeLinkNode);
    }
    parent.removeChild(activeLinkNode);
  } else {
    document.execCommand('unlink', false, null);
  }
  closeLinkModal();
  syncEditorToContent();
  updateToolbarActiveStates();
}

function insertTakeawayBox() {
  const visualEditor = document.getElementById('visualEditor');
  visualEditor.focus();
  const boxHtml = `\n<div class="takeaway-card" style="background:#00221E; border:1px solid #31534D; border-radius:10px; padding:1.5rem; margin:1.75rem 0;"><h4 style="color:#36D9B0; text-transform:uppercase; letter-spacing:0.08em; font-size:0.85rem; margin:0 0 0.5rem 0;">Key Takeaway</h4><p style="margin:0; color:#D9E5DF;">Key actionable insight goes here...</p></div>\n<p><br></p>`;
  document.execCommand('insertHTML', false, boxHtml);
  syncEditorToContent();
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function updateToolbarActiveStates() {
  const commands = ['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList'];
  commands.forEach(cmd => {
    const btn = document.querySelector(`[data-cmd="${cmd}"]`);
    if (btn) {
      btn.classList.toggle('active', !!document.queryCommandState(cmd));
    }
  });

  const selection = window.getSelection();
  if (selection && selection.rangeCount > 0) {
    let node = selection.anchorNode;
    if (node && node.nodeType === 3) node = node.parentNode;
    const h2Btn = document.querySelector('[data-block="h2"]');
    const h3Btn = document.querySelector('[data-block="h3"]');
    const pBtn = document.querySelector('[data-block="p"]');
    const quoteBtn = document.querySelector('[data-block="blockquote"]');

    if (h2Btn) h2Btn.classList.toggle('active', !!(node && node.closest('h2')));
    if (h3Btn) h3Btn.classList.toggle('active', !!(node && node.closest('h3')));
    if (pBtn) pBtn.classList.toggle('active', !!(node && node.closest('p') && !node.closest('blockquote, h2, h3, table')));
    if (quoteBtn) quoteBtn.classList.toggle('active', !!(node && node.closest('blockquote')));
  }
  checkTableContext();
}

function syncEditorToContent() {
  const visualEditor = document.getElementById('visualEditor');
  const hiddenContent = document.getElementById('content');
  if (visualEditor && hiddenContent) {
    const cleanText = visualEditor.innerText.trim();
    if (cleanText === '' && !visualEditor.querySelector('img, iframe, hr, .takeaway-card, table')) {
      hiddenContent.value = '';
    } else {
      hiddenContent.value = visualEditor.innerHTML;
    }
  }
  updateLivePreview();
}

/**
 * =========================================================================
 * Markdown Mode & Import Logic (with Table Support)
 * =========================================================================
 */
function switchBodyEditorMode(mode) {
  currentBodyMode = mode;
  const visualTab = document.getElementById('tabVisualMode');
  const mdTab = document.getElementById('tabMarkdownMode');
  const visualWrapper = document.getElementById('visualEditorWrapper');
  const mdWorkspace = document.getElementById('markdownWorkspace');

  if (mode === 'markdown') {
    syncEditorToContent();
    const html = document.getElementById('content').value;
    const md = clientHtmlToMarkdown(html);
    document.getElementById('markdownInput').value = md;
    onMarkdownInput();

    visualTab.classList.remove('active');
    mdTab.classList.add('active');
    visualWrapper.style.display = 'none';
    mdWorkspace.style.display = 'block';
  } else {
    visualTab.classList.add('active');
    mdTab.classList.remove('active');
    mdWorkspace.style.display = 'none';
    visualWrapper.style.display = 'block';
  }
}

function setMdViewMode(view) {
  currentMdView = view;
  ['write', 'preview', 'split'].forEach(v => {
    const btn = document.getElementById('btnMd' + v.charAt(0).toUpperCase() + v.slice(1));
    if (btn) btn.classList.toggle('active', v === view);
  });

  const container = document.getElementById('mdSplitContainer');
  container.className = 'markdown-split-container view-' + view;
  if (view !== 'write') {
    onMarkdownInput();
  }
}

function onMarkdownInput() {
  const text = document.getElementById('markdownInput').value;
  const words = text.trim() ? text.trim().split(/\s+/).length : 0;
  const chars = text.length;
  document.getElementById('mdMetricsDisplay').textContent = words + ' words • ' + chars + ' chars';

  const rendered = clientMarkdownToHtml(text);
  document.getElementById('markdownPreview').innerHTML = rendered || '<p style="color: var(--text-muted); font-style: italic;">Markdown preview will render here...</p>';
}

function handleMdFileUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    const importedMd = e.target.result;
    const existingMd = document.getElementById('markdownInput').value;

    if (existingMd.trim().length > 0) {
      const choice = confirm('Replace existing Markdown content with uploaded file?\n\nClick "OK" to Replace, or "Cancel" to Append.');
      if (choice) {
        document.getElementById('markdownInput').value = importedMd;
      } else {
        document.getElementById('markdownInput').value = existingMd + '\n\n' + importedMd;
      }
    } else {
      document.getElementById('markdownInput').value = importedMd;
    }

    onMarkdownInput();
    event.target.value = '';
  };
  reader.readAsText(file);
}

function applyMarkdownToArticle(action) {
  const mdText = document.getElementById('markdownInput').value;
  if (!mdText.trim()) {
    alert('Markdown editor is empty. Enter or upload some markdown first.');
    return;
  }

  const convertedHtml = clientMarkdownToHtml(mdText);
  const visualEditor = document.getElementById('visualEditor');

  if (action === 'replace') {
    if (visualEditor.innerText.trim().length > 0 && !confirm('This will replace your current Article Body with the converted Markdown. Continue?')) {
      return;
    }
    visualEditor.innerHTML = convertedHtml;
  } else if (action === 'append') {
    visualEditor.innerHTML = (visualEditor.innerHTML ? visualEditor.innerHTML + '<br>' : '') + convertedHtml;
  }

  syncEditorToContent();
  switchBodyEditorMode('visual');
  updateLivePreview();
}

function clientMarkdownToHtml(md) {
  if (!md) return '';
  let text = md.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

  // Convert Pipe Tables to HTML
  text = text.replace(/((?:(?:^|\n)\|[^\n]+\|[ \t]*)+)/g, function(match) {
    const lines = match.trim().split('\n');
    if (lines.length < 2) return match;
    let html = '<div class="table-responsive"><table class="editorial-table">';
    let headerDone = false;
    let inBody = false;

    lines.forEach((l, idx) => {
      const line = l.trim();
      if (!line || !line.includes('|')) return;
      const cells = line.replace(/^\||\|$/g, '').split('|').map(c => c.trim());
      const isSep = cells.every(c => /^:?-+:?$/.test(c));
      if (isSep) {
        headerDone = true;
        return;
      }
      if (!headerDone && idx === 0) {
        html += '<thead><tr>' + cells.map(c => '<th>' + escapeHtml(c) + '</th>').join('') + '</tr></thead>';
      } else {
        if (!inBody) { html += '<tbody>'; inBody = true; }
        html += '<tr>' + cells.map(c => '<td>' + escapeHtml(c) + '</td>').join('') + '</tr>';
      }
    });

    if (inBody) html += '</tbody>';
    html += '</table></div>';
    return '\n\n' + html + '\n\n';
  });

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
    if (/^<(h[1-6]|ul|ol|blockquote|hr|div|table)/i.test(p)) return p;
    return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
  }).join('\n');
}

function clientHtmlToMarkdown(html) {
  if (!html) return '';
  let text = html;

  // Convert HTML tables to Markdown pipe tables
  text = text.replace(/<table[^>]*>[\s\S]*?<\/table>/gi, function(tblHtml) {
    const div = document.createElement('div');
    div.innerHTML = tblHtml;
    const table = div.querySelector('table');
    if (!table) return '';
    const rows = table.querySelectorAll('tr');
    const mdRows = [];
    let hasHeader = false;

    rows.forEach((r, idx) => {
      const ths = r.querySelectorAll('th');
      const tds = r.querySelectorAll('td');
      if (ths.length > 0) {
        const cells = Array.from(ths).map(th => th.innerText.replace(/\|/g, '\\|').trim());
        mdRows.push('| ' + cells.join(' | ') + ' |');
        mdRows.push('| ' + cells.map(() => '---').join(' | ') + ' |');
        hasHeader = true;
      } else if (tds.length > 0) {
        const cells = Array.from(tds).map(td => td.innerText.replace(/\|/g, '\\|').trim());
        if (!hasHeader && mdRows.length === 0) {
          mdRows.push('| ' + cells.map((_, i) => 'Col ' + (i + 1)).join(' | ') + ' |');
          mdRows.push('| ' + cells.map(() => '---').join(' | ') + ' |');
          hasHeader = true;
        }
        mdRows.push('| ' + cells.join(' | ') + ' |');
      }
    });

    return '\n\n' + mdRows.join('\n') + '\n\n';
  });

  text = text.replace(/<h1[^>]*>(.*?)<\/h1>/gi, '# $1\n\n');
  text = text.replace(/<h2[^>]*>(.*?)<\/h2>/gi, '## $1\n\n');
  text = text.replace(/<h3[^>]*>(.*?)<\/h3>/gi, '### $1\n\n');
  text = text.replace(/<h4[^>]*>(.*?)<\/h4>/gi, '#### $1\n\n');
  text = text.replace(/<h5[^>]*>(.*?)<\/h5>/gi, '##### $1\n\n');
  text = text.replace(/<h6[^>]*>(.*?)<\/h6>/gi, '###### $1\n\n');

  text = text.replace(/<blockquote[^>]*>(.*?)<\/blockquote>/gi, '> $1\n\n');
  text = text.replace(/<li[^>]*>(.*?)<\/li>/gi, '- $1\n');
  text = text.replace(/<\/?(ul|ol)[^>]*>/gi, '\n');
  text = text.replace(/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/gi, '**$2**');
  text = text.replace(/<(em|i)[^>]*>(.*?)<\/(em|i)>/gi, '*$2*');
  text = text.replace(/<a\s+[^>]*href=["']([^"']+)["'][^>]*>(.*?)<\/a>/gi, '[$2]($1)');
  text = text.replace(/<p[^>]*>(.*?)<\/p>/gi, '$1\n\n');
  text = text.replace(/<br\s*\/?>/gi, '\n');

  const div = document.createElement('div');
  div.innerHTML = text;
  return div.innerText.trim();
}

/**
 * =========================================================================
 * FAQ Builder Logic
 * =========================================================================
 */
function renderFaqItems() {
  const container = document.getElementById('faqItemsContainer');
  container.innerHTML = '';

  if (faqs.length === 0) {
    container.innerHTML = '<div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted); font-size: 0.88rem; border: 1px dashed var(--border-sage-subtle); border-radius: var(--radius-sm);">No FAQs added yet. Click "+ Add FAQ" above to create questions &amp; answers for readers and search engines.</div>';
  } else {
    faqs.forEach((faq, index) => {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'faq-item-card';
      itemDiv.innerHTML = `
        <div class="faq-item-header">
          <span class="faq-item-badge">FAQ #${index + 1}</span>
          <div class="faq-btn-group">
            ${index > 0 ? `<button type="button" class="faq-btn" onclick="moveFaqItem(${index}, -1)" title="Move Up">&uarr;</button>` : ''}
            ${index < faqs.length - 1 ? `<button type="button" class="faq-btn" onclick="moveFaqItem(${index}, 1)" title="Move Down">&darr;</button>` : ''}
            <button type="button" class="faq-btn faq-btn-delete" onclick="deleteFaqItem(${index})" title="Delete FAQ">&times;</button>
          </div>
        </div>
        <div class="form-group" style="margin-bottom: 0.75rem;">
          <label class="form-label" style="font-size: 0.78rem;">Question</label>
          <input type="text" class="form-control" value="${escapeHtml(faq.question || '')}" placeholder="e.g. How do creators shoot authentic UGC video ads?" oninput="updateFaqQuestion(${index}, this.value)">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.78rem;">Answer</label>
          <textarea class="form-control" style="min-height: 65px; font-size: 0.88rem;" placeholder="Clear, direct answer explaining the concept..." oninput="updateFaqAnswer(${index}, this.value)">${escapeHtml(faq.answer || '')}</textarea>
        </div>
      `;
      container.appendChild(itemDiv);
    });
  }

  document.getElementById('faqs_json').value = JSON.stringify(faqs);
  updateLiveSchema();
  updateFaqLivePreview();
}

function addNewFaqItem() {
  faqs.push({ question: '', answer: '' });
  renderFaqItems();
  const inputs = document.querySelectorAll('#faqItemsContainer input');
  if (inputs.length > 0) inputs[inputs.length - 1].focus();
}

function updateFaqQuestion(index, val) {
  if (faqs[index]) {
    faqs[index].question = val;
    document.getElementById('faqs_json').value = JSON.stringify(faqs);
    updateLiveSchema();
    updateFaqLivePreview();
  }
}

function updateFaqAnswer(index, val) {
  if (faqs[index]) {
    faqs[index].answer = val;
    document.getElementById('faqs_json').value = JSON.stringify(faqs);
    updateLiveSchema();
    updateFaqLivePreview();
  }
}

function deleteFaqItem(index) {
  if (confirm('Delete FAQ #' + (index + 1) + '?')) {
    faqs.splice(index, 1);
    renderFaqItems();
  }
}

function moveFaqItem(index, direction) {
  const newIndex = index + direction;
  if (newIndex >= 0 && newIndex < faqs.length) {
    const temp = faqs[index];
    faqs[index] = faqs[newIndex];
    faqs[newIndex] = temp;
    renderFaqItems();
  }
}

function updateFaqLivePreview() {
  const faqSection = document.getElementById('previewFaqSection');
  const faqList = document.getElementById('previewFaqList');
  if (!faqSection || !faqList) return;

  const valid = faqs.filter(f => f.question.trim() && f.answer.trim());
  if (valid.length === 0) {
    faqSection.style.display = 'none';
  } else {
    faqSection.style.display = 'block';
    faqList.innerHTML = valid.map(f => `
      <div style="background: rgba(0, 31, 27, 0.7); border: 1px solid rgba(49, 83, 77, 0.4); border-radius: 6px; padding: 1rem 1.25rem;">
        <div style="font-weight: 700; color: #fff; font-size: 1rem; margin-bottom: 0.4rem;">${escapeHtml(f.question)}</div>
        <div style="color: var(--text-secondary); font-size: 0.92rem; line-height: 1.6;">${escapeHtml(f.answer).replace(/\n/g, '<br>')}</div>
      </div>
    `).join('');
  }
}

/**
 * =========================================================================
 * Intelligent Dynamic Schema Generation Logic (Two Modes: Recommended vs Upload)
 * =========================================================================
 */
function toggleSchemaMode(mode) {
  currentSchemaMode = (mode === 'upload' || mode === 'custom') ? 'upload' : 'recommended';
  
  const radioRec = document.getElementById('schemaModeRecommended');
  const radioUp = document.getElementById('schemaModeUpload');
  if (radioRec) radioRec.checked = (currentSchemaMode === 'recommended');
  if (radioUp) radioUp.checked = (currentSchemaMode === 'upload');

  const secRec = document.getElementById('recommendedSchemaSection');
  const secUp = document.getElementById('uploadSchemaSection');
  if (secRec) secRec.style.display = (currentSchemaMode === 'recommended') ? 'block' : 'none';
  if (secUp) secUp.style.display = (currentSchemaMode === 'upload') ? 'block' : 'none';

  if (currentSchemaMode === 'recommended') {
    updateLiveSchema();
  } else {
    validateUploadedSchemaState();
  }
}

function handleSchemaFileUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  if (!file.name.toLowerCase().endsWith('.json')) {
    showSchemaUploadError('Invalid file format. Only .json files are accepted.');
    return;
  }

  if (file.size > 524288) { // 500KB
    showSchemaUploadError('File size exceeds maximum allowed 500KB.');
    return;
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    const text = e.target.result;
    try {
      const parsed = JSON.parse(text);
      if (!parsed || typeof parsed !== 'object') {
        throw new Error('Root JSON must be an object or array.');
      }

      // Check for Schema.org context or graph
      const ctx = parsed['@context'] ? JSON.stringify(parsed['@context']) : '';
      const hasGraph = Array.isArray(parsed['@graph']);
      if (!ctx.includes('schema.org') && !hasGraph) {
        throw new Error('Missing standard "@context": "https://schema.org". Valid JSON-LD must declare Schema.org context.');
      }
      if (!parsed['@type'] && !hasGraph) {
        throw new Error('Missing "@type" property. Schema must specify an entity type (e.g. "BlogPosting") or a "@graph" array.');
      }

      const formatted = JSON.stringify(parsed, null, 2);
      document.getElementById('custom_schema').value = formatted;
      document.getElementById('schema_filename').value = file.name;

      document.getElementById('schemaFileNameDisplay').textContent = file.name;
      document.getElementById('schemaFileSizeDisplay').textContent = (file.size / 1024).toFixed(1) + ' KB';
      document.getElementById('uploadedSchemaCard').style.display = 'flex';
      document.getElementById('schemaDropzone').style.display = 'none';
      document.getElementById('uploadedSchemaPreview').textContent = formatted;
      document.getElementById('uploadedSchemaPreviewContainer').style.display = 'block';
      document.getElementById('schemaUploadAlert').style.display = 'none';

      const badge = document.getElementById('schemaStatusBadge');
      badge.className = 'schema-status-badge badge-active';
      badge.textContent = 'Active & Validated';
    } catch (err) {
      showSchemaUploadError('Schema Validation Error: ' + err.message);
    }
  };
  reader.readAsText(file);
}

function removeUploadedSchema() {
  document.getElementById('custom_schema').value = '';
  document.getElementById('schema_filename').value = '';
  document.getElementById('schema_file_input').value = '';
  document.getElementById('uploadedSchemaCard').style.display = 'none';
  document.getElementById('uploadedSchemaPreviewContainer').style.display = 'none';
  document.getElementById('schemaDropzone').style.display = 'block';
  document.getElementById('schemaUploadAlert').style.display = 'none';
  toggleSchemaMode('recommended');
}

function showSchemaUploadError(msg) {
  const alertBox = document.getElementById('schemaUploadAlert');
  alertBox.style.display = 'block';
  alertBox.textContent = msg;

  const badge = document.getElementById('schemaStatusBadge');
  badge.className = 'schema-status-badge badge-error';
  badge.textContent = 'Validation Error';
}

function validateUploadedSchemaState() {
  const customVal = (document.getElementById('custom_schema').value || '').trim();
  const alertBox = document.getElementById('schemaUploadAlert');
  const badge = document.getElementById('schemaStatusBadge');

  if (!customVal) {
    alertBox.style.display = 'none';
    badge.className = 'schema-status-badge';
    badge.style.background = 'rgba(255, 193, 7, 0.15)';
    badge.style.color = '#ffc107';
    badge.style.borderColor = 'rgba(255, 193, 7, 0.4)';
    badge.textContent = 'Awaiting JSON File';
    return;
  }

  try {
    const parsed = JSON.parse(customVal);
    const ctx = parsed['@context'] ? JSON.stringify(parsed['@context']) : '';
    const hasGraph = Array.isArray(parsed['@graph']);
    if (!ctx.includes('schema.org') && !hasGraph) {
      throw new Error('Missing "@context": "https://schema.org"');
    }
    alertBox.style.display = 'none';
    badge.className = 'schema-status-badge badge-active';
    badge.style.background = '';
    badge.style.color = '';
    badge.style.borderColor = '';
    badge.textContent = 'Active & Validated';
  } catch (err) {
    showSchemaUploadError('Validation Error: ' + err.message);
  }
}

function updateLiveSchema() {
  if (currentSchemaMode !== 'recommended') {
    validateUploadedSchemaState();
    return;
  }

  const title = document.getElementById('title').value.trim() || 'Untitled Article';
  const excerpt = document.getElementById('excerpt').value.trim() || '';
  const author = document.getElementById('author_name').value.trim() || 'MY UGC VIDEO Editorial Team';
  const role = document.getElementById('author_role').value.trim() || 'UGC Creative Studio';
  const slug = document.getElementById('slug').value.trim() || 'article-slug';
  const catSelect = document.getElementById('category_id');
  const catText = catSelect.selectedIndex > 0 ? catSelect.options[catSelect.selectedIndex].text : '';
  const catSlug = catText ? slugifyClient(catText) : '';
  const canonical = '<?= SITE_URL ?>/blog/' + slug;

  const validFaqs = faqs.filter(f => f.question.trim() && f.answer.trim());

  const breadcrumbs = [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "<?= SITE_URL ?>/" },
    { "@type": "ListItem", "position": 2, "name": "Blogs", "item": "<?= SITE_URL ?>/blogs" }
  ];
  let pos = 3;
  if (catText) {
    breadcrumbs.push({ "@type": "ListItem", "position": pos, "name": catText, "item": "<?= SITE_URL ?>/blogs?category=" + encodeURIComponent(catSlug) });
    pos++;
  }
  breadcrumbs.push({ "@type": "ListItem", "position": pos, "name": title, "item": canonical });

  const graph = [
    {
      "@type": "Organization",
      "@id": "<?= SITE_URL ?>/#organization",
      "name": "MY UGC VIDEO",
      "url": "<?= SITE_URL ?>/",
      "logo": {
        "@type": "ImageObject",
        "url": "<?= SITE_URL ?>/images/hero/hero-creator.jpg"
      },
      "description": "Premier performance-driven UGC video production, creator management and content marketing studio."
    },
    {
      "@type": "BreadcrumbList",
      "@id": canonical + "#breadcrumb",
      "itemListElement": breadcrumbs
    },
    {
      "@type": "BlogPosting",
      "@id": canonical + "#article",
      "isPartOf": {
        "@type": "WebSite",
        "@id": "<?= SITE_URL ?>/#website",
        "name": "MY UGC VIDEO",
        "url": "<?= SITE_URL ?>/"
      },
      "headline": title,
      "description": excerpt,
      "image": uploadedDataUrl || "<?= SITE_URL ?>/images/blogs/blog-1.jpg",
      "datePublished": "<?= !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c') ?>",
      "dateModified": new Date().toISOString(),
      "mainEntityOfPage": { "@type": "WebPage", "@id": canonical },
      "author": { "@type": "Person", "name": author, "jobTitle": role },
      "publisher": { "@id": "<?= SITE_URL ?>/#organization" }
    }
  ];

  if (validFaqs.length > 0) {
    graph.push({
      "@type": "FAQPage",
      "@id": canonical + "#faq",
      "mainEntity": validFaqs.map(f => ({
        "@type": "Question",
        "name": f.question.trim(),
        "acceptedAnswer": { "@type": "Answer", "text": f.answer.trim() }
      }))
    });
  }

  const schemaData = {
    "@context": "https://schema.org",
    "@graph": graph
  };

  document.getElementById('autoSchemaPreview').textContent = JSON.stringify(schemaData, null, 2);

  // Sync individual schema editor textareas if user hasn't typed custom content
  const edOrg = document.getElementById('schemaEditorOrg');
  const edBreadcrumb = document.getElementById('schemaEditorBreadcrumb');
  const edFaq = document.getElementById('schemaEditorFaq');
  const edBlog = document.getElementById('schemaEditorBlog');

  if (edOrg && !edOrg.dataset.userEdited) {
    edOrg.value = JSON.stringify({
      "@context": "https://schema.org",
      "@type": "Organization",
      "@id": "<?= SITE_URL ?>/#organization",
      "name": "MY UGC VIDEO",
      "url": "<?= SITE_URL ?>/",
      "logo": {
        "@type": "ImageObject",
        "url": "<?= SITE_URL ?>/images/hero/hero-creator.jpg"
      },
      "description": "Premier performance-driven UGC video production, creator management and content marketing studio."
    }, null, 2);
  }

  if (edBreadcrumb && !edBreadcrumb.dataset.userEdited) {
    edBreadcrumb.value = JSON.stringify({
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "@id": canonical + "#breadcrumb",
      "itemListElement": breadcrumbs
    }, null, 2);
  }

  if (edFaq && !edFaq.dataset.userEdited) {
    edFaq.value = JSON.stringify({
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "@id": canonical + "#faq",
      "mainEntity": validFaqs.map(f => ({
        "@type": "Question",
        "name": f.question.trim(),
        "acceptedAnswer": { "@type": "Answer", "text": f.answer.trim() }
      }))
    }, null, 2);
  }

  if (edBlog && !edBlog.dataset.userEdited) {
    edBlog.value = JSON.stringify({
      "@context": "https://schema.org",
      "@type": "BlogPosting",
      "@id": canonical + "#article",
      "isPartOf": {
        "@type": "WebSite",
        "@id": "<?= SITE_URL ?>/#website",
        "name": "MY UGC VIDEO",
        "url": "<?= SITE_URL ?>/"
      },
      "headline": title,
      "description": excerpt,
      "image": uploadedDataUrl || "<?= SITE_URL ?>/images/blogs/blog-1.jpg",
      "datePublished": "<?= !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c') ?>",
      "dateModified": new Date().toISOString(),
      "mainEntityOfPage": { "@type": "WebPage", "@id": canonical },
      "author": { "@type": "Person", "name": author, "jobTitle": role },
      "publisher": { "@id": "<?= SITE_URL ?>/#organization" }
    }, null, 2);
  }

  const chipFaq = document.getElementById('chipFaqPage');
  const chipFaqLabel = document.getElementById('chipFaqLabel');
  const chipFaqIcon = document.getElementById('chipFaqIcon');
  if (validFaqs.length > 0) {
    chipFaq.classList.add('active');
    chipFaqIcon.innerHTML = '&#10003;';
    chipFaqLabel.textContent = `FAQPage (${validFaqs.length} FAQs)`;
  } else {
    chipFaq.classList.remove('active');
    chipFaqIcon.innerHTML = '&bull;';
    chipFaqLabel.textContent = 'FAQPage (0 FAQs)';
  }

  const badge = document.getElementById('schemaStatusBadge');
  badge.className = 'schema-status-badge badge-active';
  badge.style.background = '';
  badge.style.color = '';
  badge.style.borderColor = '';
  badge.textContent = 'Active & Validated';
}

function copyAutoSchemaJson() {
  const text = document.getElementById('autoSchemaPreview').textContent;
  navigator.clipboard.writeText(text).then(() => {
    alert('Dynamic Schema JSON copied to clipboard!');
  });
}

function handleSchemaCheckboxChange(checkbox) {
  const schemaType = checkbox.value;
  const checkboxes = document.querySelectorAll('.schema-checkbox');
  const editors = {
    organization: document.getElementById('schemaEditorOrgContainer'),
    breadcrumb: document.getElementById('schemaEditorBreadcrumbContainer'),
    faq: document.getElementById('schemaEditorFaqContainer'),
    blog: document.getElementById('schemaEditorBlogContainer')
  };
  const defaultPreview = document.getElementById('autoSchemaPreviewContainer');

  if (!checkbox.checked) {
    // When currently selected checkbox is clicked again: deselect and hide editor
    if (editors[schemaType]) {
      editors[schemaType].style.display = 'none';
    }
    if (defaultPreview) {
      defaultPreview.style.display = 'block';
    }
    return;
  }

  // When a checkbox is selected:
  // 1. Automatically deselect the previously selected checkbox
  checkboxes.forEach(cb => {
    if (cb !== checkbox) {
      cb.checked = false;
    }
  });

  // 2. Display only its corresponding schema editor below the checkbox options
  // 3. Hide the previously selected schema editor
  Object.keys(editors).forEach(type => {
    if (editors[type]) {
      if (type === schemaType) {
        editors[type].style.display = 'block';
      } else {
        editors[type].style.display = 'none';
      }
    }
  });

  // Hide the default preview while a specific editor is active
  if (defaultPreview) {
    defaultPreview.style.display = 'none';
  }
}

function copySchemaEditorText(editorId) {
  const el = document.getElementById(editorId);
  if (!el) return;
  navigator.clipboard.writeText(el.value).then(() => {
    alert('Schema JSON copied to clipboard!');
  });
}

function previewUpload(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      uploadedDataUrl = e.target.result;
      document.getElementById('imageThumbImg').src = uploadedDataUrl;
      document.getElementById('imageThumbBox').style.display = 'block';

      // Update live preview pane
      const previewImg = document.getElementById('previewHeroImg');
      const previewPlaceholder = document.getElementById('previewHeroPlaceholder');
      previewImg.src = uploadedDataUrl;
      previewImg.style.display = 'block';
      if (previewPlaceholder) previewPlaceholder.style.display = 'none';
      updateLiveSchema();
    };
    reader.readAsDataURL(file);
  }
}

function updateLivePreview() {
  const title = document.getElementById('title').value.trim();
  const excerpt = document.getElementById('excerpt').value.trim();
  const hiddenContent = document.getElementById('content');
  const content = hiddenContent ? hiddenContent.value : '';
  const author = document.getElementById('author_name').value.trim() || 'MY UGC VIDEO Editorial Team';
  const role = document.getElementById('author_role').value.trim() || 'UGC Creative Studio';
  const bio = document.getElementById('author_bio').value.trim();
  const catSelect = document.getElementById('category_id');
  const catText = catSelect.selectedIndex > 0 ? catSelect.options[catSelect.selectedIndex].text : 'UGC Strategy';
  const status = document.getElementById('formStatus').value;

  // Title
  document.getElementById('previewTitleDisplay').textContent = title || 'Your Headline Will Appear Here in Real-Time';
  document.getElementById('previewBreadcrumbTitle').textContent = title || 'Untitled Article';

  // Excerpt
  document.getElementById('previewExcerptDisplay').textContent = excerpt || 'The lead excerpt paragraph providing a high-level overview of the strategic analysis will be rendered live right here.';

  // Category Tag
  document.getElementById('previewCategoryTag').textContent = catText;

  // Date
  const dateInput = document.getElementById('published_at').value;
  if (dateInput) {
    const d = new Date(dateInput);
    if (!isNaN(d.getTime())) {
      const options = { year: 'numeric', month: 'long', day: 'numeric' };
      document.getElementById('previewDateTag').textContent = d.toLocaleDateString('en-US', options);
    }
  }

  // Word count & Reading time
  const visualEditor = document.getElementById('visualEditor');
  const rawText = visualEditor ? visualEditor.innerText : content.replace(/<[^>]*>/g, ' ');
  const cleanText = rawText.replace(/\s+/g, ' ').trim();
  const words = cleanText ? cleanText.split(' ').length : 0;
  document.getElementById('wordCountDisplay').textContent = words + ' words';
  
  const customReading = document.getElementById('reading_time').value.trim();
  const computedReading = customReading || (Math.max(1, Math.ceil(words / 200)) + ' Min Read');
  document.getElementById('previewReadingTimeTag').textContent = computedReading;

  // Author Box
  document.getElementById('previewAuthorTag').textContent = 'By ' + author;
  document.getElementById('previewAuthorNameDisplay').textContent = author;
  document.getElementById('previewAuthorRoleDisplay').textContent = role;
  document.getElementById('previewAuthorBioDisplay').textContent = bio;
  document.getElementById('previewAuthorAvatar').textContent = author.charAt(0).toUpperCase();

  // Draft indicator
  const draftBadge = document.getElementById('previewDraftIndicator');
  if (draftBadge) {
    draftBadge.style.display = (status === 'draft') ? 'block' : 'none';
  }

  // Prose Body Render
  const proseEl = document.getElementById('previewProseBody');
  if (content.trim()) {
    proseEl.innerHTML = content;
  } else {
    proseEl.innerHTML = '<p style="color: var(--text-dim); font-style: italic;">Start typing your editorial article body in the editor panel. Headings, bullet points, blockquotes and takeaway cards will render live with the website\'s exact editorial typography.</p>';
  }
}

function setDeviceView(mode, btn) {
  document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const wrapper = document.getElementById('previewFrameWrapper');
  wrapper.classList.remove('sim-tablet', 'sim-mobile');
  if (mode === 'tablet') wrapper.classList.add('sim-tablet');
  if (mode === 'mobile') wrapper.classList.add('sim-mobile');
}

function submitAsStatus(statusVal) {
  syncEditorToContent();
  document.getElementById('formStatus').value = statusVal;
  document.getElementById('postForm').submit();
}

// Initial live render on load & event listeners
document.addEventListener('DOMContentLoaded', function() {
  const visualEditor = document.getElementById('visualEditor');
  const hiddenContent = document.getElementById('content');

  if (visualEditor && hiddenContent) {
    // In edit mode, hiddenContent has initial HTML; load it into the visual editor if empty
    if (hiddenContent.value && !visualEditor.innerHTML) {
      visualEditor.innerHTML = hiddenContent.value;
    }

    // Input events for real-time synchronization
    visualEditor.addEventListener('input', syncEditorToContent);
    visualEditor.addEventListener('keyup', updateToolbarActiveStates);
    visualEditor.addEventListener('mouseup', updateToolbarActiveStates);
    
    // Ctrl+K Link shortcut
    visualEditor.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        openLinkModal();
      }
    });

    // Paste handling
    visualEditor.addEventListener('paste', function() {
      setTimeout(syncEditorToContent, 10);
    });
  }

  // Active state listener on document selection
  document.addEventListener('selectionchange', function() {
    const active = document.activeElement;
    if (active && (active.id === 'visualEditor' || active.closest('#visualEditor'))) {
      updateToolbarActiveStates();
      checkTableContext();
    }
  });

  // Intercept form submit to ensure sync and validation
  const form = document.getElementById('postForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      syncEditorToContent();
      const contentVal = document.getElementById('content').value.trim();
      if (!contentVal) {
        e.preventDefault();
        alert('Please enter body content for your article before saving.');
        if (visualEditor) visualEditor.focus();
        return false;
      }
    });
  }

  // Render initial FAQs and Schema
  renderFaqItems();
  toggleSchemaMode(currentSchemaMode);
  updateLivePreview();
});
</script>

<!-- Hyperlink Modal -->
<div id="linkModal" class="editor-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="linkModalTitle">
  <div class="editor-modal-card">
    <div class="editor-modal-header">
      <h3 id="linkModalTitle" class="editor-modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        <span>Insert / Edit Hyperlink</span>
      </h3>
      <button type="button" class="editor-modal-close" onclick="closeLinkModal()" aria-label="Close">&times;</button>
    </div>
    <div class="editor-modal-body">
      <div class="form-group" style="margin-bottom: 1rem;">
        <label for="modalLinkUrl" class="form-label">Link Destination URL <span class="required">*</span></label>
        <input type="text" id="modalLinkUrl" class="form-control" placeholder="https://example.com or /services.html" onkeydown="if(event.key==='Enter'){applyLinkFromModal(); event.preventDefault();}">
        <div id="modalLinkError" style="display: none; color: #ff5252; font-size: 0.76rem; margin-top: 0.35rem;">Please enter a valid web URL (e.g. https://... or /services.html).</div>
      </div>

      <div class="form-group" style="margin-bottom: 1rem;">
        <label for="modalLinkText" class="form-label">Text to Display</label>
        <input type="text" id="modalLinkText" class="form-control" placeholder="Link label text">
      </div>

      <div class="form-group" style="margin-bottom: 0;">
        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.88rem; color: var(--text-primary);">
          <input type="checkbox" id="modalLinkNewTab" checked>
          <span>Open link in a new window / tab (<code>target="_blank"</code>)</span>
        </label>
      </div>
    </div>
    <div class="editor-modal-footer">
      <button type="button" id="modalRemoveLinkBtn" class="btn btn-secondary btn-sm" onclick="removeLinkFromModal()" style="display: none; color: #ff5252; border-color: rgba(255, 82, 82, 0.4); margin-right: auto;">
        Remove Link
      </button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeLinkModal()">
        Cancel
      </button>
      <button type="button" class="btn btn-primary btn-sm" onclick="applyLinkFromModal()">
        Apply Link
      </button>
    </div>
  </div>
</div>

<!-- Insert Table Modal -->
<div id="insertTableModal" class="editor-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="tableModalTitle">
  <div class="editor-modal-card" style="max-width: 440px;">
    <div class="editor-modal-header">
      <h3 id="tableModalTitle" class="editor-modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
        <span>Insert Editorial Table</span>
      </h3>
      <button type="button" class="editor-modal-close" onclick="closeInsertTableModal()" aria-label="Close">&times;</button>
    </div>
    <div class="editor-modal-body">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
        <div class="form-group" style="margin-bottom: 0;">
          <label for="tableRowsInput" class="form-label">Number of Rows</label>
          <input type="number" id="tableRowsInput" class="form-control" value="3" min="1" max="25">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label for="tableColsInput" class="form-label">Number of Columns</label>
          <input type="number" id="tableColsInput" class="form-control" value="3" min="1" max="10">
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 0;">
        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-white); font-size: 0.9rem;">
          <input type="checkbox" id="tableHeaderCheck" checked>
          <span>Include Header Row (<code>&lt;th&gt;</code>)</span>
        </label>
      </div>
    </div>
    <div class="editor-modal-footer">
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeInsertTableModal()">Cancel</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="insertTableFromModal()">Insert Table</button>
    </div>
  </div>
</div>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>