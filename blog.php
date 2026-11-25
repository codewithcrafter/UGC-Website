<?php
/**
 * MY UGC VIDEO - Dynamic Individual Blog Detail Template
 * 
 * Renders any blog article from MySQL by slug.
 * Accessible via /blog.php?slug=example-slug and /blog/example-slug/ via mod_rewrite.
 */

define('CMS_ROOT', __DIR__);
require_once CMS_ROOT . '/config/config.php';

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    header('Location: ' . SITE_URL . '/blogs');
    exit;
}

// Fetch published post (allow preview for logged-in admins if status is draft)
$isAdmin = isLoggedIn();
$post = getPostBySlug($slug, !$isAdmin);

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Article Not Found | MY UGC VIDEO';
    $metaDescription = 'The requested blog article could not be found.';
    $robotsDirective = 'noindex, nofollow';
    $canonicalUrl = generateCleanCanonicalUrl('/blogs');
    include CMS_ROOT . '/includes/blog-header.php';
    ?>
    <main id="main-content" style="padding: clamp(8rem, 15vw, 12rem) 0; text-align: center;">
      <div class="container-narrow">
        <h1 class="font-display" style="font-size: clamp(2rem, 4vw, 3rem); margin-bottom: 1.5rem;">Article Not Found</h1>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">The blog article you are looking for may have been moved, unpublished, or does not exist.</p>
        <a href="<?= SITE_URL ?>/blogs" class="btn btn-primary">&larr; Return to All Blogs</a>
      </div>
    </main>
    <?php
    include CMS_ROOT . '/includes/blog-footer.php';
    exit;
}

// Fetch up to 3 related articles
$relatedPosts = getRelatedPosts((int) $post['id'], $post['category_id'] ? (int) $post['category_id'] : null, 3);

// Dynamic Table of Contents (H2 & H3) & Heading ID Injection
$rawContent = sanitizeHtmlContent($post['content']);
$parsedContent = parseAndInjectTableOfContents($rawContent);
$contentHtml = $parsedContent['html'];
$tocItems = $parsedContent['toc'];

// Retrieve blog-specific SEO hashtags from relational tags system
$postTags = getPostTags((int)$post['id']);

// Prepare SEO metadata
$pageTitle = !empty($post['meta_title']) ? $post['meta_title'] : ($post['title'] . ' | MY UGC VIDEO');
$metaDescription = !empty($post['meta_description']) ? $post['meta_description'] : $post['excerpt'];
$canonicalUrl = generateCleanCanonicalUrl('/blog/' . $post['slug']);
$robotsDirective = ($post['status'] === 'published')
    ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
    : 'noindex, nofollow';

$featuredImgUrl = !empty($post['featured_image'])
    ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : SITE_URL . '/' . ltrim($post['featured_image'], '/'))
    : SITE_URL . '/images/blogs/blog-1.jpg';

$ogImage = !empty($post['og_image']) ? $post['og_image'] : $featuredImgUrl;
$ogType = 'article';

// Author initials
$authorInitials = 'MU';
$nameParts = explode(' ', trim($post['author_name']));
if (count($nameParts) >= 2) {
    $authorInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
}

// Parse FAQs
$faqs = [];
if (!empty($post['faqs_json'])) {
    $decodedFaqs = json_decode($post['faqs_json'], true);
    if (is_array($decodedFaqs)) {
        $faqs = $decodedFaqs;
    }
}

// Structured Data (Centralized Schema Manager)
$structuredData = null;
if ($post['status'] === 'published') {
    if (function_exists('renderDynamicPageSchemas')) {
        $dynamicSchemas = renderDynamicPageSchemas('blog-' . $post['slug']);
        if (!empty($dynamicSchemas)) {
            $structuredData = $dynamicSchemas;
        }
    }
    if (empty($structuredData)) {
        if (function_exists('renderCentralizedBlogSchema')) {
            $structuredData = renderCentralizedBlogSchema($post, $faqs);
        } else {
            $structuredData = generateBlogSchema($post, $faqs);
        }
    }
}

/**
 * Format blog hero title with tasteful brand color highlights
 */
