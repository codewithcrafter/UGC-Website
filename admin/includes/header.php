<?php
/**
 * MY UGC VIDEO — Admin CMS Header Partial
 * Premium Dark Editorial CMS Layout
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(dirname(__DIR__)));
}
require_once CMS_ROOT . '/config/config.php';

// Enforce admin authentication
requireAuth();

$currentAdmin = getCurrentAdmin();
$activeNav = $activeNav ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Admin Dashboard';
$breadcrumbs = $breadcrumbs ?? [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard']
];

try {
    $newLeadsBadgeCount = (int)getDb()->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
} catch (Throwable $e) {
    $newLeadsBadgeCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> — MY UGC VIDEO Editorial CMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/admin/assets/admin.css?v=<?= getAssetVersion('admin/assets/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">

  <!-- Mobile Drawer Backdrop -->
  <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

  <!-- Editorial Sidebar -->
  <aside id="adminSidebar" class="admin-sidebar" aria-label="Admin Navigation">
    <div class="sidebar-header">
      <a href="<?= ADMIN_URL ?>/dashboard" class="brand-link">
        <div class="brand-mark">UGC</div>
        <div class="brand-text">
          <span class="brand-title">MY UGC VIDEO</span>
          <span class="brand-subtitle">Editorial CMS</span>
        </div>
      </a>
      <button id="sidebarCloseBtn" class="sidebar-close-btn" type="button" aria-label="Close Navigation Menu">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= ADMIN_URL ?>/dashboard" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect></svg>
        <span>Dashboard</span>
      </a>

      <a href="<?= ADMIN_URL ?>/leads" class="nav-item <?= $activeNav === 'leads' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span>Leads</span>
        <?php if (!empty($newLeadsBadgeCount) && $newLeadsBadgeCount > 0): ?>
          <span class="nav-badge" style="background: var(--accent-mint); color: #000; font-weight: 700;"><?= $newLeadsBadgeCount ?></span>
        <?php endif; ?>
      </a>

      <a href="<?= ADMIN_URL ?>/posts" class="nav-item <?= $activeNav === 'posts' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        <span>All Blog Articles</span>
      </a>

      <a href="<?= ADMIN_URL ?>/posts/create" class="nav-item <?= $activeNav === 'create_post' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        <span>Add New Blog</span>
        <span class="nav-badge">Live Preview</span>
      </a>

      <a href="<?= ADMIN_URL ?>/markdown" class="nav-item <?= $activeNav === 'markdown' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>
        <span>Markdown &amp; LLMs</span>
      </a>

      <a href="<?= ADMIN_URL ?>/categories" class="nav-item <?= $activeNav === 'categories' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
        <span>Categories</span>
      </a>

      <a href="<?= ADMIN_URL ?>/schema-manager" class="nav-item <?= $activeNav === 'schema_manager' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        <span>Schema Manager</span>
      </a>

      <a href="<?= ADMIN_URL ?>/announcement" class="nav-item <?= $activeNav === 'announcement' ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        <span>Announcement Bar</span>
      </a>

      <a href="<?= SITE_URL ?>/blogs" target="_blank" class="nav-item">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
        <span>View Live Blogs</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-snippet">
        <div class="user-avatar-badge"><?= strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)) ?></div>
        <div class="user-meta">
          <div class="user-name"><?= e($currentAdmin['name'] ?? 'Admin') ?></div>
          <div class="user-role-tag"><?= ucfirst(e($currentAdmin['role'] ?? 'Editor')) ?></div>
        </div>
      </div>
      <a href="<?= ADMIN_URL ?>/logout" class="logout-link-btn" title="Sign Out of CMS" aria-label="Sign Out">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>
    </div>
  </aside>

  <!-- Main Content Shell -->
  <div class="admin-main">
    
    <!-- Topbar Navigation -->
    <header class="admin-topbar">
      <div class="topbar-left">
        <button id="mobileMenuToggle" class="mobile-menu-toggle" type="button" aria-label="Open Navigation Menu">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>

        <nav class="admin-breadcrumbs" aria-label="Breadcrumbs">
          <?php 
          $bTotal = count($breadcrumbs);
          foreach ($breadcrumbs as $i => $bc): 
            $isLast = ($i === $bTotal - 1);
          ?>
            <?php if ($isLast): ?>
              <span class="breadcrumb-current"><?= e($bc['name']) ?></span>
            <?php else: ?>
              <a href="<?= e($bc['url']) ?>"><?= e($bc['name']) ?></a>
              <span class="breadcrumb-separator">/</span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="topbar-right">
        <a href="<?= SITE_URL ?>/blogs" target="_blank" class="btn btn-secondary btn-sm navbar-action-btn" title="View Public Blog Listing">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
          <span class="navbar-btn-label">Live Preview</span>
        </a>
        <a href="<?= ADMIN_URL ?>/posts/create" class="btn btn-primary btn-sm navbar-action-btn" title="Add New Blog Article">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          <span class="navbar-btn-label">Add New Blog</span>
        </a>
      </div>
    </header>

    <main class="admin-content">
      <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>" id="cmsAlert">
          <span><?= e($flash['message']) ?></span>
          <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; padding: 0.2rem;" aria-label="Dismiss Alert">&times;</button>
        </div>
      <?php endif; ?>
