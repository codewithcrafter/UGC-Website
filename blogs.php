<?php
/**
 * MY UGC VIDEO - Dynamic Blog Listing Page
 * 
 * Powered by PHP & MySQL. Replaces the static blogs.html with dynamic content from MySQL,
 * preserving the exact layout, interactive category filtering, live search, and load more actions.
 */

define('CMS_ROOT', __DIR__);
require_once CMS_ROOT . '/config/config.php';

// 1. Fetch Featured Post (Only explicitly marked is_featured = 1)
$featuredPost = getFeaturedPost();

// 2. Fetch Categories
$categories = getAllCategories();

// 3. Fetch The Latest Posts (Exclusively non-featured published posts: is_featured = 0)
$gridResult = getPosts([
    'status'      => 'published',
    'is_featured' => 0,
    'per_page'    => 50 // Load published non-featured posts for client-side filtering via main.js
]);
$gridPosts = $gridResult['posts'];

// Metadata
$pageTitle = 'The Creator Journal — UGC Insights, Trends & Strategy | MY UGC VIDEO';
$metaDescription = 'Explore in-depth articles on UGC video strategy, creator management, paid social performance, and e-commerce content marketing from MY UGC VIDEO.';
$canonicalUrl = SITE_URL . '/blogs.php';
$ogImage = $featuredPost && !empty($featuredPost['featured_image']) 
    ? (str_starts_with($featuredPost['featured_image'], 'http') ? $featuredPost['featured_image'] : SITE_URL . '/' . ltrim($featuredPost['featured_image'], '/'))
    : SITE_URL . '/images/blogs/blog-1.jpg';

// Structured Data
$structuredData = [
    "@context" => "https://schema.org",
    "@type" => "CollectionPage",
    "name" => "MY UGC VIDEO Insights & Editorial Journal",
    "url" => $canonicalUrl,
    "description" => $metaDescription,
    "publisher" => [
        "@type" => "Organization",
        "name" => "MY UGC VIDEO",
        "logo" => [
            "@type" => "ImageObject",
            "url" => SITE_URL . "/images/hero/hero-creator.jpg"
        ]
    ]
];

