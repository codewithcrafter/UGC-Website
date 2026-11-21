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
$canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
$canonicalUrl = $canonicalUrl ?? ($canonicalBase . '/blogs');
$robotsDirective = $robotsDirective ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
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
  <meta name="robots" content="<?= e($robotsDirective) ?>">

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
  <script>window.SITE_URL = "<?= SITE_URL ?>";</script>

  <?php if (!empty($structuredData)): ?>
  <!-- Structured Data JSON-LD -->
  <script type="application/ld+json">
  <?= is_string($structuredData) ? trim($structuredData) : json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>
  </script>
  <?php endif; ?>
</head>
<body>

  <!-- SITE HEADER WRAPPER -->
  <div class="site-header-wrapper" id="siteHeaderWrapper">
    <!-- Combined Top Contact & Dynamic Announcement Bar (Single Horizontal Line on Desktop) -->
    <div class="site-top-bar" id="siteTopBar">
      <div class="container site-top-bar-container">
        <!-- Left: Contact Information -->
        <div class="top-bar-left">
          <a href="mailto:hello@myugcvideo.in" class="top-bar-link" aria-label="Email Us">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            <span>hello@myugcvideo.in</span>
          </a>
          <span class="top-bar-divider" aria-hidden="true">|</span>
          <a href="tel:+919876543210" class="top-bar-link" aria-label="Call Us">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
            <span>+91 98765 43210</span>
          </a>
        </div>

        <!-- Center: Dynamic Announcement -->
        <div class="top-bar-center" id="dynamicAnnouncementWrap" role="region" aria-label="Announcement">
          <a href="<?= SITE_URL ?>/contact" class="top-bar-announcement-link" id="dynamicAnnouncementLink">
            <span class="announcement-brand-badge" id="announcementBadge">MY UGC VIDEO</span>
            <span class="announcement-promo-text" id="announcementPromoText">Let's Create Videos That Make Your Brand Stand Out</span>
            <span class="announcement-arrow-action" aria-hidden="true">&rarr;</span>
          </a>
        </div>

        <!-- Right: Social Links -->
        <div class="top-bar-right">
          <div class="top-social-wrap">
            <span class="top-social-label">Follow Us:</span>
            <div class="top-social-links">
              <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="top-social-icon" aria-label="Facebook">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
              </a>
              <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="top-social-icon" aria-label="Instagram">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
              </a>
              <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" class="top-social-icon" aria-label="LinkedIn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
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
              <a href="<?= SITE_URL ?>/services/ai-ugc-video-creation-company" class="dropdown-link" role="menuitem">AI UGC Video Creation</a>
              <a href="<?= SITE_URL ?>/services/ugc-product-video-company" class="dropdown-link" role="menuitem">UGC Product Video</a>
              <a href="<?= SITE_URL ?>/services/ugc-product-review-videos-company" class="dropdown-link" role="menuitem">UGC Product Reviews Videos</a>
              <a href="<?= SITE_URL ?>/services/ugc-video-company-in-delhi" class="dropdown-link" role="menuitem">UGC Video Company in Delhi</a>
              <a href="<?= SITE_URL ?>/services/ugc-unboxing-videos-company" class="dropdown-link" role="menuitem">UGC Unboxing Videos</a>
              <a href="<?= SITE_URL ?>/services/ugc-testimonial-videos-company" class="dropdown-link" role="menuitem">UGC Testimonial Videos</a>
              <a href="<?= SITE_URL ?>/services/ugc-video-editing-company" class="dropdown-link" role="menuitem">UGC Video Editing Service</a>
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
  </div>

  <!-- Mobile Navigation Drawer -->
  <div class="drawer-overlay" aria-hidden="true"></div>
  <aside class="mobile-drawer" aria-label="Mobile Navigation Drawer">
        <div class="mobile-drawer-header">
      <div class="brand-logo">
        <span class="brand-title">MY UGC VIDEO</span>
        <span class="brand-subtitle">UGC Creative Agency</span>
      </div>
      <button class="drawer-close-btn" aria-label="Close navigation menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <nav class="mobile-nav-links">
      <a href="<?= SITE_URL ?>/" class="mobile-nav-link">Home <span>&rarr;</span></a>
      <a href="<?= SITE_URL ?>/about" class="mobile-nav-link">About <span>&rarr;</span></a>

      <!-- Mobile Services Collapsible -->
      <div class="mobile-dropdown-wrap">
        <div class="mobile-nav-link-row">
          <a href="<?= SITE_URL ?>/services" class="mobile-nav-link">Services</a>
          <button type="button" class="mobile-dropdown-arrow-btn" aria-label="Toggle Services submenu" aria-expanded="false">
            <svg class="dropdown-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </button>
        </div>
        <div class="mobile-dropdown-content">
          <a href="<?= SITE_URL ?>/services/ugc-video-creation-company" class="mobile-sub-link">&bull; UGC Video Creation Services</a>
          <a href="<?= SITE_URL ?>/services/ugc-video-ads-company" class="mobile-sub-link">&bull; UGC Video Ads</a>
          <a href="<?= SITE_URL ?>/services/ai-ugc-video-creation-company" class="mobile-sub-link">&bull; AI UGC Video Creation</a>
          <a href="<?= SITE_URL ?>/services/ugc-product-video-company" class="mobile-sub-link">&bull; UGC Product Video</a>
          <a href="<?= SITE_URL ?>/services/ugc-product-review-videos-company" class="mobile-sub-link">&bull; UGC Product Reviews Videos</a>
          <a href="<?= SITE_URL ?>/services/ugc-video-company-in-delhi" class="mobile-sub-link">&bull; UGC Video Company in Delhi</a>
          <a href="<?= SITE_URL ?>/services/ugc-unboxing-videos-company" class="mobile-sub-link">&bull; UGC Unboxing Videos</a>
          <a href="<?= SITE_URL ?>/services/ugc-testimonial-videos-company" class="mobile-sub-link">&bull; UGC Testimonial Videos</a>
          <a href="<?= SITE_URL ?>/services/ugc-video-editing-company" class="mobile-sub-link">&bull; UGC Video Editing Service</a>
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

