<?php
/**
 * MY UGC VIDEO - Automated Static Blog to MySQL Migration Script
 * 
 * Safely parses all existing HTML files in blogs/ directory, preserves all formatting,
 * extracts SEO metadata, categories, author information, images, and content,
 * then inserts or updates records in the MySQL database.
 * 
 * Usage:
 * CLI:     php database/migrate_existing_blogs.php [--dry-run]
 * Browser: http://localhost/UGC%20Website/database/migrate_existing_blogs.php[?dry_run=1]
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

// CLI or Web detection
$isCli = (php_sapi_name() === 'cli');
$dryRun = false;

if ($isCli) {
    global $argv;
    $dryRun = in_array('--dry-run', $argv ?? []);
} else {
    $dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';
}

function out(string $msg, bool $isCli) {
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')) . "<br>\n";
    }
}

out("=========================================================================", $isCli);
out("MY UGC VIDEO - STATIC BLOG TO MYSQL MIGRATION", $isCli);
out("Mode: " . ($dryRun ? "DRY RUN (No database modifications)" : "LIVE MIGRATION"), $isCli);
out("=========================================================================\n", $isCli);

$blogsDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'blogs';
if (!is_dir($blogsDir)) {
    out("ERROR: Blogs directory not found at: {$blogsDir}", $isCli);
    exit(1);
}

$files = glob($blogsDir . DIRECTORY_SEPARATOR . '*.html');
if (empty($files)) {
    out("ERROR: No HTML blog files found in: {$blogsDir}", $isCli);
    exit(1);
}

out("Found " . count($files) . " blog files to process.\n", $isCli);

$db = getDb();

// Category mapping helper
function getOrCreateCategory(PDO $db, string $catName): int {
    $slug = slugify($catName);
    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $insert = $db->prepare("INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :desc)");
    $desc = "Articles and expert guides covering " . $catName;
    $insert->execute([':name' => $catName, ':slug' => $slug, ':desc' => $desc]);
    return (int) $db->lastInsertId();
}

$report = [
    'total'     => count($files),
    'migrated'  => 0,
    'updated'   => 0,
    'failed'    => 0,
    'details'   => []
];

foreach ($files as $file) {
    $filename = basename($file);
    $slug = str_replace('.html', '', $filename);
    $html = file_get_contents($file);

    out("--- Processing: {$filename} (slug: {$slug}) ---", $isCli);

    // 1. Meta Title
    preg_match('/<title>(.*?)<\/title>/is', $html, $m);
    $metaTitle = trim($m[1] ?? '');

    // 2. Meta Description
    preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $m);
    $metaDescription = trim($m[1] ?? '');

    // 3. Canonical URL
    preg_match('/<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']/is', $html, $m);
    $canonicalUrl = trim($m[1] ?? '');

    // 4. OG Image
    preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/is', $html, $m);
    $ogImage = trim($m[1] ?? '');

    // 5. JSON-LD datePublished
    preg_match('/"datePublished":\s*"([^"]+)"/is', $html, $m);
    $datePublishedIso = trim($m[1] ?? '');

    // 6. Header Meta: Category, Reading Time, Display Date, Author
    preg_match('/<div class="article-header-meta">(.*?)<\/div>/is', $html, $m);
    $headerMetaHtml = $m[1] ?? '';

    preg_match('/<span class="tag-mono accent">(.*?)<\/span>/is', $headerMetaHtml, $m);
    $categoryName = trim(strip_tags($m[1] ?? 'UGC Marketing'));

    preg_match('/<span class="tag-mono">(\d+\s*Min\s*Read)<\/span>/is', $headerMetaHtml, $m);
    $readingTime = trim(strip_tags($m[1] ?? '8 Min Read'));

    preg_match('/<span class="tag-mono">((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d+,\s+\d+)<\/span>/is', $headerMetaHtml, $m);
    $displayDate = trim($m[1] ?? '');

    preg_match('/<span class="tag-mono">By\s+(.*?)<\/span>/is', $headerMetaHtml, $m);
    $authorName = trim(strip_tags($m[1] ?? 'MY UGC VIDEO Editorial Team'));

    // Compute SQL published_at
    if (!empty($datePublishedIso)) {
        try {
            $dt = new DateTime($datePublishedIso);
            $publishedAt = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $publishedAt = date('Y-m-d H:i:s');
        }
    } elseif (!empty($displayDate)) {
        try {
            $dt = new DateTime($displayDate);
            $publishedAt = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $publishedAt = date('Y-m-d H:i:s');
        }
    } else {
        $publishedAt = date('Y-m-d H:i:s');
    }

    // 7. H1 Title
    preg_match('/<h1[^>]*class=["\'][^"\']*article-title[^"\']*["\'][^>]*>(.*?)<\/h1>/is', $html, $m);
    $title = trim(strip_tags($m[1] ?? ''));
    if (empty($title)) {
        preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m);
        $title = trim(strip_tags($m[1] ?? ''));
    }

    // 8. Lead Excerpt
    preg_match('/<p[^>]*class=["\'][^"\']*article-intro-lead[^"\']*["\'][^>]*>(.*?)<\/p>/is', $html, $m);
    $excerpt = trim(strip_tags($m[1] ?? ''));
    if (empty($excerpt)) {
        $excerpt = $metaDescription;
    }

    // 9. Featured Hero Image & Alt
    preg_match('/<div class="article-hero-media">.*?<img[^>]*src=["\']([^"\']+)["\'][^>]*alt=["\']([^"\']*)["\']/is', $html, $m);
    $rawImageSrc = trim($m[1] ?? '');
    $imageAlt = trim($m[2] ?? $title);

    // Normalize image src (remove leading ../)
    $featuredImage = ltrim(str_replace('../', '', $rawImageSrc), '/');

    // 10. Prose Content
    $content = '';
    if (preg_match('/<div class="prose">(.*?)(?:<\/div>\s*<!--\s*5\.|\s*<div class="article-author-card">)/is', $html, $m)) {
        $content = trim($m[1]);
    } else {
        out("WARNING: Could not delimit prose body with standard tags for {$filename}. Attempting fallback.", $isCli);
        if (preg_match('/<div class="prose">(.*?)<\/article>/is', $html, $m)) {
            $content = trim($m[1]);
        }
    }

    // Sanitize relative links in content: convert href="../about.html" to "about.html", href="../services.html" to "services.html"
    $content = str_replace(['href="../', 'src="../'], ['href="', 'src="'], $content);

    // 11. Author Card Bio & Role
    preg_match('/<p class="author-role">(.*?)<\/p>/is', $html, $m);
    $authorRole = trim(strip_tags($m[1] ?? 'UGC Creative Studio'));

    preg_match('/<p class="author-bio">(.*?)<\/p>/is', $html, $m);
    $authorBio = trim(strip_tags($m[1] ?? 'The MY UGC VIDEO editorial team specialises in creator-led content strategy, performance advertising and authentic video production for ambitious brands.'));

    // Check if this article is featured (e.g. what-is-ugc)
    $isFeatured = ($slug === 'what-is-ugc') ? 1 : 0;

    out("  Title: {$title}", $isCli);
    out("  Category: {$categoryName}", $isCli);
    out("  Published At: {$publishedAt}", $isCli);
    out("  Reading Time: {$readingTime}", $isCli);
    out("  Featured Image: {$featuredImage}", $isCli);
    out("  Content Length: " . strlen($content) . " chars", $isCli);

    if (empty($title) || empty($content)) {
        out("  [FAILED] Missing critical title or content!", $isCli);
        $report['failed']++;
        $report['details'][] = ['slug' => $slug, 'status' => 'FAILED', 'reason' => 'Empty title or content'];
        continue;
    }

    if (!$dryRun) {
        $catId = getOrCreateCategory($db, $categoryName);

        // Check if slug already exists
        $checkStmt = $db->prepare("SELECT id FROM blog_posts WHERE slug = :slug");
        $checkStmt->execute([':slug' => $slug]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            // Update
            $updateStmt = $db->prepare(
                "UPDATE blog_posts SET 
                    title = :title,
                    excerpt = :excerpt,
                    content = :content,
                    featured_image = :featured_image,
                    featured_image_alt = :featured_image_alt,
                    author_name = :author_name,
                    author_role = :author_role,
                    author_bio = :author_bio,
                    category_id = :category_id,
                    status = 'published',
                    is_featured = :is_featured,
                    published_at = :published_at,
                    reading_time = :reading_time,
                    meta_title = :meta_title,
                    meta_description = :meta_description,
                    canonical_url = :canonical_url,
                    og_image = :og_image,
                    keywords = :keywords,
                    updated_at = NOW()
                 WHERE id = :id"
            );
            $updateStmt->execute([
                ':title'              => $title,
                ':excerpt'            => $excerpt,
                ':content'            => $content,
                ':featured_image'     => $featuredImage,
                ':featured_image_alt' => $imageAlt,
                ':author_name'        => $authorName,
                ':author_role'        => $authorRole,
                ':author_bio'         => $authorBio,
                ':category_id'        => $catId,
                ':is_featured'        => $isFeatured,
                ':published_at'       => $publishedAt,
                ':reading_time'       => $readingTime,
                ':meta_title'         => $metaTitle,
                ':meta_description'   => $metaDescription,
                ':canonical_url'      => $canonicalUrl,
                ':og_image'           => $ogImage,
                ':keywords'           => $categoryName . ', UGC video, creator marketing',
                ':id'                 => $existingId
            ]);
            out("  [UPDATED] Post ID: {$existingId}", $isCli);
            $report['updated']++;
            $report['details'][] = ['slug' => $slug, 'status' => 'UPDATED', 'id' => $existingId];
        } else {
            // Insert
            $insertStmt = $db->prepare(
                "INSERT INTO blog_posts (
                    title, slug, excerpt, content, featured_image, featured_image_alt,
                    author_name, author_role, author_bio, category_id, status, is_featured,
                    published_at, reading_time, meta_title, meta_description, canonical_url,
                    og_image, keywords
                ) VALUES (
                    :title, :slug, :excerpt, :content, :featured_image, :featured_image_alt,
                    :author_name, :author_role, :author_bio, :category_id, 'published', :is_featured,
                    :published_at, :reading_time, :meta_title, :meta_description, :canonical_url,
                    :og_image, :keywords
                )"
            );
            $insertStmt->execute([
                ':title'              => $title,
                ':slug'               => $slug,
                ':excerpt'            => $excerpt,
                ':content'            => $content,
                ':featured_image'     => $featuredImage,
                ':featured_image_alt' => $imageAlt,
                ':author_name'        => $authorName,
                ':author_role'        => $authorRole,
                ':author_bio'         => $authorBio,
                ':category_id'        => $catId,
                ':is_featured'        => $isFeatured,
                ':published_at'       => $publishedAt,
                ':reading_time'       => $readingTime,
                ':meta_title'         => $metaTitle,
                ':meta_description'   => $metaDescription,
                ':canonical_url'      => $canonicalUrl,
                ':og_image'           => $ogImage,
                ':keywords'           => $categoryName . ', UGC video, creator marketing'
            ]);
            $newId = $db->lastInsertId();
            out("  [INSERTED] New Post ID: {$newId}", $isCli);
            $report['migrated']++;
            $report['details'][] = ['slug' => $slug, 'status' => 'INSERTED', 'id' => $newId];
        }
    } else {
        out("  [DRY RUN OK] Ready for migration.", $isCli);
        $report['migrated']++;
        $report['details'][] = ['slug' => $slug, 'status' => 'DRY_RUN_VALIDATED'];
    }
}

out("\n=========================================================================", $isCli);
out("MIGRATION SUMMARY:", $isCli);
out("Total Files: " . $report['total'], $isCli);
out("Migrated / Inserted: " . $report['migrated'], $isCli);
out("Updated: " . $report['updated'], $isCli);
out("Failed: " . $report['failed'], $isCli);
out("=========================================================================", $isCli);