include CMS_ROOT . '/includes/blog-header.php';
?>

  <main id="main-content">

    <!-- =========================================================================
         SECTION 01 — BLOG HEADER (EDITORIAL INTRODUCTION)
         ========================================================================= -->
    <section class="blog-hero-editorial" aria-labelledby="blog-hero-title">
      <div class="container">
        
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumbs" aria-label="Breadcrumb">
          <a href="<?= SITE_URL ?>/index.html">Home</a>
          <span class="separator" aria-hidden="true">/</span>
          <span class="current-page">Blogs</span>
        </nav>

        <div class="blog-hero-inner">
          <div class="blog-hero-eyebrow-wrap">
            <span class="blog-hero-eyebrow">THE CREATOR JOURNAL</span>
          </div>

          <h1 id="blog-hero-title" class="blog-hero-title font-display">
            IDEAS THAT INSPIRE.<br class="blog-hero-br"> CONTENT THAT CONNECTS.
          </h1>

          <p class="blog-hero-desc">
            Discover fresh perspectives on UGC, creator culture and the ideas shaping the future of brand storytelling.
          </p>
        </div>
      </div>
    </section>

    <!-- =========================================================================
         SECTION 02 — FEATURED BLOG
         ========================================================================= -->
    <?php if ($featuredPost): 
      $featImg = !empty($featuredPost['featured_image']) ? SITE_URL . '/' . ltrim($featuredPost['featured_image'], '/') : SITE_URL . '/images/blogs/blog-1.jpg';
      $featUrl = SITE_URL . '/blog.php?slug=' . urlencode($featuredPost['slug']);
    ?>
    <section class="featured-blog-section" aria-labelledby="featured-blog-title">
      <div class="container">
        <div class="featured-blog-card">
          <div class="featured-blog-media">
            <img src="<?= e($featImg) ?>" alt="<?= e($featuredPost['featured_image_alt'] ?: $featuredPost['title']) ?>" width="700" height="460" loading="eager">
            <span class="featured-badge font-display">FEATURED ARTICLE</span>
          </div>
          <div class="featured-blog-body">
            <div class="featured-meta-row">
              <span class="category-pill"><?= e($featuredPost['category_name'] ?? 'UGC Trends') ?></span>
              <span class="meta-dot" aria-hidden="true">&bull;</span>
              <span class="meta-date"><?= formatDate($featuredPost['published_at']) ?></span>
              <span class="meta-dot" aria-hidden="true">&bull;</span>
              <span class="meta-read"><?= e($featuredPost['reading_time']) ?></span>
            </div>
            <h2 id="featured-blog-title" class="featured-title font-display">
              <a href="<?= e($featUrl) ?>"><?= e($featuredPost['title']) ?></a>
            </h2>
            <p class="featured-desc">
              <?= e($featuredPost['excerpt']) ?>
            </p>
            <div class="featured-author-row">
              <span class="author-name">By <?= e($featuredPost['author_name']) ?></span>
            </div>
            <div class="featured-actions">
              <a href="<?= e($featUrl) ?>" class="btn btn-primary">Read Full Guide &rarr;</a>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- =========================================================================
         SECTION 03 — THE LATEST (SEARCH, CATEGORY FILTERS, GRID & LOAD MORE)
         ========================================================================= -->
    <section class="latest-blogs-section" id="latest-articles" aria-labelledby="latest-heading">
      <div class="container">
        
        <div class="latest-section-header">
          <div class="about-eyebrow-wrap">
            <span class="about-eyebrow">EXPLORE PERSPECTIVES</span>
          </div>
          <h2 id="latest-heading" class="latest-section-title font-display">
            THE LATEST
          </h2>
        </div>

        <!-- Search Bar and Category Filters -->
        <div class="blog-controls-wrapper">
          
          <!-- Live Search Input -->
          <div class="blog-search-box">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="blogSearchInput" class="blog-search-input" placeholder="Search blogs, topics, or keywords..." aria-label="Search blogs by title, keywords or content" autocomplete="off">
            <button type="button" id="blogSearchClear" class="blog-search-clear" aria-label="Clear search input" style="display: none;">
              &times;
            </button>
          </div>

          <!-- Category Filter Tabs -->
          <div class="blog-category-tabs" role="tablist" aria-label="Blog Category Filter">
            <button type="button" class="category-filter-btn active" data-category="all" role="tab" aria-selected="true">All Articles</button>
            <?php foreach ($categories as $cat): ?>
              <?php if ($cat['post_count'] > 0): ?>
                <button type="button" class="category-filter-btn" data-category="<?= e($cat['slug']) ?>" role="tab" aria-selected="false"><?= e($cat['name']) ?></button>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

        </div>

        <!-- Dynamic Feedback for Filter/Search State -->
        <div id="blogResultsSummary" class="blog-results-summary" aria-live="polite"></div>

        <!-- Blog Editorial Grid -->
        <div class="blog-editorial-grid" id="blogGrid">
          <?php foreach ($gridPosts as $post): 
            $postImg = !empty($post['featured_image']) ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : SITE_URL . '/' . ltrim($post['featured_image'], '/')) : SITE_URL . '/images/blogs/blog-1.jpg';
            $postUrl = SITE_URL . '/blog.php?slug=' . urlencode($post['slug']);
            $catSlug = $post['category_slug'] ?? 'general';
            $keywords = e($post['keywords'] ?? ($post['title'] . ' ' . $post['category_name']));
          ?>
          <article class="blog-card" data-category="<?= e($catSlug) ?>" data-keywords="<?= e($keywords) ?>">
            <div class="blog-card-media">
              <img src="<?= e($postImg) ?>" alt="<?= e($post['featured_image_alt'] ?: $post['title']) ?>" width="400" height="250" loading="lazy">
              <span class="category-pill-badge"><?= e($post['category_name'] ?? 'UGC') ?></span>
            </div>
            <div class="blog-card-body">
              <div class="blog-meta-compact">
                <span><?= formatDate($post['published_at']) ?></span>
                <span>&bull;</span>
                <span><?= e($post['reading_time']) ?></span>
              </div>
              <h3 class="blog-card-title">
                <a href="<?= e($postUrl) ?>"><?= e($post['title']) ?></a>
              </h3>
              <p class="blog-card-excerpt">
                <?= e($post['excerpt']) ?>
              </p>
              <div class="blog-card-footer">
                <span class="blog-card-author">By <?= e($post['author_name']) ?></span>
                <a href="<?= e($postUrl) ?>" class="slider-link-action">Read Article &rarr;</a>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

        <!-- No Blogs Found Empty State -->
        <div id="noBlogsFound" class="no-blogs-found" style="display: none;">
          <div class="no-blogs-icon" aria-hidden="true">&#128269;</div>
          <h3 class="no-blogs-title font-display">No articles found matching your criteria</h3>
          <p class="no-blogs-desc">Try adjusting your search terms or selecting another category filter.</p>
          <button type="button" id="resetBlogFiltersBtn" class="btn btn-secondary">Reset Search &amp; Filters</button>
        </div>

        <!-- Load More Action -->
        <div class="blog-load-more-wrap" id="loadMoreWrap">
          <button type="button" id="loadMoreBtn" class="btn btn-secondary btn-lg">
            Load More Articles
          </button>
        </div>

      </div>
    </section>

    <!-- =========================================================================
         SECTION 04 — RELEVANT SERVICE CTA
         ========================================================================= -->
    <section class="blog-closing-cta-section" id="blog-cta" aria-labelledby="blog-cta-heading">
      <div class="container">
        <div class="about-cta-card">
          <div class="cta-glow-mesh" aria-hidden="true"></div>
          <div class="about-eyebrow-wrap">
            <span class="about-eyebrow">PUT STRATEGY INTO ACTION</span>
          </div>
          <h2 id="blog-cta-heading" class="about-cta-title font-display">
            READY TO CREATE CONTENT THAT CONVERTS?
          </h2>
          <p class="about-cta-desc">
            Partner with MY UGC VIDEO to craft authentic, high-performing creator-led video campaigns for your brand.
          </p>
          <div class="about-cta-actions">
            <a href="<?= SITE_URL ?>/contact.html" class="btn btn-primary btn-lg">START A PROJECT</a>
            <a href="<?= SITE_URL ?>/services.html" class="btn btn-secondary btn-lg">EXPLORE OUR SERVICES</a>
          </div>
        </div>
      </div>
    </section>

  </main>

<?php include CMS_ROOT . '/includes/blog-footer.php'; ?>

