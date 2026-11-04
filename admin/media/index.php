<?php
/**
 * MY UGC VIDEO — Media Library & Asset Manager
 * Premium Dark Editorial CMS
 */

define('CMS_ROOT', dirname(dirname(__DIR__)));
require_once CMS_ROOT . '/config/config.php';

$activeNav = 'media';
$pageTitle = 'Media Library';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard.php'],
    ['name' => 'Media Library', 'url' => ADMIN_URL . '/media/index.php']
];

$errors = [];

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_file'])) {
    verifyCsrfOrDie();
    $upload = handleImageUpload($_FILES['media_file']);
    if ($upload['success']) {
        setFlash('success', 'Image uploaded successfully: ' . $upload['filename']);
        header('Location: ' . ADMIN_URL . '/media/index.php');
        exit;
    } else {
        $errors[] = $upload['error'];
    }
}

// 1. Read uploads/blogs files
$uploadFiles = [];
if (is_dir(UPLOADS_DIR)) {
    $found = glob(UPLOADS_DIR . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
    if ($found) {
        foreach ($found as $f) {
            $fn = basename($f);
            $uploadFiles[] = [
                'name' => $fn,
                'path' => 'uploads/blogs/' . $fn,
                'url'  => UPLOADS_URL . '/' . $fn,
                'time' => filemtime($f),
                'size' => filesize($f)
            ];
        }
    }
}

// 2. Read legacy images/blogs files
$legacyDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'blogs';
$legacyFiles = [];
if (is_dir($legacyDir)) {
    $foundLegacy = glob($legacyDir . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
    if ($foundLegacy) {
        foreach ($foundLegacy as $f) {
            $fn = basename($f);
            $legacyFiles[] = [
                'name' => $fn,
                'path' => 'images/blogs/' . $fn,
                'url'  => SITE_URL . '/images/blogs/' . $fn,
                'time' => filemtime($f),
                'size' => filesize($f)
            ];
        }
    }
}

usort($uploadFiles, fn($a, $b) => $b['time'] <=> $a['time']);
usort($legacyFiles, fn($a, $b) => $b['time'] <=> $a['time']);

include CMS_ROOT . '/admin/includes/header.php';
?>

<div class="page-header-bar">
  <div class="page-header-title-wrap">
    <h1>Editorial Media Library</h1>
    <p class="page-header-subtitle">
      Manage high-resolution cover photos, creator thumbnails, and visual assets used across your articles.
    </p>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger" style="display: block;">
    <ul style="margin: 0.25rem 0 0 1.25rem;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Copy Feedback Toast Notification -->
<div id="copyToast" style="display: none; position: fixed; bottom: 2rem; right: 2rem; background: var(--bg-surface-elevated); border: 1px solid var(--accent-mint); color: var(--accent-mint); padding: 0.85rem 1.25rem; border-radius: var(--radius-sm); font-weight: 700; box-shadow: var(--shadow-lg); z-index: 3000; animation: fadeIn 0.25s ease;">
  ✓ Path copied to clipboard!
</div>

<div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 2rem; align-items: start;">

  <!-- Left: Media Assets Grid -->
  <div>
    <!-- Uploaded Directory -->
    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
          <span>Uploaded Articles Media (<?= count($uploadFiles) ?>)</span>
        </h2>
      </div>
      <div class="panel-body">
        <?php if (empty($uploadFiles)): ?>
          <p style="color: var(--text-muted); padding: 2rem 0; text-align: center;">
            No uploads stored in <code>uploads/blogs/</code> yet. Use the uploader on the right to upload new imagery.
          </p>
        <?php else: ?>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.25rem;">
            <?php foreach ($uploadFiles as $file): ?>
              <div style="background: var(--bg-deep); border: 1px solid var(--border-sage); border-radius: var(--radius-sm); overflow: hidden; display: flex; flex-direction: column;">
                <div style="aspect-ratio: 16/9; overflow: hidden; background: #000c0a; border-bottom: 1px solid var(--border-sage-subtle);">
                  <img src="<?= e($file['url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div style="padding: 0.85rem; font-size: 0.78rem; display: flex; flex-direction: column; flex-grow: 1;">
                  <div style="font-weight: 700; color: var(--text-white); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($file['name']) ?>">
                    <?= e($file['name']) ?>
                  </div>
                  <div style="color: var(--text-muted); margin-top: 0.25rem;">
                    <?= round($file['size'] / 1024, 1) ?> KB &bull; <?= date('M j, Y', $file['time']) ?>
                  </div>
                  <div style="display: flex; gap: 0.4rem; margin-top: 0.75rem;">
                    <button type="button" class="btn btn-secondary btn-sm" style="flex: 1; font-size: 0.72rem; padding: 0.3rem 0.5rem;" onclick="copyToClipboard('<?= e($file['path']) ?>')">
                      Copy Path
                    </button>
                    <a href="<?= e($file['url']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding: 0.3rem 0.5rem;" title="View Full Image">
                      <svg fill="none" viewBox="0 0 24 24" stroke-width="2" style="width: 13px; height: 13px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline></svg>
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Legacy Migrated Images -->
    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
          <span>Legacy Blog Images (<?= count($legacyFiles) ?>)</span>
        </h2>
      </div>
      <div class="panel-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.25rem;">
          <?php foreach ($legacyFiles as $file): ?>
            <div style="background: var(--bg-deep); border: 1px solid var(--border-sage); border-radius: var(--radius-sm); overflow: hidden; display: flex; flex-direction: column;">
              <div style="aspect-ratio: 16/9; overflow: hidden; background: #000c0a; border-bottom: 1px solid var(--border-sage-subtle);">
                <img src="<?= e($file['url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
              </div>
              <div style="padding: 0.85rem; font-size: 0.78rem; display: flex; flex-direction: column; flex-grow: 1;">
                <div style="font-weight: 700; color: var(--text-white); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($file['name']) ?>">
                  <?= e($file['name']) ?>
                </div>
                <div style="color: var(--text-muted); margin-top: 0.25rem;">
                  <?= round($file['size'] / 1024, 1) ?> KB
                </div>
                <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 0.75rem; width: 100%; font-size: 0.72rem; padding: 0.3rem 0.5rem;" onclick="copyToClipboard('<?= e($file['path']) ?>')">
                  Copy Relative Path
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Direct Media Uploader -->
  <div class="panel">
    <div class="panel-header">
      <h2 class="panel-title">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        <span>Upload Media</span>
      </h2>
    </div>
    <div class="panel-body">
      <form method="POST" action="" enctype="multipart/form-data">
        <?= csrfField() ?>

        <div class="image-uploader-card" style="padding: 1.5rem 1rem;">
          <label for="media_file" style="cursor: pointer; display: block;">
            <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="color: var(--accent-mint); margin-bottom: 0.5rem;"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <div style="font-weight: 700; color: var(--text-white); margin-bottom: 0.25rem;">Select Image File</div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 1rem;">JPG, PNG, WebP up to 5 MB</div>
            <input type="file" id="media_file" name="media_file" class="form-control" accept="image/*" required style="cursor: pointer;">
          </label>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.25rem; padding: 0.85rem;">
          Upload to Library &rarr;
        </button>
      </form>

      <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border-sage-subtle); font-size: 0.8rem; color: var(--text-muted); line-height: 1.5;">
        <strong style="color: var(--text-white);">Usage Tip:</strong>
        <p style="margin-top: 0.35rem; margin-bottom: 0;">
          After uploading, click <em>Copy Path</em> and paste it into the image URL field or use it inside article body content via <code>&lt;img src="..."&gt;</code>.
        </p>
      </div>
    </div>
  </div>

</div>

<script>
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    const toast = document.getElementById('copyToast');
    toast.textContent = '✓ Copied path: ' + text;
    toast.style.display = 'block';
    setTimeout(() => {
      toast.style.display = 'none';
    }, 2800);
  }).catch(() => {
    prompt('Copy path manually:', text);
  });
}
</script>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>
