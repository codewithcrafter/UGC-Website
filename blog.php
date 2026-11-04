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
    header('Location: ' . SITE_URL . '/blogs.php');
    exit;
}

// Fetch published post (allow preview for logged-in admins if status is draft)
$isAdmin = isLoggedIn();
$post = getPostBySlug($slug, !$isAdmin);

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Article Not Found | MY UGC VIDEO';
    $metaDescription = 'The requested blog article could not be found.';
    include CMS_ROOT . '/includes/blog-header.php';
    ?>
    <main id="main-content" style="padding: clamp(8rem, 15vw, 12rem) 0; text-align: center;">
      <div class="container-narrow">
        <h1 class="font-display" style="font-size: clamp(2rem, 4vw, 3rem); margin-bottom: 1.5rem;">Article Not Found</h1>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">The blog article you are looking for may have been moved, unpublished, or does not exist.</p>
        <a href="<?= SITE_URL ?>/blogs.php" class="btn btn-primary">&larr; Return to All Blogs</a>
      </div>
    </main>
    <?php
    include CMS_ROOT . '/includes/blog-footer.php';
    exit;
}

// Fetch up to 3 related articles
$relatedPosts = getRelatedPosts((int) $post['id'], $post['category_id'] ? (int) $post['category_id'] : null, 3);

// Prepare SEO metadata
$pageTitle = !empty($post['meta_title']) ? $post['meta_title'] : ($post['title'] . ' | MY UGC VIDEO');
$metaDescription = !empty($post['meta_description']) ? $post['meta_description'] : $post['excerpt'];
$canonicalUrl = !empty($post['canonical_url']) ? $post['canonical_url'] : (SITE_URL . '/blog.php?slug=' . urlencode($post['slug']));

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

// Structured Data (Intelligent Dynamic Schema Generation)
// IMPORTANT: Draft articles must NEVER expose public schema!
$structuredData = null;
if ($post['status'] === 'published') {
    $schemaType = $post['schema_type'] ?? 'recommended';
    if (($schemaType === 'upload' || $schemaType === 'custom') && !empty($post['custom_schema'])) {
        $check = validateJsonLd($post['custom_schema']);
        if ($check['valid']) {
            $structuredData = $check['cleanJson'] ?? $post['custom_schema'];
        } else {
            // Fallback to recommended schema if uploaded schema has syntax errors
            $structuredData = generateBlogSchema($post, $faqs);
        }
    } else {
        $structuredData = generateBlogSchema($post, $faqs);
    }
}

include CMS_ROOT . '/includes/blog-header.php';
?>

  <main id="main-content">
    <article class="blog-article-page" style="padding-top: clamp(7.5rem, 13vw, 10.5rem);">
      <div class="container-narrow">

        <?php if ($post['status'] === 'draft'): ?>
          <div style="background: rgba(255, 193, 7, 0.15); border: 1px solid #ffc107; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; color: #ffc107;">
            <strong>Admin Preview Mode:</strong> This article is currently a <strong>Draft</strong> and is only visible to authenticated administrators.
          </div>
        <?php endif; ?>

        <!-- 1. BREADCRUMB -->
        <nav class="breadcrumbs" aria-label="Breadcrumb">
          <a href="<?= SITE_URL ?>/index.html">Home</a>
          <span class="separator">/</span>
          <a href="<?= SITE_URL ?>/blogs.php">Blogs</a>
          <span class="separator">/</span>
          <span><?= e($post['title']) ?></span>
        </nav>

        <!-- 2. ARTICLE HEADER -->
        <header class="article-header">
          <div class="article-header-meta">
            <span class="tag-mono accent"><?= e($post['category_name'] ?? 'UGC Marketing') ?></span>
            <span class="tag-mono"><?= e($post['reading_time']) ?></span>
            <span class="tag-mono"><?= formatDate($post['published_at']) ?></span>
            <span class="tag-mono">By <?= e($post['author_name']) ?></span>
          </div>
          <h1 class="article-title font-display"><?= e($post['title']) ?></h1>
          <?php if (!empty($post['excerpt'])): ?>
            <p class="article-intro-lead"><?= e($post['excerpt']) ?></p>
          <?php endif; ?>
        </header>

        <!-- 3. FEATURED IMAGE — 16:9, NO CAPTION -->
        <?php if (!empty($post['featured_image'])): ?>
        <div class="article-hero-media">
          <img src="<?= e($featuredImgUrl) ?>" alt="<?= e($post['featured_image_alt'] ?: $post['title']) ?>" width="1000" height="562" loading="eager">
        </div>
        <?php endif; ?>

        <!-- 4. PROSE CONTENT -->
        <div class="prose">
          <?= sanitizeHtmlContent($post['content']) ?>
        </div>

        <!-- 4B. FREQUENTLY ASKED QUESTIONS SECTION -->
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

        <!-- 5. AUTHOR BOX -->
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

        <!-- 6. PROJECT CTA BOX -->
        <div class="article-cta-box">
          <span class="eyebrow">WORK WITH US</span>
          <h2 class="article-cta-title">Ready to Create Content That Converts?</h2>
          <p class="article-cta-desc">MY UGC VIDEO connects ambitious brands with vetted creators to produce authentic, scroll-stopping UGC video content engineered for results.</p>
          <div class="article-cta-actions">
            <a href="<?= SITE_URL ?>/contact.html" class="btn btn-primary">Start a Project</a>
            <a href="<?= SITE_URL ?>/services.html" class="btn btn-secondary">Explore Services</a>
          </div>
        </div>

        <!-- 7. RELATED ARTICLES -->
        <?php if (!empty($relatedPosts)): ?>
        <section class="related-articles" aria-labelledby="related-heading">
          <header class="related-header">
            <span class="eyebrow">KEEP READING</span>
            <h2 class="related-title" id="related-heading">Related Articles</h2>
          </header>
          <div class="blog-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
            <?php foreach ($relatedPosts as $rel): 
              $relImg = !empty($rel['featured_image']) ? (str_starts_with($rel['featured_image'], 'http') ? $rel['featured_image'] : SITE_URL . '/' . ltrim($rel['featured_image'], '/')) : SITE_URL . '/images/blogs/blog-1.jpg';
              $relUrl = SITE_URL . '/blog.php?slug=' . urlencode($rel['slug']);
            ?>
            <article class="blog-card" style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; display: flex; flex-direction: column;">
              <div class="blog-card-media" style="position: relative; aspect-ratio: 16/10; overflow: hidden; background: #000c0a;">
                <img src="<?= e($relImg) ?>" alt="<?= e($rel['featured_image_alt'] ?: $rel['title']) ?>" width="600" height="375" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
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
        </section>
        <?php endif; ?>

      </div>
    </article>
  </main>

<?php include CMS_ROOT . '/includes/blog-footer.php'; ?>