function renderBlogTitleWithAccent(string $title): string {
    $titleSafe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    
    $phraseHighlights = [
        'How Product Photography Shapes Online Shopping Today' => 'How Product Photography Shapes <span class="highlight-mint">Online Shopping Today</span>',
        'UGC Content Strategy for Instagram and TikTok' => 'UGC Content Strategy for <span class="highlight-mint">Instagram and TikTok</span>',
        'The Psychology of Authenticity: How UGC Directly Influences Consumer Purchasing' => 'The Psychology of Authenticity: How UGC Directly Influences <span class="highlight-mint">Consumer Purchasing</span>',
        'The Role of Product Photography in Modern E-commerce' => 'The Role of Product Photography in <span class="highlight-mint">Modern E-commerce</span>',
        'How Brands Can Work Effectively With UGC Creators' => 'How Brands Can Work Effectively With <span class="highlight-mint">UGC Creators</span>',
        '10 Creative UGC Content Ideas Every E-commerce Brand Should Try' => '10 Creative UGC Content Ideas Every E-commerce Brand <span class="highlight-mint">Should Try</span>',
        'How to Create Engaging UGC Videos for Your Brand' => 'How to Create <span class="highlight-mint">Engaging UGC Videos</span> for Your Brand',
        'UGC vs Influencer Marketing: Understanding the Strategic Differences' => 'UGC vs Influencer Marketing: Understanding the <span class="highlight-mint">Strategic Differences</span>',
        'Why UGC Marketing Is Essential for Building Brand Trust' => 'Why UGC Marketing Is Essential for <span class="highlight-mint">Building Brand Trust</span>',
        'What Is User Generated Content? A Complete Guide for Modern Brands' => 'What Is User Generated Content? A Complete Guide for <span class="highlight-mint">Modern Brands</span>',
    ];

    if (isset($phraseHighlights[$title])) {
        return $phraseHighlights[$title];
    }

    if (preg_match('/^(.*?)(\s+\S+\s+\S+)$/u', $titleSafe, $matches)) {
        return $matches[1] . '<span class="highlight-mint">' . $matches[2] . '</span>';
    }

    return $titleSafe;
}

