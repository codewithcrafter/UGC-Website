<?php
/**
 * MY UGC VIDEO - Dynamic Blog Listing Page
 * 
 * Powered by PHP & MySQL. Replaces the static blogs.html with dynamic content from MySQL,
 * preserving the exact layout, interactive category filtering, live search, and load more actions.
 */

define('CMS_ROOT', __DIR__);
require_once CMS_ROOT . '/config/config.php';

// Tag and pagination parameter handling
$tagParam = !empty($_GET['tag']) ? trim($_GET['tag']) : null;
$page = max(1, (int)($_GET['page'] ?? 1));

// 1. Fetch Featured Post (Only explicitly marked is_featured = 1, hidden when tag filter is active)
$featuredPost = empty($tagParam) ? getFeaturedPost() : null;

// 2. Fetch Categories
$categories = getAllCategories();

// 3. Fetch Posts (Supporting tag filter, category filter, and pagination)
$gridArgs = [
    'status'      => 'published',
    'is_featured' => 0,
    'page'        => $page,
    'per_page'    => 50 // Load published non-featured posts for client-side filtering via main.js
];

if ($tagParam !== null && $tagParam !== '') {
    $gridArgs['tag'] = $tagParam;
}

$gridResult = getPosts($gridArgs);
$gridPosts = $gridResult['posts'];
$totalPosts = $gridResult['total'];

// Dynamic SEO & Metadata
if ($tagParam !== null && $tagParam !== '') {
    $cleanTagName = ltrim($tagParam, '#');
    $pageTitle = '#' . $cleanTagName . ' Articles & Trends | MY UGC VIDEO';
    $metaDescription = 'Explore in-depth articles, insights and creator strategies tagged #' . $cleanTagName . ' from MY UGC VIDEO.';
} else {
    $pageTitle = 'The Creator Journal — UGC Insights, Trends & Strategy | MY UGC VIDEO';
    $metaDescription = 'Explore in-depth articles on UGC video strategy, creator management, paid social performance, and e-commerce content marketing from MY UGC VIDEO.';
}

// Canonical URL generation (Preserves valid tag and page params, strips all tracking query params)
$canonicalUrl = generateCleanCanonicalUrl('/blogs', ['tag' => $tagParam, 'page' => $page]);

// Dynamic Robots Indexing Strategy:
// Paginated pages (> 1) and empty tag filter results get 'noindex, follow' to protect crawl budget
if ($page > 1 || ($tagParam !== null && $totalPosts === 0)) {
    $robotsDirective = 'noindex, follow';
} else {
    $robotsDirective = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
}

$canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
$ogImage = $featuredPost && !empty($featuredPost['featured_image']) 
    ? (str_starts_with($featuredPost['featured_image'], 'http') ? $featuredPost['featured_image'] : $canonicalBase . '/' . ltrim($featuredPost['featured_image'], '/'))
    : $canonicalBase . '/images/blogs/blog-1.jpg';

// Structured Data (Managed via Centralized Schema Manager with fallback)
$structuredData = [
    "@context" => "https://schema.org",
    "@graph" => [
        [
            "@type" => "Organization",
            "@id" => rtrim($canonicalBase, '/') . "/#organization",
            "name" => "MY UGC VIDEO",
            "url" => rtrim($canonicalBase, '/'),
            "image" => rtrim($canonicalBase, '/') . "/images/hero/hero-ugc-production.jpg",
            "description" => "Premier performance-driven UGC video production studio and creator network connecting brands with authentic creator-led video content.",
            "email" => "hello@myugcvideo.in"
        ],
        [
            "@type" => "BreadcrumbList",
            "@id" => $canonicalUrl . "#breadcrumb",
            "itemListElement" => [
                [
                    "@type" => "ListItem",
                    "position" => 1,
                    "name" => "Home",
                    "item" => rtrim($canonicalBase, '/')
                ],
                [
                    "@type" => "ListItem",
                    "position" => 2,
                    "name" => "Blogs",
                    "item" => $canonicalUrl
                ]
            ]
        ],
        [
            "@type" => "CollectionPage",
            "@id" => $canonicalUrl . "#collection",
            "url" => $canonicalUrl,
            "name" => $pageTitle,
            "description" => $metaDescription,
            "mainEntityOfPage" => $canonicalUrl
        ],
        [
            "@type" => "WebPage",
            "@id" => $canonicalUrl . "#webpage",
            "url" => $canonicalUrl,
            "name" => $pageTitle,
            "description" => $metaDescription,
            "isPartOf" => rtrim($canonicalBase, '/') . "/",
            "publisher" => [
                "@type" => "Organization",
                "name" => "MY UGC VIDEO",
                "url" => rtrim($canonicalBase, '/')
            ]
        ]
    ]
];

