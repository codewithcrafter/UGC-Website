<?php
/**
 * MY UGC VIDEO — Category Management
 * Premium Dark Editorial CMS
 */

define('CMS_ROOT', dirname(dirname(__DIR__)));
require_once CMS_ROOT . '/config/config.php';
requireAuth();

$activeNav = 'categories';
$pageTitle = 'Blog Categories';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard'],
    ['name' => 'Categories', 'url' => ADMIN_URL . '/categories']
];

$db = getDb();
$errors = [];

// Handle Create Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrfOrDie();
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = 'Category name is required.';
    }

    if (empty($slug)) {
        $slug = slugify($name);
    } else {
        $slug = slugify($slug);
    }

    $check = $db->prepare("SELECT id FROM categories WHERE slug = :slug");
    $check->execute([':slug' => $slug]);
    if ($check->fetchColumn()) {
        $errors[] = 'A category with the slug "' . $slug . '" already exists.';
    }

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :desc)");
        $stmt->execute([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
        setFlash('success', 'Category "' . $name . '" created successfully!');
        header('Location: ' . ADMIN_URL . '/categories');
        exit;
    }
}

// Handle Update/Rename Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    verifyCsrfOrDie();
    $catId = (int) ($_POST['category_id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    if ($catId > 0 && $name !== '') {
        $stmt = $db->prepare("UPDATE categories SET name = :name WHERE id = :id");
        $stmt->execute([':name' => $name, ':id' => $catId]);
        if (function_exists('reconcileAllBlogsToMarkdown')) {
            reconcileAllBlogsToMarkdown();
        }
        if (function_exists('syncBlogsMd')) {
            syncBlogsMd();
        }
        setFlash('success', 'Category updated & blog Markdown synchronized.');
        header('Location: ' . ADMIN_URL . '/categories');
        exit;
    }
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verifyCsrfOrDie();
    $catId = (int) ($_POST['category_id'] ?? 0);
    if ($catId > 0) {
        $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $catId]);
        if (function_exists('reconcileAllBlogsToMarkdown')) {
            reconcileAllBlogsToMarkdown();
        }
        if (function_exists('syncBlogsMd')) {
            syncBlogsMd();
        }
        setFlash('success', 'Category deleted successfully. Associated articles have been set to Uncategorized & Markdown synchronized.');
        header('Location: ' . ADMIN_URL . '/categories');
        exit;
    }
}

$categories = getAllCategories();

include CMS_ROOT . '/admin/includes/header.php';
?>

<div class="page-header-bar">
  <div class="page-header-title-wrap">
    <h1>Blog Categories &amp; Taxonomies</h1>
    <p class="page-header-subtitle">
      Organize your editorial publications across creator-led marketing verticals and performance topics.
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

<div style="display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 2rem; align-items: start;">
  
  <!-- Left Column: Categories Table -->
  <div class="panel">
    <div class="panel-header">
      <h2 class="panel-title">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
        <span>Active Categories (<?= count($categories) ?>)</span>
      </h2>
    </div>

    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>URL Slug</th>
            <th>Articles</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($categories)): ?>
            <tr>
              <td colspan="4" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                No categories created yet. Create one using the form on the right.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($categories as $cat): ?>
              <tr>
                <td>
                  <strong><?= e($cat['name']) ?></strong>
                  <?php if (!empty($cat['description'])): ?>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem; max-width: 300px;"><?= e($cat['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td><code style="color: var(--accent-mint);"><?= e($cat['slug']) ?></code></td>
                <td>
                  <span class="badge-featured" style="background: rgba(0, 191, 165, 0.12); color: var(--accent-teal); border-color: rgba(0, 191, 165, 0.3);">
                    <?= (int) $cat['post_count'] ?> <?= (int) $cat['post_count'] === 1 ? 'post' : 'posts' ?>
                  </span>
                </td>
                <td style="text-align: right;">
                  <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete category &quot;<?= e(addslashes($cat['name'])) ?>&quot;? Associated articles will remain safe but will be set to Uncategorized.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Category">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Column: Add New Category Form -->
  <div class="panel">
    <div class="panel-header">
      <h2 class="panel-title">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        <span>Create Category</span>
      </h2>
    </div>
    <div class="panel-body">
      <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div class="form-group">
          <label for="cat_name" class="form-label">Category Name <span class="required">*</span></label>
          <input type="text" id="cat_name" name="name" class="form-control" placeholder="e.g. TikTok Ads Strategy" required oninput="autoSlugCategory(this.value)">
        </div>

        <div class="form-group">
          <label for="cat_slug" class="form-label">URL Slug</label>
          <input type="text" id="cat_slug" name="slug" class="form-control" placeholder="e.g. tiktok-ads-strategy">
          <div class="form-hint">Used for filtering and category URLs</div>
        </div>

        <div class="form-group">
          <label for="cat_desc" class="form-label">Description (Optional)</label>
          <textarea id="cat_desc" name="description" class="form-control" style="min-height: 85px;" placeholder="Brief scope of articles contained in this category..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 0.95rem; margin-top: 0.5rem;">
          + Create Category &rarr;
        </button>
      </form>
    </div>
  </div>

</div>

<script>
function autoSlugCategory(text) {
  const slugInput = document.getElementById('cat_slug');
  const slug = text.toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .trim()
    .replace(/[\s_-]+/g, '-');
  slugInput.value = slug;
}
</script>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>
