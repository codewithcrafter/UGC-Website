<?php
/**
 * MY UGC VIDEO — All Blog Articles Management
 * Premium Dark Editorial CMS
 */

define('CMS_ROOT', dirname(dirname(__DIR__)));
require_once CMS_ROOT . '/config/config.php';
requireAuth();

$activeNav = 'posts';
$pageTitle = 'All Blog Articles';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard'],
    ['name' => 'All Articles', 'url' => ADMIN_URL . '/posts']
];

$db = getDb();

// 1. Quick Status Toggle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    verifyCsrfOrDie();
    $postId = (int) ($_POST['post_id'] ?? 0);
    $currentStatus = $_POST['current_status'] ?? 'draft';
    $newStatus = ($currentStatus === 'published') ? 'draft' : 'published';

    $stmt = $db->prepare("UPDATE blog_posts SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $newStatus, ':id' => $postId]);

    // Automatically synchronize artifacts and reconciliation
    if (function_exists('syncBlogPostArtifacts')) {
        syncBlogPostArtifacts($postId);
    }
    // Sync blogs.md listing
    if (function_exists('syncBlogsMd')) {
        syncBlogsMd();
    }
    if (function_exists('runFullReconciliation')) {
        runFullReconciliation();
    }

    setFlash('success', "Article status updated to " . ucfirst($newStatus) . " & synchronization updated.");
    header('Location: ' . ADMIN_URL . '/posts');
    exit;
}

// 2. Quick Featured Toggle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_featured') {
    verifyCsrfOrDie();
    $postId = (int) ($_POST['post_id'] ?? 0);
    $currentFeatured = (int) ($_POST['current_featured'] ?? 0);
    $newFeatured = $currentFeatured ? 0 : 1;

    if ($newFeatured === 1) {
        // Enforce single featured hero spotlight
        $db->query("UPDATE blog_posts SET is_featured = 0");
    }
    $stmt = $db->prepare("UPDATE blog_posts SET is_featured = :feat WHERE id = :id");
    $stmt->execute([':feat' => $newFeatured, ':id' => $postId]);
    setFlash('success', $newFeatured ? "Article marked as Featured Spotlight." : "Article removed from Featured.");
    header('Location: ' . ADMIN_URL . '/posts');
    exit;
}