if (function_exists('getPublishedPageSchemas')) {
    $mgSchemas = getPublishedPageSchemas('blog-listing');
    if (!empty($mgSchemas)) {
        $graphNodes = [];
        foreach ($mgSchemas as $sRow) {
            $parsed = json_decode($sRow['schema_data'], true);
            if ($parsed) {
                if (isset($parsed['@graph']) && is_array($parsed['@graph'])) {
                    foreach ($parsed['@graph'] as $gn) {
                        $graphNodes[] = $gn;
                    }
                } else {
                    $graphNodes[] = $parsed;
                }
            }
        }
        if (!empty($graphNodes)) {
            $structuredData = [
                "@context" => "https://schema.org",
                "@graph"   => $graphNodes
            ];
        }
    }
}

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
          <a href="<?= SITE_URL ?>/">Home</a>
          <span class="separator" aria-hidden="true">/</span>
          <?php if (!empty($tagParam)): ?>
            <a href="<?= SITE_URL ?>/blogs">Blogs</a>
            <span class="separator" aria-hidden="true">/</span>
            <span class="current-page">#<?= e(ltrim($tagParam, '#')) ?></span>
          <?php else: ?>
            <span class="current-page">Blogs</span>
          <?php endif; ?>
        </nav>

        <div class="blog-hero-inner">
          <div class="blog-hero-eyebrow-wrap">
            <span class="blog-hero-eyebrow">THE CREATOR JOURNAL</span>
          </div>

          <h1 id="blog-hero-title" class="blog-hero-title font-display">
            <?php if (!empty($tagParam)): ?>
              Articles Tagged #<?= e(ltrim($tagParam, '#')) ?>
            <?php else: ?>
              UGC Marketing Tips and Creator Trends
            <?php endif; ?>
          </h1>

          <p class="blog-hero-desc">
            <?php if (!empty($tagParam)): ?>
              Curated perspectives and tactical deep dives on #<?= e(ltrim($tagParam, '#')) ?>. Explore creator frameworks, production workflows, and real performance data.
            <?php else: ?>
              Fresh ideas on UGC video, creator culture and the way brands tell their stories today. Our team shares practical UGC marketing tips, video ad trends and creator content strategies you can put to work right away.
            <?php endif; ?>
          </p>
        </div>
      </div>
    </section>

    <!-- =========================================================================
         SECTION 02 — FEATURED BLOG (Shown on general listing, omitted during tag filtering)
         ========================================================================= -->
    <?php if ($featuredPost): 
      $featImg = !empty($featuredPost['featured_image']) ? SITE_URL . '/' . ltrim($featuredPost['featured_image'], '/') : SITE_URL . '/images/blogs/blog-1.jpg';
      $featUrl = SITE_URL . '/blog/' . urlencode($featuredPost['slug']);
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
              <a href="<?= e($featUrl) ?>" class="btn btn-primary">Read the product photography guide &rarr;</a>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- =========================================================================
         SECTION 03 — THE LATEST (SEARCH, CATEGORY FILTERS, HASHTAG BANNER, GRID)
         ========================================================================= -->
    <section class="latest-blogs-section" id="latest-articles" aria-labelledby="latest-heading">
      <div class="container">
        
        <div class="latest-section-header">
          <div class="about-eyebrow-wrap">
            <span class="about-eyebrow">EXPLORE PERSPECTIVES</span>
          </div>
          <h2 id="latest-heading" class="latest-section-title font-display">
            Explore by Topic
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

        <!-- Active Hashtag Filter Banner (Displayed when ?tag=... is present) -->
        <?php if (!empty($tagParam)): ?>
          <div class="active-tag-banner" role="status">
            <div class="active-tag-info">
              <span>Showing articles tagged:</span>
              <span class="active-tag-badge">#<?= e(ltrim($tagParam, '#')) ?></span>
              <span style="color: var(--text-muted); font-size: 0.85rem;">(<?= count($gridPosts) ?> article<?= count($gridPosts) === 1 ? '' : 's' ?>)</span>
            </div>
            <a href="<?= SITE_URL ?>/blogs" class="clear-tag-btn" title="View all articles">
              <span>&times; Clear Filter</span>
            </a>
          </div>
        <?php endif; ?>

        <!-- Dynamic Feedback for Filter/Search State -->
        <div id="blogResultsSummary" class="blog-results-summary" aria-live="polite"></div>

        <!-- Blog Editorial Grid -->
        <div class="blog-editorial-grid" id="blogGrid">
          <?php foreach ($gridPosts as $post): 
            $postImg = !empty($post['featured_image']) ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : SITE_URL . '/' . ltrim($post['featured_image'], '/')) : SITE_URL . '/images/blogs/blog-1.jpg';
            $postUrl = SITE_URL . '/blog/' . urlencode($post['slug']);
            $catSlug = $post['category_slug'] ?? 'general';
            $keywords = e($post['keywords'] ?? ($post['title'] . ' ' . $post['category_name']));
            $cardTags = getPostTags((int)$post['id']);
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

              <!-- Clickable Hashtag Chips on Blog Card -->
              <?php if (!empty($cardTags)): ?>
                <div class="card-tags-row" style="display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 1rem;">
                  <?php foreach (array_slice($cardTags, 0, 3) as $ct): ?>
                    <a href="<?= SITE_URL ?>/blogs?tag=<?= urlencode($ct['name']) ?>" class="blog-hashtag-chip" style="font-size: 0.74rem; padding: 0.2rem 0.55rem;" title="Explore #<?= e($ct['name']) ?>">
                      #<?= e($ct['name']) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div class="blog-card-footer">
                <span class="blog-card-author">By <?= e($post['author_name']) ?></span>
                <a href="<?= e($postUrl) ?>" class="slider-link-action">Read Article &rarr;</a>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

        <!-- No Blogs Found Empty State -->
        <div id="noBlogsFound" class="no-blogs-found" style="<?= (count($gridPosts) === 0) ? 'display: block;' : 'display: none;' ?>">
          <div class="no-blogs-icon" aria-hidden="true">&#128269;</div>
          <h3 class="no-blogs-title font-display">
            <?= !empty($tagParam) ? 'No articles found tagged #' . e(ltrim($tagParam, '#')) : 'No articles found matching your criteria' ?>
          </h3>
          <p class="no-blogs-desc">
            <?= !empty($tagParam) ? 'We couldn\'t find any published articles matching this hashtag filter.' : 'Try adjusting your search terms or selecting another category filter.' ?>
          </p>
          <a href="<?= SITE_URL ?>/blogs" class="btn btn-secondary">Reset All Filters &rarr;</a>
        </div>

        <!-- Load More Action -->
        <?php if (count($gridPosts) > 6): ?>
        <div class="blog-load-more-wrap" id="loadMoreWrap">
          <button type="button" id="loadMoreBtn" class="btn btn-secondary btn-lg">
            Load More Articles
          </button>
        </div>
        <?php endif; ?>

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
            Ready to Make Content That Sells?
          </h2>
          <p class="about-cta-desc">
            Work with MY UGC VIDEO to build real, creator-led video campaigns that get your brand noticed and bring in customers.
          </p>
          <div class="about-cta-actions">
            <a href="<?= SITE_URL ?>/contact" class="btn btn-primary btn-lg">START A PROJECT</a>
            <a href="<?= SITE_URL ?>/services" class="btn btn-secondary btn-lg">EXPLORE OUR SERVICES</a>
          </div>
        </div>
      </div>
    </section>

  </main>

<?php include CMS_ROOT . '/includes/blog-footer.php'; ?>