include CMS_ROOT . '/includes/blog-header.php';
?>

  <main id="main-content" class="blog-detail-page">

    <!-- =========================================================================
         SECTION 1: BLOG HEADER HERO — TWO-COLUMN LAYOUT
         Left Column: Breadcrumbs, Taxonomy (Category Badge + Hashtags), Title, Excerpt, Author Meta
         Right Column: QUICK STRATEGY CALL Form Card
         ========================================================================= -->
    <header class="blog-header-hero">
      <div class="container">

        <?php if ($post['status'] === 'draft'): ?>
          <div style="background: rgba(255, 193, 7, 0.15); border: 1px solid #ffc107; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; color: #ffc107;">
            <strong>Admin Preview Mode:</strong> This article is currently a <strong>Draft</strong> and is only visible to authenticated administrators.
          </div>
        <?php endif; ?>

        <div class="blog-header-grid">

          <!-- LEFT COLUMN: Blog Information -->
          <div class="blog-header-intro">
            <!-- 1. Breadcrumb navigation -->
            <nav class="breadcrumbs" aria-label="Breadcrumb">
              <a href="<?= SITE_URL ?>/">Home</a>
              <span class="separator">/</span>
              <a href="<?= SITE_URL ?>/blogs">Blogs</a>
              <span class="separator">/</span>
              <span><?= e($post['title']) ?></span>
            </nav>

            <!-- 2. Blog Taxonomy: Category Badge + Hashtag Links -->
            <div class="blog-taxonomy">
              <span class="blog-category-badge"><?= e($post['category_name'] ?? 'UGC') ?></span>
              <?php if (!empty($postTags)): ?>
                <?php foreach ($postTags as $t): ?>
                  <a href="<?= SITE_URL ?>/blogs?tag=<?= urlencode($t['name']) ?>" class="blog-tag" title="Explore #<?= e($t['name']) ?>">
                    #<?= e($t['name']) ?>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- 3. Blog Title (H1) -->
            <h1 class="article-title font-display"><?= renderBlogTitleWithAccent($post['title']) ?></h1>

            <!-- 4. Blog Excerpt / Description -->
            <?php if (!empty($post['excerpt'])): ?>
              <p class="article-intro-lead"><?= e($post['excerpt']) ?></p>
            <?php endif; ?>

            <!-- 5. Author Information, Published Date, Reading Time -->
            <div class="article-header-meta">
              <span class="meta-item meta-author">By <?= e($post['author_name']) ?></span>
              <span class="meta-dot" aria-hidden="true">&bull;</span>
              <span class="meta-item meta-date"><?= formatDate($post['published_at']) ?></span>
              <span class="meta-dot" aria-hidden="true">&bull;</span>
              <span class="meta-item meta-read"><?= e($post['reading_time']) ?></span>
            </div>
          </div> <!-- /.blog-header-intro -->

          <!-- RIGHT COLUMN: Quick Strategy Call Form -->
          <div class="blog-header-cta">
            <div class="blog-strategy-card" id="blogStrategyCard">
              <div class="strategy-card-header">
                <span class="strategy-eyebrow">QUICK STRATEGY CALL</span>
                <h3 class="strategy-title font-display">Grow Your Traffic &amp; Leads</h3>
                <p class="strategy-desc">Need help executing these tactics? Connect with our senior digital marketing team.</p>
              </div>

              <form id="blogStrategyForm" class="blog-strategy-form" method="POST" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="source" value="blog_strategy_call">
                <input type="text" name="website_hp" style="display:none;" tabindex="-1" autocomplete="off">

                <div class="strategy-form-group">
                  <label for="strat_name" class="strategy-label">Full Name <span class="req">*</span></label>
                  <input type="text" id="strat_name" name="name" class="strategy-input" required placeholder="e.g. John Doe" autocomplete="name">
                  <div class="field-error" id="stratNameError"></div>
                </div>

                <div class="strategy-form-group">
                  <label for="strat_email" class="strategy-label">Email Address <span class="req">*</span></label>
                  <input type="email" id="strat_email" name="email" class="strategy-input" required placeholder="e.g. john@example.com" autocomplete="email">
                  <div class="field-error" id="stratEmailError"></div>
                </div>

                <div class="strategy-form-group">
                  <label for="strat_phone" class="strategy-label">Contact Number <span class="req">*</span></label>
                  <input type="tel" id="strat_phone" name="phone" class="strategy-input" required placeholder="e.g. 00000 00000" autocomplete="tel">
                  <div class="field-error" id="stratPhoneError"></div>
                </div>

                <button type="submit" class="strategy-submit-btn" id="stratSubmitBtn">
                  <span>Book a Strategy Call</span>
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>

                <div class="strategy-form-status" id="stratFormStatus" role="alert" style="display: none;"></div>
              </form>
            </div>
          </div> <!-- /.blog-header-cta -->

        </div> <!-- /.blog-header-grid -->
      </div> <!-- /.container -->
    </header>

    <!-- =========================================================================
         SECTION 2: FEATURED IMAGE AND ARTICLE CONTENT — MAIN TWO-COLUMN LAYOUT
         Left Column: Featured Image + Article Content (width strictly matches prose)
         Right Column: Floating Table of Contents (starts at top of featured image and stays sticky throughout)
         ========================================================================= -->
    <section class="blog-article-body-section">
      <div class="container">
        <div class="blog-article-grid">

          <!-- LEFT COLUMN: Featured Image + Article Prose + FAQs + Author + CTA -->
          <div class="blog-article-col">

            <!-- Featured Blog Image (Exact width of article prose below it) -->
            <?php if (!empty($post['featured_image'])): ?>
            <div class="article-hero-media">
              <img src="<?= e($featuredImgUrl) ?>" alt="<?= e($post['featured_image_alt'] ?: $post['title']) ?>" <?= getImageDimensionAttrs($post['featured_image']) ?> loading="eager">
            </div>
            <?php endif; ?>

            <?php if (!empty($tocItems)): ?>
            <!-- Mobile-Only Collapsible Table of Contents Accordion -->
            <details class="mobile-toc-accordion" aria-label="Table of Contents">
              <summary class="mobile-toc-summary">
                <span class="mobile-toc-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                  Table of Contents
                </span>
                <span class="mobile-toc-chevron">&darr;</span>
              </summary>
              <div class="mobile-toc-body">
                <ul class="mobile-toc-list">
                  <?php foreach ($tocItems as $h2): ?>
                    <li class="toc-item toc-level-2">
                      <a href="#<?= e($h2['id']) ?>" class="toc-link"><?= e($h2['title']) ?></a>
                      <?php if (!empty($h2['children'])): ?>
                        <ul class="toc-sublist">
                          <?php foreach ($h2['children'] as $h3): ?>
                            <li class="toc-item toc-level-3">
                              <a href="#<?= e($h3['id']) ?>" class="toc-link"><?= e($h3['title']) ?></a>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </details>
            <?php endif; ?>

            <!-- Blog Article Prose Content -->
            <div class="prose">
              <?= $contentHtml ?>
            </div>

            <!-- Frequently Asked Questions Section -->
            <?php if (!empty($faqs)): ?>
            <section class="article-faq-section" aria-labelledby="faq-section-heading" style="margin: 3.5rem 0 2.5rem; padding-top: 2.5rem; border-top: 1px solid var(--border-subtle, rgba(49, 83, 77, 0.4));">
              <div class="faq-header" style="margin-bottom: 2rem;">
                <span class="tag-mono accent" style="display: inline-block; margin-bottom: 0.5rem; font-size: 0.75rem; letter-spacing: 0.08em; text-transform: uppercase;">QUICK ANSWERS</span>
                <h2 id="faq-section-heading" class="font-display" style="font-size: clamp(1.6rem, 3vw, 2.2rem); margin: 0; color: var(--text-primary); font-family: var(--font-heading);">Frequently Asked Questions</h2>
              </div>
              <div class="faq-accordion-list" style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($faqs as $fIndex => $faq): 
                  $q = trim($faq['question'] ?? '');
                  $a = trim($faq['answer'] ?? '');
                  if ($q === '' || $a === '') continue;
                ?>
                <details class="faq-accordion-item" style="background: var(--bg-card, #001f1b); border: 1px solid var(--border-subtle, rgba(49, 83, 77, 0.4)); border-radius: var(--radius-md, 8px); padding: 1.25rem 1.5rem;">
                  <summary style="font-family: var(--font-heading); font-weight: 700; font-size: 1.08rem; color: var(--text-primary); cursor: pointer; display: flex; justify-content: space-between; align-items: center; list-style: none;">
                    <span><?= e($q) ?></span>
                    <span class="faq-toggle-icon" style="color: var(--accent-mint); font-size: 1.3rem; line-height: 1; margin-left: 1rem;">+</span>
                  </summary>
                  <div class="faq-answer-body" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(49, 83, 77, 0.25); color: var(--text-secondary); line-height: 1.75; font-size: 0.98rem;">
                    <?= nl2br(e($a)) ?>
                  </div>
                </details>
                <?php endforeach; ?>
              </div>
            </section>
            <?php endif; ?>

            <!-- Author Card -->
            <div class="article-author-card">
              <div class="author-avatar" aria-hidden="true">
                <span class="avatar-initials"><?= e($authorInitials) ?></span>
              </div>
              <div class="author-info">
                <p class="author-name"><?= e($post['author_name']) ?></p>
                <p class="author-role"><?= e($post['author_role'] ?: 'UGC Creative Studio') ?></p>
                <p class="author-bio"><?= e($post['author_bio'] ?: 'The MY UGC VIDEO editorial team specialises in creator-led content strategy, performance advertising and authentic video production for ambitious brands.') ?></p>
              </div>
            </div>

            <!-- Project CTA Box -->
            <div class="article-cta-box">
              <span class="eyebrow">WORK WITH US</span>
              <h2 class="article-cta-title">Ready to Create Content That Converts?</h2>
              <p class="article-cta-desc">MY UGC VIDEO connects ambitious brands with vetted creators to produce authentic, scroll-stopping UGC video content engineered for results.</p>
              <div class="article-cta-actions">
                <a href="<?= SITE_URL ?>/contact" class="btn btn-primary">Start a Project</a>
                <a href="<?= SITE_URL ?>/services" class="btn btn-secondary">Explore Services</a>
              </div>
            </div>

          </div> <!-- /.blog-article-col -->

          <!-- RIGHT COLUMN: Floating Sticky Table of Contents -->
          <!-- Starts vertically level with the top of the featured image and floats alongside the full article -->
          <aside class="blog-toc-col" aria-label="Table of Contents Sidebar">
            <div class="blog-toc-sticky-wrapper">
              <?php if (!empty($tocItems)): ?>
              <nav class="blog-toc-card" aria-label="Table of Contents">
                <div class="blog-toc-header">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                  <h3 class="blog-toc-title">Table of Contents</h3>
                </div>
                <ul class="blog-toc-list" id="blogTocList">
                  <?php foreach ($tocItems as $h2): ?>
                    <li class="toc-item toc-level-2" data-target="<?= e($h2['id']) ?>">
                      <a href="#<?= e($h2['id']) ?>" class="toc-link"><?= e($h2['title']) ?></a>
                      <?php if (!empty($h2['children'])): ?>
                        <ul class="toc-sublist">
                          <?php foreach ($h2['children'] as $h3): ?>
                            <li class="toc-item toc-level-3" data-target="<?= e($h3['id']) ?>">
                              <a href="#<?= e($h3['id']) ?>" class="toc-link"><?= e($h3['title']) ?></a>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </nav>
              <?php endif; ?>
            </div>
          </aside> <!-- /.blog-toc-col -->

        </div> <!-- /.blog-article-grid -->
      </div> <!-- /.container -->
    </section>

    <!-- =========================================================================
         SECTION 3: RELATED ARTICLES
         Positioned below the article body grid so TOC never overlaps it
         ========================================================================= -->
    <?php if (!empty($relatedPosts)): ?>
    <section class="related-articles-section" aria-labelledby="related-heading" style="margin-top: 4rem; padding-bottom: 5rem;">
      <div class="container">
        <header class="related-header">
          <span class="eyebrow">KEEP READING</span>
          <h2 class="related-title" id="related-heading">Related Articles</h2>
        </header>
        <div class="blog-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
          <?php foreach ($relatedPosts as $rel): 
            $relImg = !empty($rel['featured_image']) ? (str_starts_with($rel['featured_image'], 'http') ? $rel['featured_image'] : SITE_URL . '/' . ltrim($rel['featured_image'], '/')) : SITE_URL . '/images/blogs/blog-1.jpg';
            $relUrl = SITE_URL . '/blog/' . urlencode($rel['slug']);
          ?>
          <article class="blog-card" style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; display: flex; flex-direction: column;">
            <div class="blog-card-media">
              <img src="<?= e($relImg) ?>" alt="<?= e($rel['featured_image_alt'] ?: $rel['title']) ?>" <?= getImageDimensionAttrs(!empty($rel['featured_image']) ? $rel['featured_image'] : 'images/blogs/blog-1.jpg') ?> loading="lazy">
              <span class="category-pill-badge"><?= e($rel['category_name'] ?? 'UGC') ?></span>
            </div>
            <div class="blog-card-body" style="padding: 1.75rem 1.5rem; display: flex; flex-direction: column; flex-grow: 1;">
              <div class="blog-meta-compact" style="display: flex; gap: 0.5rem; font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                <span><?= formatDate($rel['published_at']) ?></span>
                <span>&bull;</span>
                <span><?= e($rel['reading_time']) ?></span>
              </div>
              <h4 class="blog-card-title" style="font-family: var(--font-heading); font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.75rem; line-height: 1.35;">
                <a href="<?= e($relUrl) ?>" style="color: inherit; text-decoration: none;"><?= e($rel['title']) ?></a>
              </h4>
              <p style="font-size: 0.88rem; line-height: 1.6; color: var(--text-secondary); margin-bottom: 1.25rem; flex-grow: 1;"><?= e($rel['excerpt']) ?></p>
              <div style="margin-top: auto; padding-top: 0.75rem; border-top: 1px solid rgba(49, 83, 77, 0.3);">
                <a href="<?= e($relUrl) ?>" class="slider-link-action" style="color: var(--accent-mint); font-weight: 700; font-size: 0.85rem; text-decoration: none;">Read Article &rarr;</a>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

  </main>

<?php include CMS_ROOT . '/includes/blog-footer.php'; ?>