// 3. Filters
$search = trim($_GET['search'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? '');
$statusFilter = ($status === 'published' || $status === 'draft') ? $status : null;

$categories = getAllCategories();

$postsResult = getPosts([
    'status'         => $statusFilter,
    'category'       => $categorySlug,
    'search'         => $search,
    'include_future' => true,
    'per_page'       => 150
]);
$posts = $postsResult['posts'];

include CMS_ROOT . '/admin/includes/header.php';
?>

<div class="page-header-bar">
  <div class="page-header-title-wrap">
    <h1>All Blog Articles</h1>
    <p class="page-header-subtitle">
      Showing <?= count($posts) ?> editorial <?= count($posts) === 1 ? 'article' : 'articles' ?> in library
    </p>
  </div>
  <div class="page-header-actions">
    <a href="<?= ADMIN_URL ?>/posts/create" class="btn btn-primary">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Add New Article</span>
    </a>
  </div>
</div>

<div class="panel">
  <!-- Filter & Search Toolbar -->
  <div style="padding: 1.25rem 1.5rem; background: rgba(0, 18, 15, 0.4); border-bottom: 1px solid var(--border-sage);">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 0.85rem; align-items: center;">
      <div style="flex-grow: 1; min-width: 250px;">
        <input type="text" name="search" class="form-control" placeholder="Search by article title, keyword or slug..." value="<?= e($search) ?>">
      </div>

      <div style="min-width: 180px;">
        <select name="category" class="form-control">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat['slug']) ?>" <?= $categorySlug === $cat['slug'] ? 'selected' : '' ?>>
              <?= e($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width: 140px;">
        <select name="status" class="form-control">
          <option value="">All Statuses</option>
          <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
          <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
      </div>

      <button type="submit" class="btn btn-secondary">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" style="width: 15px; height: 15px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <span>Filter</span>
      </button>

      <?php if ($search || $categorySlug || $status): ?>
        <a href="<?= ADMIN_URL ?>/posts" class="btn btn-secondary" style="font-size: 0.82rem;">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width: 72px;">Media</th>
          <th>Article Details</th>
          <th>Category</th>
          <th>Status</th>
          <th>Featured</th>
          <th>Published Date</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($posts)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 3.5rem 1.5rem; color: var(--text-muted);">
              No blog articles found matching your criteria.
              <div style="margin-top: 1rem;">
                <a href="<?= ADMIN_URL ?>/posts/create" class="btn btn-primary btn-sm">Create New Blog</a>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($posts as $post): 
            $thumb = !empty($post['featured_image']) 
              ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : SITE_URL . '/' . ltrim($post['featured_image'], '/')) 
              : SITE_URL . '/images/blogs/blog-1.jpg';
            $previewUrl = SITE_URL . '/blog/' . urlencode($post['slug']);
          ?>
            <tr>
              <td>
                <div style="width: 60px; aspect-ratio: 16/9; border-radius: 4px; overflow: hidden; border: 1px solid var(--border-sage); background: #000;">
                  <img src="<?= e($thumb) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
              </td>
              <td>
                <div>
                  <a href="<?= ADMIN_URL ?>/posts/edit?id=<?= $post['id'] ?>" style="font-weight: 700; color: var(--text-white); font-size: 0.95rem;">
                    <?= e($post['title']) ?>
                  </a>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">
                  Slug: <code style="color: var(--accent-mint);"><?= e($post['slug']) ?></code> &bull; By <?= e($post['author_name']) ?>
                </div>
                <?php
                $isPub = ($post['status'] === 'published');
                if ($isPub) {
                    $hasMd = file_exists(CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'blogs' . DIRECTORY_SEPARATOR . 'post-' . $post['id'] . '-' . $post['slug'] . '.md');
                    $hasSchema = file_exists(CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'blog-' . $post['id'] . '-' . $post['slug'] . '.json');
                } else {
                    $hasMd = file_exists(CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . 'post-' . $post['id'] . '-' . $post['slug'] . '.md');
                    $hasSchema = file_exists(CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . 'blog-' . $post['id'] . '-' . $post['slug'] . '.json');
                }
                ?>
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.3rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                  <span>Markdown: <strong style="color: <?= $hasMd ? 'var(--accent-mint)' : '#ff5252' ?>;"><?= $hasMd ? ($isPub ? 'Generated' : 'Staged (Draft)') : 'Missing' ?></strong></span>
                  <span>&bull;</span>
                  <span>Schema: <strong style="color: <?= $hasSchema ? 'var(--accent-mint)' : '#ff5252' ?>;"><?= $hasSchema ? ($isPub ? 'Generated' : 'Staged (Draft)') : 'Missing' ?></strong></span>
                  <?php if (!empty($post['updated_at'])): ?>
                    <span>&bull;</span>
                    <span style="font-family: var(--font-mono); opacity: 0.85;">Synced: <?= date('M j, H:i', strtotime($post['updated_at'])) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <span class="badge-category"><?= e($post['category_name'] ?? 'Uncategorized') ?></span>
              </td>
              <td>
                <form method="POST" action="" style="display: inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                  <input type="hidden" name="current_status" value="<?= $post['status'] ?>">
                  <button type="submit" class="status-pill <?= $post['status'] === 'published' ? 'published' : 'draft' ?>" style="cursor: pointer; border: 1px solid;" title="Click to toggle Draft / Published">
                    <?= ucfirst($post['status']) ?>
                  </button>
                </form>
              </td>
              <td>
                <form method="POST" action="" style="display: inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_featured">
                  <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                  <input type="hidden" name="current_featured" value="<?= $post['is_featured'] ?>">
                  <button type="submit" class="btn btn-sm <?= $post['is_featured'] ? 'btn-outline-mint' : 'btn-secondary' ?>" style="font-size: 0.72rem; padding: 0.25rem 0.6rem; <?= $post['is_featured'] ? 'background: rgba(54, 217, 176, 0.18); font-weight: 800;' : '' ?>" title="Click to <?= $post['is_featured'] ? 'remove from Spotlight' : 'feature on Homepage' ?>">
                    <?= $post['is_featured'] ? '★ Featured' : '☆ Standard' ?>
                  </button>
                </form>
              </td>
              <td style="color: var(--text-muted); font-size: 0.85rem; white-space: nowrap;">
                <?= formatDate($post['published_at']) ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; gap: 0.4rem;">
                  <a href="<?= e($previewUrl) ?>" target="_blank" class="btn btn-secondary btn-sm" title="View Public Article">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" style="width: 14px; height: 14px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline></svg>
                    <span>View</span>
                  </a>
                  <a href="<?= ADMIN_URL ?>/posts/edit?id=<?= $post['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                  <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $post['id'] ?>, '<?= e(addslashes($post['title'])) ?>')">Delete</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
  <div class="modal-content">
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
      <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--danger-bg); color: var(--danger); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
      </div>
      <h3 style="color: var(--text-white); margin-bottom: 0;">Delete Blog Article</h3>
    </div>
    
    <p id="deleteMessage" style="color: var(--text-muted); font-size: 0.92rem; margin-bottom: 1.75rem; line-height: 1.5;">
      Are you sure you want to delete this article? This action cannot be reversed.
    </p>

    <form method="POST" action="<?= ADMIN_URL ?>/posts/delete.php">
      <?= csrfField() ?>
      <input type="hidden" id="deletePostId" name="post_id" value="">
      <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Permanently Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function confirmDelete(id, title) {
  document.getElementById('deletePostId').value = id;
  document.getElementById('deleteMessage').textContent = 'Are you sure you want to permanently delete "' + title + '"? This will remove the article and its SEO URLs completely.';
  document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});
</script>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>
