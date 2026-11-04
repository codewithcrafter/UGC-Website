<?php
/**
 * MY UGC VIDEO — Admin CMS Dashboard Overview
 * Premium Dark Editorial CMS
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

$activeNav = 'dashboard';
$pageTitle = 'Dashboard Overview';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard.php']
];

$db = getDb();

// Metrics
$totalPosts = (int) $db->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
$publishedPosts = (int) $db->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn();
$draftPosts = (int) $db->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn();
$featuredPosts = (int) $db->query("SELECT COUNT(*) FROM blog_posts WHERE is_featured = 1 AND status = 'published'")->fetchColumn();
$totalCategories = (int) $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Recent 6 Articles
$recentPosts = $db->query(
    "SELECT p.*, c.name AS category_name 
     FROM blog_posts p 
     LEFT JOIN categories c ON p.category_id = c.id 
     ORDER BY p.updated_at DESC 
     LIMIT 6"
)->fetchAll();

include CMS_ROOT . '/admin/includes/header.php';
?>

<!-- Editorial Greeting Banner -->
<div class="page-header-bar">
  <div class="page-header-title-wrap">
    <h1>Welcome back, <?= e($currentAdmin['name'] ?? 'Editor') ?></h1>
    <p class="page-header-subtitle">
      Today is <?= date('l, F j, Y') ?> &bull; Manage your editorial publications, creator insights and performance articles.
    </p>
  </div>
  <div class="page-header-actions">
    <a href="<?= ADMIN_URL ?>/posts/create.php" class="btn btn-primary">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Write New Article</span>
    </a>
  </div>
</div>

<!-- KPI Metrics Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Total Articles</div>
      <div class="stat-value"><?= $totalPosts ?></div>
    </div>
    <div class="stat-icon">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Published Live</div>
      <div class="stat-value" style="color: var(--accent-mint);"><?= $publishedPosts ?></div>
    </div>
    <div class="stat-icon" style="background: rgba(54, 217, 176, 0.1); color: var(--accent-mint); border-color: rgba(54, 217, 176, 0.3);">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Draft Articles</div>
      <div class="stat-value" style="color: var(--status-draft);"><?= $draftPosts ?></div>
    </div>
    <div class="stat-icon" style="background: rgba(229, 185, 90, 0.1); color: var(--status-draft); border-color: rgba(229, 185, 90, 0.3);">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Hero Featured</div>
      <div class="stat-value" style="color: var(--accent-teal);"><?= $featuredPosts ?></div>
    </div>
    <div class="stat-icon" style="background: rgba(0, 191, 165, 0.1); color: var(--accent-teal); border-color: rgba(0, 191, 165, 0.3);">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Categories</div>
      <div class="stat-value"><?= $totalCategories ?></div>
    </div>
    <div class="stat-icon">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
    </div>
  </div>
</div>

<!-- Quick Workflow Strips -->
<div class="quick-actions-grid">
  <a href="<?= ADMIN_URL ?>/posts/create.php" class="quick-action-card">
    <div class="quick-action-icon">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
    </div>
    <div class="quick-action-info">
      <h4>Write Article</h4>
      <p>Live Split Preview</p>
    </div>
  </a>

  <a href="<?= ADMIN_URL ?>/posts/index.php" class="quick-action-card">
    <div class="quick-action-icon">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
    </div>
    <div class="quick-action-info">
      <h4>Manage Library</h4>
      <p><?= $totalPosts ?> articles total</p>
    </div>
  </a>

  <a href="<?= ADMIN_URL ?>/markdown/index.php" class="quick-action-card">
    <div class="quick-action-icon">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>
    </div>
    <div class="quick-action-info">
      <h4>Markdown &amp; LLMs</h4>
      <p>Directives, files &amp; SEO</p>
    </div>
  </a>

  <a href="<?= SITE_URL ?>/blogs.php" target="_blank" class="quick-action-card">
    <div class="quick-action-icon">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline></svg>
    </div>
    <div class="quick-action-info">
      <h4>Live Website</h4>
      <p>blogs.php</p>
    </div>
  </a>
</div>

<!-- Recent Posts Panel -->
<div class="panel">
  <div class="panel-header">
    <h2 class="panel-title">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
      <span>Recent Editorial Activity</span>
    </h2>
    <div style="display: flex; gap: 0.6rem; align-items: center;">
      <a href="<?= ADMIN_URL ?>/posts/index.php" class="btn btn-secondary btn-sm">All Articles &rarr;</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width: 72px;">Media</th>
          <th>Article Details</th>
          <th>Category</th>
          <th>Status</th>
          <th>Published Date</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentPosts)): ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
              No blog articles created yet. <a href="<?= ADMIN_URL ?>/posts/create.php">Create your first article &rarr;</a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recentPosts as $post): 
            $thumb = !empty($post['featured_image']) 
              ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : SITE_URL . '/' . ltrim($post['featured_image'], '/')) 
              : SITE_URL . '/images/blogs/blog-1.jpg';
            $previewUrl = SITE_URL . '/blog.php?slug=' . urlencode($post['slug']);
          ?>
            <tr>
              <td>
                <div style="width: 60px; aspect-ratio: 16/9; border-radius: 4px; overflow: hidden; border: 1px solid var(--border-sage); background: #000;">
                  <img src="<?= e($thumb) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
              </td>
              <td>
                <div>
                  <a href="<?= ADMIN_URL ?>/posts/edit.php?id=<?= $post['id'] ?>" style="font-weight: 700; color: var(--text-white); font-size: 0.95rem;">
                    <?= e($post['title']) ?>
                  </a>
                  <?php if ($post['is_featured']): ?>
                    <span class="badge-featured" style="margin-left: 0.4rem;">★ Featured</span>
                  <?php endif; ?>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">
                  Slug: <code style="color: var(--accent-mint);"><?= e($post['slug']) ?></code> &bull; By <?= e($post['author_name']) ?>
                </div>
              </td>
              <td>
                <span class="badge-category"><?= e($post['category_name'] ?? 'General') ?></span>
              </td>
              <td>
                <span class="status-pill <?= $post['status'] === 'published' ? 'published' : 'draft' ?>">
                  <?= ucfirst($post['status']) ?>
                </span>
              </td>
              <td style="color: var(--text-muted); font-size: 0.85rem; white-space: nowrap;">
                <?= formatDate($post['published_at']) ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <a href="<?= e($previewUrl) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Preview Public Page">View</a>
                <a href="<?= ADMIN_URL ?>/posts/edit.php?id=<?= $post['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>
