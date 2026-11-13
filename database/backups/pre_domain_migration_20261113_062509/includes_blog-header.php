<?php
/**
 * MY UGC VIDEO - Public Blog Header Partial
 * 
 * Reusable header matching the exact brand styling and navigation of MY UGC VIDEO.
 * Accepts optional page title, meta description, canonical URL, OG data, and active link marker.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

$pageTitle = $pageTitle ?? 'Blogs & Editorial Insights | MY UGC VIDEO';
$metaDescription = $metaDescription ?? 'Discover fresh perspectives on UGC, creator culture and the ideas shaping the future of brand storytelling.';
$canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.com';
$canonicalUrl = $canonicalUrl ?? ($canonicalBase . '/blogs');
$ogImage = $ogImage ?? ($canonicalBase . '/images/blogs/blog-1.jpg');
$ogType = $ogType ?? 'website';
$structuredData = $structuredData ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($metaDescription) ?>">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($metaDescription) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">

  <!-- Twitter / X -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= e($metaDescription) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">

  <!-- Fonts & Styles -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Sora:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">

  <?php if (!empty($structuredData)): ?>
  <!-- Structured Data JSON-LD -->
  <script type="application/ld+json">
  <?= is_string($structuredData) ? trim($structuredData) : json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>
  </script>
  <?php endif; ?>
</head>
<body>

  <!-- GLOBAL HEADER -->
  <header class="site-header" role="banner">
    <div class="container">
      <div class="nav-wrapper">
        <a href="<?= SITE_URL ?>/" class="brand-logo" aria-label="MY UGC VIDEO Home">
          <span class="brand-title">MY UGC VIDEO</span>
          <span class="brand-subtitle">UGC Creative Agency</span>
        </a>

        <nav class="nav-menu" role="navigation" aria-label="Primary Navigation">
          <a href="<?= SITE_URL ?>/" class="nav-link">Home</a>
          <a href="<?= SITE_URL ?>/about" class="nav-link">About</a>

          <!-- Hover-Triggered Services Dropdown -->
          <div class="nav-item-dropdown">
            <a href="<?= SITE_URL ?>/services" class="nav-link nav-link-dropdown" aria-haspopup="true" aria-expanded="false">
              Services
              <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </a>
            <div class="dropdown-menu" role="menu" aria-label="Services Menu">
              <a href="<?= SITE_URL ?>/services/ugc-video-creation-company" class="dropdown-link" role="menuitem">UGC Video Creation Services</a>
              <a href="<?= SITE_URL ?>/services/ugc-video-ads-company" class="dropdown-link" role="menuitem">UGC Video Ads</a>
              <a href="<?= SITE_URL ?>/services/ai-ugc-videos-company" class="dropdown-link" role="menuitem">AI UGC Video Creation</a>
              <a href="<?= SITE_URL ?>/services/ugc-product-videos-company" class="dropdown-link" role="menuitem">UGC Product Video</a>
              <a href="<?= SITE_URL ?>/services/ugc-product-reviews-videos-company" class="dropdown-link" role="menuitem">UGC Product Reviews Videos</a>
              <a href="<?= SITE_URL ?>/services/ugc-video-service-in-delhi-company" class="dropdown-link" role="menuitem">UGC Video Company in Delhi</a>
              <a href="<?= SITE_URL ?>/services/ugc-unboxing-videos-company" class="dropdown-link" role="menuitem">UGC Unboxing Videos</a>
              <a href="<?= SITE_URL ?>/services/ugc-testimonial-videos-company" class="dropdown-link" role="menuitem">UGC Testimonial Videos</a>
              <a href="<?= SITE_URL ?>/services/ugc-video-editing-service-company" class="dropdown-link" role="menuitem">UGC Video Editing Service</a>
            </div>
          </div>

          <a href="<?= SITE_URL ?>/blogs" class="nav-link active">Blogs</a>
          <a href="<?= SITE_URL ?>/contact" class="nav-link">Contact</a>
        </nav>

        <div class="header-cta">
          <a href="<?= SITE_URL ?>/contact" class="btn btn-primary">Start a Project</a>
          <button class="nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
          </button>
        </div>
      </div>
    </div>
  </header>

  <!-- Mobile Navigation Drawer -->
  <div class="drawer-overlay" aria-hidden="true"></div>
  <aside class="mobile-drawer" aria-label="Mobile Navigation Drawer">
    <nav class="mobile-nav-links">
      <a href="<?= SITE_URL ?>/" class="mobile-nav-link">Home <span>&rarr;</span></a>
      <a href="<?= SITE_URL ?>/about" class="mobile-nav-link">About <span>&rarr;</span></a>

      <!-- Mobile Services Collapsible -->
      <div class="mobile-dropdown-wrap">
        <a href="<?= SITE_URL ?>/services" class="mobile-nav-link mobile-dropdown-toggle" aria-expanded="false">
          Services
          <svg class="dropdown-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </a>
        <div class="mobile-dropdown-content">
          <a href="<?= SITE_URL ?>/services/ugc-video-creation-company" class="mobile-sub-link">&bull; UGC Video Creation Services</a>
          <a href="<?= SITE_URL ?>/services/ugc-video-ads-company" class="mobile-sub-link">&bull; UGC Video Ads</a>
          <a href="<?= SITE_URL ?>/services/ai-ugc-videos-company" class="mobile-sub-link">&bull; AI UGC Video Creation</a>
          <a href="<?= SITE_URL ?>/services/ugc-product-videos-company" class="mobile-sub-link">&bull; UGC Product Video</a>
          <a href="<?= SITE_URL ?>/services/ugc-product-reviews-videos-company" class="mobile-sub-link">&bull; UGC Product Reviews Videos</a>
          <a href="<?= SITE_URL ?>/services/ugc-video-service-in-delhi-company" class="mobile-sub-link">&bull; UGC Video Company in Delhi</a>
          <a href="<?= SITE_URL ?>/services/ugc-unboxing-videos-company" class="mobile-sub-link">&bull; UGC Unboxing Videos</a>
          <a href="<?= SITE_URL ?>/services/ugc-testimonial-videos-company" class="mobile-sub-link">&bull; UGC Testimonial Videos</a>
          <a href="<?= SITE_URL ?>/services/ugc-video-editing-service-company" class="mobile-sub-link">&bull; UGC Video Editing Service</a>
        </div>
      </div>

      <a href="<?= SITE_URL ?>/blogs" class="mobile-nav-link active">Blogs <span>&rarr;</span></a>
      <a href="<?= SITE_URL ?>/contact" class="mobile-nav-link">Contact <span>&rarr;</span></a>
    </nav>
    <div class="mobile-drawer-footer">
      <p class="eyebrow">UGC Creative Agency</p>
      <a href="<?= SITE_URL ?>/contact.html" class="btn btn-accent" style="width: 100%;">Start a Project</a>
    </div>
  </aside>

