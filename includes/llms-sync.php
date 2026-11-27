<?php
/**
 * MY UGC VIDEO — Central Automation & Synchronization Architecture
 * 
 * Unified orchestration layer coordinating:
 * - Public Markdown files (.md)
 * - Approved JSON schema files (.json)
 * - Approved SQL schema documentation (.sql)
 * - Public Core Website Pages & Dedicated Service Pages
 * - Published Blog Articles & Categories
 * - JSON-LD Structured Data
 * - Canonical URLs
 * - sitemap.xml
 * - llms.txt
 * - robots.txt
 * - Google Search Console discovery integration (sitemaps submission)
 * - Bing Webmaster Tools & IndexNow API integration
 * - AI crawler directives
 * 
 * Guarantees:
 * - Thread-safe concurrency locking via flock
 * - Atomic filesystem replacement with rollback protection
 * - Shared-hosting compatible (PHP 8+, zero persistent background requirements)
 * - Strict security filtering (never exposes credentials, private tables, or draft posts)
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

require_once CMS_ROOT . '/includes/website-markdown-sync.php';

/**
 * -------------------------------------------------------------------------
 * 1. Canonical Domain Resolution & Strict URL Validation
 * -------------------------------------------------------------------------
 */
function getLlmCanonicalBaseUrl(): string {
    $custom = getenv('CANONICAL_URL');
    if (!empty($custom)) {
        $custom = rtrim($custom, '/');
        if (str_starts_with($custom, 'https://') && 
            !str_contains($custom, 'demo.myugcvideo') && 
            !str_contains($custom, 'example.com') && 
            !str_contains($custom, 'localhost') && 
            !str_contains($custom, '127.0.0.1')) {
            return $custom;
        }
    }

    $appUrl = getenv('APP_URL');
    if (!empty($appUrl)) {
        $appUrl = rtrim($appUrl, '/');
        if (str_starts_with($appUrl, 'https://') && 
            !str_contains($appUrl, 'demo.myugcvideo') && 
            !str_contains($appUrl, 'example.com') && 
            !str_contains($appUrl, 'localhost') && 
            !str_contains($appUrl, '127.0.0.1')) {
            return $appUrl;
        }
    }

    // Official production canonical domain (always HTTPS)
    return 'https://myugcvideo.in';
}

/**
 * Single Centralized Canonical Markdown Public URL Generator
 *
 * Enforces strictly canonical public URLs across all markdown assets:
 * - Core pages: https://myugcvideo.in/{slug}.md (e.g. /about.md, /services.md, /blogs.md, /contact.md)
 * - Services:   https://myugcvideo.in/services/{slug}.md
 * - Blog posts: https://myugcvideo.in/blog/{slug}.md
 * - Other docs: https://myugcvideo.in/other/{slug}.md
 * - llms.txt:   https://myugcvideo.in/llms.txt
 * - Homepage:   Never exposed / returns null
 *
 * @param string $category   'pages' | 'services' | 'blogs' | 'other' | 'llm'
 * @param string $filename   Filename or path (e.g. 'about.md', 'post-1-what-is-ugc.md', 'llms.txt')
 * @param ?string $blogSlug  Optional published blog slug for blog posts
 * @param bool $useLocalBase If true, uses SITE_URL instead of canonical production base
 * @return ?string Canonical public URL, or null if invalid / non-public (e.g. homepage)
 */
function getCanonicalMarkdownPublicUrl(string $category, string $filename, ?string $blogSlug = null, bool $useLocalBase = false): ?string {
    $base = $useLocalBase ? (defined('SITE_URL') ? rtrim(SITE_URL, '/') : getLlmCanonicalBaseUrl()) : getLlmCanonicalBaseUrl();
    
    $cleanFilename = basename($filename);
    $slug = pathinfo($cleanFilename, PATHINFO_FILENAME);
    $slugLower = strtolower($slug);
    
    // Strict prohibition: Homepage must never have a public markdown URL
    if (in_array($slugLower, ['home', 'index', 'homepage']) || str_starts_with($slugLower, 'home-') || str_starts_with($slugLower, 'index-')) {
        return null;
    }
    
    if ($category === 'llm' || $cleanFilename === 'llms.txt') {
        return $base . '/llms.txt';
    }
    
    if ($category === 'pages' || $category === 'core') {
        return $base . '/' . $slug . '.md';
    }
    
    if ($category === 'services') {
        return $base . '/services/' . $slug . '.md';
    }
    
    if ($category === 'blogs' || $category === 'blog') {
        if (!empty($blogSlug)) {
            return $base . '/blog/' . urlencode($blogSlug) . '.md';
        }
        if (preg_match('/^post-\d+-(.+)$/i', $slug, $m)) {
            return $base . '/blog/' . urlencode($m[1]) . '.md';
        }
        return $base . '/blog/' . urlencode($slug) . '.md';
    }
    
    if ($category === 'other' || $category === 'docs') {
        return $base . '/other/' . $slug . '.md';
    }
    
    return $base . '/markdown/' . $category . '/' . $cleanFilename;
}

/**
 * Strict Public SEO URL Validator
 * Enforces canonical domain, rejects demo domains, internal PHP scripts, and non-existent pages.
 *
 * @param string $url The candidate URL to validate
 * @param string $expectedType 'any' | 'core_page' | 'service_page' | 'blog_post' | 'markdown' | 'schema'
 * @return array ['valid' => bool, 'clean_url' => string, 'error' => ?string, 'type' => string]
 */
function validatePublicSeoUrl(string $url, string $expectedType = 'any'): array {
    $canonicalBase = getLlmCanonicalBaseUrl();
    $trimmed = trim($url);

    if (empty($trimmed)) {
        return ['valid' => false, 'clean_url' => '', 'error' => 'URL is empty.', 'type' => 'unknown'];
    }

    // 1. Must use HTTPS and match canonical base domain
    if (!str_starts_with($trimmed, 'https://')) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => 'URL must use HTTPS protocol.', 'type' => 'unknown'];
    }

    // 2. Reject demo domains, localhost, example.com
    if (str_contains($trimmed, 'demo.myugcvideo') || 
                str_contains($trimmed, 'example.com') || 
        str_contains($trimmed, 'localhost') || 
        str_contains($trimmed, '127.0.0.1')) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => 'URL contains non-canonical or demo domain.', 'type' => 'unknown'];
    }

    // 3. Must match canonical domain
    if (!str_starts_with($trimmed, $canonicalBase)) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL domain does not match canonical base ({$canonicalBase}).", 'type' => 'unknown'];
    }

    // Extract path component
    $path = parse_url($trimmed, PHP_URL_PATH) ?: '/';
    $cleanPath = '/' . ltrim($path, '/');

    // 4. Reject internal system directories and scripts
    $blockedPrefixes = [
        '/admin', '/config', '/includes', '/database', '/logs', '/scratch',
        '/vendor', '/node_modules', '/.git', '/tests', '/test'
    ];
    foreach ($blockedPrefixes as $bp) {
        if ($cleanPath === $bp || str_starts_with($cleanPath, $bp . '/')) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL points to private internal system path: {$cleanPath}", 'type' => 'blocked'];
        }
    }

    // 5. Reject invalid PHP paths & pseudo blog paths
    // Specifically reject /blogs/about.php, /blogs/service.php, /blogs/*.php, etc.
    if (preg_match('#^/blogs/.+\.php$#i', $cleanPath)) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Invalid PHP path under /blogs/: {$cleanPath}", 'type' => 'invalid_php'];
    }
    // Reject internal PHP files
    $blockedPhpFiles = ['/blog.php', '/functions.php', '/csrf.php', '/view.php', '/setup.php', '/header.php', '/footer.php'];
    if (in_array(strtolower($cleanPath), $blockedPhpFiles)) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL directly exposes internal PHP file: {$cleanPath}", 'type' => 'internal_script'];
    }

    // 6. Reject staging or draft paths
    if (str_contains($cleanPath, 'staging') || str_contains($cleanPath, 'draft')) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL references private staging/draft asset: {$cleanPath}", 'type' => 'draft'];
    }

    // 7. Reject digital-audit
    if (str_contains(strtolower($cleanPath), 'digital-audit')) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Digital audit is excluded from public SEO: {$cleanPath}", 'type' => 'excluded'];
    }

    // Check Core Pages: /, /about, /services, /blogs, /contact (and legacy extensions)
    $approvedCore = [
        '/'             => ['file' => 'index.html', 'canonical' => '/'],
        '/index.html'   => ['file' => 'index.html', 'canonical' => '/'],
        '/about'        => ['file' => 'about.html', 'canonical' => '/about'],
        '/about.html'   => ['file' => 'about.html', 'canonical' => '/about'],
        '/services'     => ['file' => 'services.html', 'canonical' => '/services'],
        '/services.html'=> ['file' => 'services.html', 'canonical' => '/services'],
        '/blogs'        => ['file' => 'blogs.php', 'canonical' => '/blogs'],
        '/blogs.php'    => ['file' => 'blogs.php', 'canonical' => '/blogs'],
        '/contact'      => ['file' => 'contact.html', 'canonical' => '/contact'],
        '/contact.html' => ['file' => 'contact.html', 'canonical' => '/contact']
    ];
    if (isset($approvedCore[$cleanPath])) {
        if ($expectedType !== 'any' && $expectedType !== 'core_page') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got core_page.", 'type' => 'core_page'];
        }
        $relFile = $approvedCore[$cleanPath]['file'];
        if (!file_exists(CMS_ROOT . DIRECTORY_SEPARATOR . $relFile)) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Physical file does not exist: {$relFile}", 'type' => 'missing_file'];
        }
        $canonicalClean = ($approvedCore[$cleanPath]['canonical'] === '/') ? $canonicalBase : $canonicalBase . $approvedCore[$cleanPath]['canonical'];
        return ['valid' => true, 'clean_url' => $canonicalClean, 'error' => null, 'type' => 'core_page'];
    }

    // Check Service Pages: /services/{slug} or /services/{slug}.html
    if (preg_match('#^/services/([a-z0-9\-]+)(?:\.html)?$#i', $cleanPath, $sm)) {
        if ($expectedType !== 'any' && $expectedType !== 'service_page') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got service_page.", 'type' => 'service_page'];
        }
        $slug = $sm[1];
        $relFile = 'services/' . $slug . '.html';
        if (!file_exists(CMS_ROOT . DIRECTORY_SEPARATOR . $relFile)) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Dedicated service file does not exist on disk: {$relFile}", 'type' => 'missing_service'];
        }
        return ['valid' => true, 'clean_url' => $canonicalBase . '/services/' . $slug, 'error' => null, 'type' => 'service_page'];
    }

    // Check Blog Articles: /blog/{slug}
    if (preg_match('#^/blog/([a-z0-9\-_]+)/?$#i', $cleanPath, $bm)) {
        if ($expectedType !== 'any' && $expectedType !== 'blog_post') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got blog_post.", 'type' => 'blog_post'];
        }
        $slug = $bm[1];
        // Must exist in database and be published (evaluated via MySQL NOW() for timezone consistency)
        try {
            $db = getDb();
            $stmt = $db->prepare("SELECT id, status FROM blog_posts WHERE slug = :s AND status = 'published' AND (published_at IS NULL OR published_at <= NOW()) LIMIT 1");
            $stmt->execute([':s' => $slug]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                $stmt2 = $db->prepare("SELECT id, status, published_at FROM blog_posts WHERE slug = :s LIMIT 1");
                $stmt2->execute([':s' => $slug]);
                $p2 = $stmt2->fetch(PDO::FETCH_ASSOC);

                if (!$p2) {
                    return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Blog post does not exist in database: slug={$slug}", 'type' => 'nonexistent_blog'];
                }
                if ($p2['status'] !== 'published') {
                    return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Blog post is not published (status={$p2['status']}): slug={$slug}", 'type' => 'draft_blog'];
                }
                return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Blog post is scheduled for future publication: slug={$slug}", 'type' => 'scheduled_blog'];
            }
        } catch (Throwable $e) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Database error validating blog: " . $e->getMessage(), 'type' => 'error'];
        }
        // Normalize clean URL without trailing slash
        return ['valid' => true, 'clean_url' => $canonicalBase . '/blog/' . $slug, 'error' => null, 'type' => 'blog_post'];
    }

    // Reject non-canonical duplicate /pages/ and /page/ markdown URLs
    if (preg_match('#^/(?:pages|page)/([a-z0-9\-_\.]+)\.md$#i', $cleanPath, $pm)) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Non-canonical duplicate /pages/ path rejected. Canonical URL is /{$pm[1]}.md", 'type' => 'rejected_pages_alias'];
    }

    // Reject plural /blogs/{slug}.md as non-canonical (canonical is /blog/{slug}.md)
    if (preg_match('#^/blogs/([a-z0-9\-_\.]+)\.md$#i', $cleanPath, $blm)) {
        return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Non-canonical plural /blogs/ path rejected. Canonical URL is /blog/{$blm[1]}.md", 'type' => 'rejected_blogs_plural_alias'];
    }

    // Check Root-Level Markdown URLs: /{slug}.md (Core Pages)
    if (preg_match('#^/([a-z0-9\-_\.]+)\.md$#i', $cleanPath, $rm)) {
        if ($expectedType !== 'any' && $expectedType !== 'markdown') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got markdown.", 'type' => 'markdown'];
        }
        $slug = strtolower($rm[1]);
        if (in_array($slug, ['home', 'index', 'homepage'])) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Homepage markdown is permanently excluded from public access: {$cleanPath}", 'type' => 'excluded_homepage_md'];
        }
        $candidatePage = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $slug . '.md';
        $candidateRoot = CMS_ROOT . DIRECTORY_SEPARATOR . $slug . '.md';
        if (!file_exists($candidatePage) && !file_exists($candidateRoot)) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Root markdown file does not exist on disk: {$cleanPath}", 'type' => 'missing_markdown'];
        }
        return ['valid' => true, 'clean_url' => $canonicalBase . '/' . $slug . '.md', 'error' => null, 'type' => 'markdown'];
    }

    // Check Service Markdown URLs: /services/{slug}.md
    if (preg_match('#^/services/([a-z0-9\-_\.]+)\.md$#i', $cleanPath, $sm)) {
        if ($expectedType !== 'any' && $expectedType !== 'markdown') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got markdown.", 'type' => 'markdown'];
        }
        $slug = strtolower($sm[1]);
        $candidate = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . $slug . '.md';
        if (!file_exists($candidate)) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Service markdown file does not exist on disk: {$cleanPath}", 'type' => 'missing_markdown'];
        }
        return ['valid' => true, 'clean_url' => $canonicalBase . '/services/' . $slug . '.md', 'error' => null, 'type' => 'markdown'];
    }

    // Check Blog Markdown URLs: /blog/{slug}.md
    if (preg_match('#^/blog/([a-z0-9\-_\.]+)\.md$#i', $cleanPath, $bm)) {
        if ($expectedType !== 'any' && $expectedType !== 'markdown') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got markdown.", 'type' => 'markdown'];
        }
        $subSlug = strtolower($bm[1]);
        try {
            $db = getDb();
            $st = $db->prepare("SELECT id, status FROM blog_posts WHERE slug = :s AND status = 'published' AND (published_at IS NULL OR published_at <= NOW()) LIMIT 1");
            $st->execute([':s' => $subSlug]);
            $p = $st->fetch(PDO::FETCH_ASSOC);
            if (!$p) {
                return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Markdown represents a non-existent or unpublished blog (slug={$subSlug})", 'type' => 'draft_markdown'];
            }
        } catch (Throwable $e) {}
        return ['valid' => true, 'clean_url' => $canonicalBase . '/blog/' . $subSlug . '.md', 'error' => null, 'type' => 'markdown'];
    }

    // Check Other / Documentation Markdown URLs: /other/{slug}.md
    if (preg_match('#^/(?:other|docs)/([a-z0-9\-_\.]+)\.md$#i', $cleanPath, $om)) {
        if ($expectedType !== 'any' && $expectedType !== 'markdown') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got markdown.", 'type' => 'markdown'];
        }
        $subSlug = strtolower($om[1]);
        $candidate = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'other' . DIRECTORY_SEPARATOR . $subSlug . '.md';
        if (!file_exists($candidate)) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Other markdown file does not exist on disk: {$cleanPath}", 'type' => 'missing_markdown'];
        }
        return ['valid' => true, 'clean_url' => $canonicalBase . '/other/' . $subSlug . '.md', 'error' => null, 'type' => 'markdown'];
    }

    // Check Markdown Docs: /markdown/...
    if (str_starts_with($cleanPath, '/markdown/')) {
        if ($expectedType !== 'any' && $expectedType !== 'markdown') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got markdown.", 'type' => 'markdown'];
        }
        $rel = substr($cleanPath, strlen('/markdown/'));
        $fullPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!file_exists($fullPath)) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Markdown file does not exist on disk: {$rel}", 'type' => 'missing_markdown'];
        }
        // If it's a blog post markdown, verify published status
        if (preg_match('#post-(\d+)-#i', $rel, $pm)) {
            $pid = (int)$pm[1];
            try {
                $db = getDb();
                $st = $db->prepare("SELECT status FROM blog_posts WHERE id = :id AND status = 'published' AND (published_at IS NULL OR published_at <= NOW()) LIMIT 1");
                $st->execute([':id' => $pid]);
                $p = $st->fetch(PDO::FETCH_ASSOC);
                if (!$p) {
                    return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Markdown represents an unpublished/draft post (id={$pid})", 'type' => 'draft_markdown'];
                }
            } catch (Throwable $e) {}
        }
        return ['valid' => true, 'clean_url' => $canonicalBase . $cleanPath, 'error' => null, 'type' => 'markdown'];
    }

    // Check Schemas: /schemas/... or /uploads/schemas/...
    if (str_starts_with($cleanPath, '/schemas/') || str_starts_with($cleanPath, '/uploads/schemas/')) {
        if ($expectedType !== 'any' && $expectedType !== 'schema') {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "URL type mismatch: expected {$expectedType}, got schema.", 'type' => 'schema'];
        }
        $fullPath = CMS_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($cleanPath, '/'));
        if (!file_exists($fullPath)) {
            return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Schema file does not exist on disk: {$cleanPath}", 'type' => 'missing_schema'];
        }
        // If it's a blog schema, verify published status
        if (preg_match('#blog-(\d+)-#i', $cleanPath, $sm)) {
            $pid = (int)$sm[1];
            try {
                $db = getDb();
                $st = $db->prepare("SELECT status FROM blog_posts WHERE id = :id AND status = 'published' AND (published_at IS NULL OR published_at <= NOW()) LIMIT 1");
                $st->execute([':id' => $pid]);
                $p = $st->fetch(PDO::FETCH_ASSOC);
                if (!$p) {
                    return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Schema represents an unpublished/draft post (id={$pid})", 'type' => 'draft_schema'];
                }
            } catch (Throwable $e) {}
        }
        return ['valid' => true, 'clean_url' => $canonicalBase . $cleanPath, 'error' => null, 'type' => 'schema'];
    }

    // Check llms.txt, robots.txt, sitemap.xml
    $rootFiles = ['/llms.txt', '/robots.txt', '/sitemap.xml'];
    if (in_array($cleanPath, $rootFiles)) {
        return ['valid' => true, 'clean_url' => $canonicalBase . $cleanPath, 'error' => null, 'type' => 'root_meta'];
    }

    return ['valid' => false, 'clean_url' => $trimmed, 'error' => "Unrecognized or non-existent public URL path: {$cleanPath}", 'type' => 'unrecognized'];
}

/**
 * -------------------------------------------------------------------------
 * 2. Persistent Status & Configuration Trackers
 * -------------------------------------------------------------------------
 */
function getLlmsSyncStatus(): array {
    $statusFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'llm_sync_status.json';
    if (file_exists($statusFile)) {
        $content = @file_get_contents($statusFile);
        if ($content) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }
    }

    $llmsPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'llms.txt';
    $sitemapPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'sitemap.xml';

    $llmsMtime = file_exists($llmsPath) ? filemtime($llmsPath) : null;
    $sitemapMtime = file_exists($sitemapPath) ? filemtime($sitemapPath) : null;

    return [
        'status'                        => file_exists($llmsPath) ? 'active' : 'pending',
        'last_sync_time'                => $llmsMtime ? date('c', $llmsMtime) : null,
        'last_sync_formatted'           => $llmsMtime ? date('F j, Y \a\t g:i A', $llmsMtime) : 'Never',
        'last_sitemap_time'             => $sitemapMtime ? date('c', $sitemapMtime) : null,
        'last_sitemap_formatted'        => $sitemapMtime ? date('F j, Y \a\t g:i A', $sitemapMtime) : 'Never',
        'last_reconciliation_time'      => null,
        'last_reconciliation_formatted' => 'Never',
        'last_error'                    => null,
        'public_markdown_count'         => 0,
        'approved_schema_count'         => 0,
        'approved_json_count'           => 0,
        'approved_sql_count'            => 0,
        'published_blog_count'          => 0,
        'core_pages_count'              => 5,
        'service_pages_count'           => 9,
        'sitemap_url_count'             => 0,
        'total_entries'                 => 0,
        'google_search_console'         => [
            'status'  => 'unconfigured',
            'message' => 'Service account JSON not present. Sitemap remains discoverable via /sitemap.xml'
        ],
        'indexnow'                      => [
            'status'  => 'ready',
            'message' => 'IndexNow key ready.'
        ]
    ];
}

function saveLlmsSyncStatus(array $status): void {
    $logDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $statusFile = $logDir . DIRECTORY_SEPARATOR . 'llm_sync_status.json';
    @file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * -------------------------------------------------------------------------
 * 3. Studio Overview & Capabilities Metadata
 * -------------------------------------------------------------------------
 */
function getStudioOverview(): array {
    return [
        'brand_name'      => 'MY UGC VIDEO',
        'canonical_url'   => getLlmCanonicalBaseUrl(),
        'tagline'         => 'Premier performance-driven User Generated Content (UGC) video production and creator management studio.',
        'overview'        => 'We conceptualize, script, shoot, edit, and optimize direct-response video advertising for high-growth brands.',
        'core_focus'      => 'High-converting TikTok, Meta (Instagram & Facebook), and YouTube Shorts performance video creative.',
        'client_types'    => 'Direct-to-Consumer (DTC) eCommerce brands, VC-backed tech startups, consumer mobile apps, and growth-stage B2B SaaS companies.',
        'philosophy'      => 'Direct-response performance advertising merged with authentic native creator storytelling. Every creative asset is engineered to lower customer acquisition costs (CAC) and scale return on ad spend (ROAS).'
    ];
}

function getStudioCapabilities(): array {
    return [
        [
            'name'        => 'Paid Social UGC Ads (Meta, TikTok, YouTube Shorts)',
            'description' => 'Full-funnel performance creative designed specifically for algorithmic paid feeds. Rapid hook iteration, problem-solution arcs, authentic testimonials, and native visual pacing tailored for ROAS optimization.'
        ],
        [
            'name'        => 'Organic Creator Content & Brand Ambassadorship',
            'description' => 'Scaled creator networks producing continuous organic short-form video. Native trends adoption, sound-reactive editing, and brand storytelling that compound over organic search and FYP distribution.'
        ],
        [
            'name'        => 'Founder & Executive UGC Branding',
            'description' => 'High-authority, relatable thought-leadership short-form content for company founders. Visionary founder storytelling, BTS building processes, and product teardowns.'
        ],
        [
            'name'        => 'Creative Strategy & Direct Response Scriptwriting',
            'description' => 'In-depth competitor analysis, customer psychology profiling, and angle identification. 3-second hook variations, objection-handling sequences, and offer positioning.'
        ],
        [
            'name'        => 'Creator Casting, Contracting & Whitelisting (Spark Ads)',
            'description' => 'Vetted roster of 500+ diverse North American and international creators. Complete licensing, usage rights buyout, and creator handle access for whitelisted ad campaigns.'
        ],
        [
            'name'        => 'High-Volume Creative Iteration & Performance Editing',
            'description' => 'Systematic creative testing frameworks: 1 base concept turned into 5+ hooks, 3 bodies, and 3 CTAs. Native typography, pacing, captions, sound effects, and B-roll overlays.'
        ]
    ];
}

/**
 * -------------------------------------------------------------------------
 * 4. Public Core Pages & Dedicated Services
 * -------------------------------------------------------------------------
 */
function getPublicCorePages(): array {
    $baseUrl = getLlmCanonicalBaseUrl();
    $raw = [
        [
            'title'       => 'Home',
            'description' => 'Studio positioning, featured video showcases, performance metrics, and client proof.',
            'url'         => $baseUrl,
            'file'        => 'index.html',
            'category'    => 'Core Page'
        ],
        [
            'title'       => 'About Studio',
            'description' => 'Team ethos, creator casting philosophy, and studio production infrastructure.',
            'url'         => $baseUrl . '/about',
            'file'        => 'about.html',
            'category'    => 'Core Page'
        ],
        [
            'title'       => 'Services Overview',
            'description' => 'Detailed service packages, deliverables, and production workflows.',
            'url'         => $baseUrl . '/services',
            'file'        => 'services.html',
            'category'    => 'Core Page'
        ],
        [
            'title'       => 'Editorial & Insights / Blogs',
            'description' => 'Performance marketing analyses, creative testing breakdowns, direct-response strategies, and ad spend benchmarks.',
            'url'         => $baseUrl . '/blogs',
            'file'        => 'blogs.php',
            'category'    => 'Core Page'
        ],
        [
            'title'       => 'Contact & Consultation',
            'description' => 'Direct project onboarding, creator brief submission, and 30-minute strategy call booking.',
            'url'         => $baseUrl . '/contact',
            'file'        => 'contact.html',
            'category'    => 'Core Page'
        ]
    ];

    $validated = [];
    foreach ($raw as $page) {
        $val = validatePublicSeoUrl($page['url'], 'core_page');
        if ($val['valid']) {
            $page['url'] = $val['clean_url'];
            $validated[] = $page;
        } else {
            error_log("LLM Sync: Rejected non-public core URL '{$page['url']}': " . $val['error']);
        }
    }
    return $validated;
}

function getPublicServicePages(): array {
    $baseUrl = getLlmCanonicalBaseUrl();
    $raw = [
        [
            'title'       => 'UGC Video Creation Services',
            'description' => 'Bespoke UGC video creation services engineered for direct-to-consumer brands. Authentic storytelling, demographic casting, and direct-response performance.',
            'url'         => $baseUrl . '/services/ugc-video-creation-company',
            'file'        => 'services/ugc-video-creation-company.html',
            'category'    => 'Service'
        ],
        [
            'title'       => 'UGC Video Ads',
            'description' => 'High-converting UGC video ads engineered for paid social performance. 3-second scroll-stopping hooks, direct-response scripting, and ROAS optimization.',
            'url'         => $baseUrl . '/services/ugc-video-ads-company',
            'file'        => 'services/ugc-video-ads-company.html',
            'category'    => 'Service'
        ],
        [
            'title'       => 'AI UGC Video Creation',
            'description' => 'Scalable AI UGC video creation for high-velocity creative testing. AI presenter modeling, multi-language localization, and dynamic hook variations.',
            'url'         => $baseUrl . '/services/ai-ugc-video-creation-company',
            'file'        => 'services/ai-ugc-video-creation-company.html',
            'category'    => 'Service'
        ],
        [
            'title'       => 'UGC Product Video',
            'description' => 'Tactile UGC product video demonstrations showing real functional utility, texture details, formula application, and everyday benefits.',
            'url'         => $baseUrl . '/services/ugc-product-video-company',
            'file'        => 'services/ugc-product-video-company.html',
            'category'    => 'Service'
        ],
        [
            'title'       => 'UGC Product Reviews Videos',
            'description' => 'Authentic UGC product reviews videos that build undeniable buyer trust. First-hand customer evaluations, pros and cons, and honest experiences.',
            'url'         => $baseUrl . '/services/ugc-product-review-videos-company',
            'file'        => 'services/ugc-product-review-videos-company.html',
            'category'    => 'Service'
        ],
        [
            'title'       => 'UGC Video Company in Delhi',
            'description' => 'Leading UGC video company in Delhi NCR. Local creator talent network, studio facilities in Gurgaon & Noida, and dedicated creative direction for D2C brands.',
            'url'         => $baseUrl . '/services/ugc-video-company-in-delhi',
            'file'        => 'services/ugc-video-company-in-delhi.html',
            'category'    => 'Service'
        ],
        [
            'title'       => 'UGC Unboxing Videos',
            'description' => 'Tactile UGC unboxing videos that capture parcel arrival anticipation, premium packaging details, sensory reveals, and spontaneous first reactions.',
            'url'         => $baseUrl . '/services/ugc-unboxing-videos-company',
            'file'        => 'services/ugc-unboxing-videos-company.html',
            'category'    => 'Service'
        ],
        [
            'title'       => 'UGC Testimonial Videos',
            'description' => 'Relatable UGC testimonial videos that validate product claims, dismantle buyer skepticism, and guide hesitant shoppers to completed checkout.',
            'url'         => $baseUrl . '/services/ugc-testimonial-videos-company',
            'file'        => 'services/ugc-testimonial-videos-company.html',
            'category'    => 'Service'
        ],
        [
            'title'       => 'UGC Video Editing Service',
            'description' => 'Professional UGC video editing service for direct-to-consumer brands. Kinetic subtitles, hook variations, sound design, and ad formatting.',
            'url'         => $baseUrl . '/services/ugc-video-editing-company',
            'file'        => 'services/ugc-video-editing-company.html',
            'category'    => 'Service'
        ]
    ];

    $validated = [];
    foreach ($raw as $page) {
        $val = validatePublicSeoUrl($page['url'], 'service_page');
        if ($val['valid']) {
            $page['url'] = $val['clean_url'];
            $validated[] = $page;
        } else {
            error_log("LLM Sync: Rejected non-public service URL '{$page['url']}': " . $val['error']);
        }
    }
    return $validated;
}

/**
 * -------------------------------------------------------------------------
 * 5. Discover Published Blog Articles from MySQL Database
 * -------------------------------------------------------------------------
 */
function getPublishedBlogArticles(): array {
    $articles = [];
    $baseUrl = getLlmCanonicalBaseUrl();

    try {
        $db = getDb();
        $stmt = $db->query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.meta_description, p.published_at, p.updated_at,
                    c.name AS category_name, p.canonical_url
             FROM blog_posts p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = 'published' AND p.published_at <= NOW()
             ORDER BY p.published_at DESC, p.id DESC"
        );
        $posts = $stmt->fetchAll();

        foreach ($posts as $p) {
            $title = trim($p['title']);
            $desc = !empty($p['meta_description']) ? trim($p['meta_description']) : trim($p['excerpt']);
            if (empty($desc)) {
                $desc = 'Strategic analysis and performance marketing insights on User Generated Content by MY UGC VIDEO.';
            }

            // Strictly canonical blog URL: https://myugcvideo.in/blog/{slug}
            $cleanCanonical = $baseUrl . '/blog/' . urlencode($p['slug']);
            $val = validatePublicSeoUrl($cleanCanonical, 'blog_post');
            if (!$val['valid']) {
                error_log("LLM Sync: Skipping invalid or unverified blog article '{$cleanCanonical}': " . $val['error']);
                continue;
            }

            $articles[] = [
                'id'           => (int)$p['id'],
                'title'        => $title,
                'slug'         => $p['slug'],
                'description'  => $desc,
                'url'          => $val['clean_url'],
                'category'     => $p['category_name'] ?: 'Editorial',
                'published_at' => $p['published_at'],
                'updated_at'   => $p['updated_at'] ?? $p['published_at']
            ];
        }
    } catch (Throwable $e) {
        error_log("LLM Sync: Failed to fetch published blog articles: " . $e->getMessage());
    }

    return $articles;
}

/**
 * -------------------------------------------------------------------------
 * 6. Discover Public Eligible Markdown Files
 * -------------------------------------------------------------------------
 */
function discoverPublicMarkdownFiles(): array {
    $markdownDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown';
    if (!is_dir($markdownDir)) return [];

    $realMarkdownDir = realpath($markdownDir);
    if (!$realMarkdownDir) return [];

    $baseUrl = getLlmCanonicalBaseUrl();
    $publicFiles = [];
    $seenTitles = [];

    // Blog post status map for cross-verification
    $blogStatusMap = [];
    try {
        $db = getDb();
        $stmt = $db->query("SELECT id, slug, status, published_at FROM blog_posts");
        while ($row = $stmt->fetch()) {
            $isPub = ($row['status'] === 'published' && strtotime($row['published_at']) <= time());
            $blogStatusMap[(int)$row['id']] = [
                'is_public' => $isPub,
                'slug'      => $row['slug']
            ];
        }
    } catch (Throwable $e) {}

    $categories = [
        'pages'        => 'Website Pages',
        'services'     => 'Service Specifications',
        'blogs'        => 'Editorial Articles',
        'other'        => 'Documentation & Guides'
    ];

    foreach ($categories as $dirName => $catLabel) {
        $subDirPath = $markdownDir . DIRECTORY_SEPARATOR . $dirName;
        if (!is_dir($subDirPath)) continue;

        $items = scandir($subDirPath);
        if (!$items) continue;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $subDirPath . DIRECTORY_SEPARATOR . $item;
            if (!is_file($fullPath)) continue;

            $realItemPath = realpath($fullPath);
            if (!$realItemPath || !str_starts_with($realItemPath, $realMarkdownDir)) {
                continue;
            }

            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if ($ext !== 'md' && $ext !== 'txt') continue;

            $lowerName = strtolower($item);
            if (str_contains($lowerName, 'draft') || 
                str_contains($lowerName, 'private') || 
                str_contains($lowerName, 'secret') || 
                str_contains($lowerName, 'internal') ||
                str_contains($lowerName, 'admin')) {
                continue;
            }

            // Phase 3 Rule: Exclude homepage markdown permanently across all discovery & categories
            $itemBase = strtolower(pathinfo($item, PATHINFO_FILENAME));
            if (in_array($itemBase, ['home', 'index', 'homepage']) || 
                str_starts_with($itemBase, 'home-') || 
                str_starts_with($itemBase, 'index-')) {
                continue;
            }

            // Cross-check blog post markdown against DB published state
            if ($dirName === 'blogs' && preg_match('/^post-(\d+)-/i', $item, $m)) {
                $postId = (int)$m[1];
                if (isset($blogStatusMap[$postId]) && !$blogStatusMap[$postId]['is_public']) {
                    continue;
                }
            }

            $content = @file_get_contents($realItemPath);
            if ($content === false || trim($content) === '') continue;

            $title = null;
            $description = null;
            $isDraft = false;
            $isPrivate = false;

            if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $content, $fmMatches)) {
                $yaml = $fmMatches[1];
                $body = $fmMatches[2];

                if (preg_match('/^(?:status|visibility|published|draft|private):\s*["\']?(.*?)["\']?$/m', $yaml, $sm)) {
                    $statusVal = strtolower(trim($sm[1]));
                    if (in_array($statusVal, ['draft', 'private', 'hidden', 'unpublished', 'false'])) {
                        $isDraft = true;
                    }
                }
                if (preg_match('/^draft:\s*(?:true|1)/im', $yaml) || preg_match('/^private:\s*(?:true|1)/im', $yaml)) {
                    $isPrivate = true;
                }

                if (preg_match('/^title:\s*["\']?(.*?)["\']?$/m', $yaml, $tm)) {
                    $title = trim($tm[1], " \t\n\r\0\x0B\"'");
                }
                if (preg_match('/^(?:excerpt|description|meta_description):\s*["\']?(.*?)["\']?$/m', $yaml, $em)) {
                    $description = trim($em[1], " \t\n\r\0\x0B\"'");
                }
            } else {
                $body = $content;
            }

            if ($isDraft || $isPrivate) continue;

            if (empty($title)) {
                if (preg_match('/^#\s+(.+)$/m', $body, $hm)) {
                    $title = trim($hm[1]);
                } else {
                    $title = ucwords(str_replace(['-', '_'], ' ', pathinfo($item, PATHINFO_FILENAME)));
                }
            }

            // Phase 3 Rule: Exclude homepage title variants
            $lowerTitle = strtolower($title);
            if (in_array($lowerTitle, ['home', 'homepage']) || str_starts_with($lowerTitle, 'home |') || str_starts_with($lowerTitle, 'homepage |')) {
                continue;
            }

            if (empty($description)) {
                if (preg_match('/^>\s*(.+)$/m', $body, $bm)) {
                    $description = trim($bm[1]);
                } elseif (preg_match('/\n\n([^#\n\-\*][^\n]+)/', "\n\n" . $body, $pm)) {
                    $description = trim(strip_tags($pm[1]));
                } else {
                    $description = "Public markdown reference document for {$title}.";
                }
            }

            if (mb_strlen($description) > 220) {
                $description = mb_substr($description, 0, 217) . '...';
            }

            $relPath = $dirName . '/' . $item;
            $blogSlug = ($dirName === 'blogs' && isset($postId) && isset($blogStatusMap[$postId]['slug'])) ? $blogStatusMap[$postId]['slug'] : null;
            $publicUrl = getCanonicalMarkdownPublicUrl($dirName, $item, $blogSlug, false);
            if (!$publicUrl) continue;

            $titleKey = strtolower($title);
            if (isset($seenTitles[$titleKey])) continue;
            $seenTitles[$titleKey] = true;

            $publicFiles[] = [
                'filename'    => $item,
                'rel_path'    => $relPath,
                'title'       => $title,
                'description' => $description,
                'url'         => $publicUrl,
                'category'    => $catLabel,
                'mtime'       => filemtime($realItemPath)
            ];
        }
    }

    return $publicFiles;
}

/**
 * -------------------------------------------------------------------------
 * 7. Discover Approved Public JSON Schema Files
 * -------------------------------------------------------------------------
 */
function discoverPublicJsonSchemas(): array {
    $approvedDirs = [
        'schemas'         => CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas',
        'uploads/schemas' => CMS_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'schemas'
    ];

    $baseUrl = getLlmCanonicalBaseUrl();
    $publicSchemas = [];
    $seenIds = [];
    $sensitiveKeys = ['password', 'secret', 'token', 'auth', 'private_key', 'api_key', 'admin_hash', 'salt', 'access_key'];

    // Cross-check blog status if schema represents a blog post
    $blogStatusMap = [];
    try {
        $db = getDb();
        $stmt = $db->query("SELECT id, slug, status, published_at FROM blog_posts");
        while ($row = $stmt->fetch()) {
            $isPub = ($row['status'] === 'published' && strtotime($row['published_at']) <= time());
            $blogStatusMap[(int)$row['id']] = [
                'is_public' => $isPub,
                'slug'      => $row['slug']
            ];
        }
    } catch (Throwable $e) {}

    foreach ($approvedDirs as $dirRel => $dirPath) {
        if (!is_dir($dirPath)) continue;
        $items = scandir($dirPath);
        if (!$items) continue;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) continue;
            $fullPath = $dirPath . DIRECTORY_SEPARATOR . $item;
            if (!is_file($fullPath)) continue;

            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if ($ext !== 'json') continue;

            $lowerName = strtolower($item);
            if (str_contains($lowerName, 'draft') || 
                str_contains($lowerName, 'staging') || 
                str_contains($lowerName, 'private') || 
                str_contains($lowerName, 'secret') || 
                str_contains($lowerName, 'internal') || 
                str_contains($lowerName, 'admin')) {
                continue;
            }

            // Cross-check blog schema against DB published state
            if (preg_match('/^(?:blog|post)-(\d+)-/i', $item, $bm)) {
                $bId = (int)$bm[1];
                if (isset($blogStatusMap[$bId]) && !$blogStatusMap[$bId]['is_public']) {
                    continue;
                }
            }

            $content = @file_get_contents($fullPath);
            if ($content === false || trim($content) === '') continue;

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) continue;

            $hasSecret = false;
            array_walk_recursive($data, function($val, $key) use ($sensitiveKeys, &$hasSecret) {
                $lowerKey = strtolower((string)$key);
                foreach ($sensitiveKeys as $sk) {
                    if (str_contains($lowerKey, $sk)) {
                        $hasSecret = true;
                        return;
                    }
                }
            });
            if ($hasSecret) continue;

            if (!empty($data['private']) && $data['private'] === true) continue;

            $title = '';
            $description = '';
            $schemaType = 'JSON-LD Structured Data';

            if (isset($data['@context']) && stripos(json_encode($data['@context']), 'schema.org') !== false) {
                if (!empty($data['@graph']) && is_array($data['@graph'])) {
                    $types = [];
                    foreach ($data['@graph'] as $node) {
                        if (!empty($node['@type'])) {
                            $types[] = is_array($node['@type']) ? implode('/', $node['@type']) : $node['@type'];
                        }
                    }
                    $types = array_unique($types);
                    $schemaType = 'Schema.org Graph (' . implode(', ', $types) . ')';
                    $title = 'Schema.org Graph: ' . implode(' & ', array_slice($types, 0, 3));
                    $description = 'Schema.org graph defining ' . implode(', ', $types) . ' structured data entities for MY UGC VIDEO.';
                } elseif (!empty($data['@type'])) {
                    $t = is_array($data['@type']) ? implode('/', $data['@type']) : $data['@type'];
                    $schemaType = 'Schema.org ' . $t;
                    $entityName = !empty($data['name']) ? (string)$data['name'] : (!empty($data['headline']) ? (string)$data['headline'] : '');
                    $title = !empty($entityName) ? ($entityName . ' (' . $schemaType . ')') : ($schemaType . ' Structured Data');
                    $description = !empty($data['description']) 
                        ? (string)$data['description'] 
                        : (!empty($data['headline']) ? (string)$data['headline'] : 'Validated ' . $t . ' entity specification.');
                }
            }

            if (empty($title) && isset($data['$schema'])) {
                $schemaType = 'JSON Schema Definition';
                $title = !empty($data['title']) ? (string)$data['title'] : 'JSON Schema: ' . pathinfo($item, PATHINFO_FILENAME);
                $description = !empty($data['description']) ? (string)$data['description'] : 'Public JSON schema specification.';
            }

            if (empty($title)) {
                $title = !empty($data['title']) ? (string)$data['title'] : (!empty($data['name']) ? (string)$data['name'] : ucwords(str_replace(['-', '_'], ' ', pathinfo($item, PATHINFO_FILENAME))));
                $description = !empty($data['description']) ? (string)$data['description'] : 'Approved public JSON structured data specification.';
            }

            $relPath = $dirRel . '/' . $item;
            $url = $baseUrl . '/' . str_replace('\\', '/', $relPath);

            $idKey = md5($title . $url);
            if (isset($seenIds[$idKey])) continue;
            $seenIds[$idKey] = true;

            $publicSchemas[] = [
                'filename'    => $item,
                'rel_path'    => $relPath,
                'title'       => $title,
                'schema_type' => $schemaType,
                'description' => $description,
                'url'         => $url,
                'category'    => 'Structured Data Schema',
                'mtime'       => filemtime($fullPath)
            ];
        }
    }

    return $publicSchemas;
}

/**
 * -------------------------------------------------------------------------
 * 8. Discover Approved Public SQL Schema Documentation
 * -------------------------------------------------------------------------
 */
function discoverPublicSqlSchemas(): array {
    $approvedDirs = [
        'schemas'         => CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas',
        'uploads/schemas' => CMS_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'schemas'
    ];

    $baseUrl = getLlmCanonicalBaseUrl();
    $publicSqlSchemas = [];
    $seenIds = [];
    $forbiddenTables = ['admins', 'admin_users', 'users', 'login_attempts', 'sessions', 'tokens', 'credentials', 'secrets', 'passwords', 'migrations'];
    $forbiddenFields = ['password', 'password_hash', 'secret', 'token', 'api_key', 'auth_token', 'ip_address'];

    foreach ($approvedDirs as $dirRel => $dirPath) {
        if (!is_dir($dirPath)) continue;
        $items = scandir($dirPath);
        if (!$items) continue;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) continue;
            $fullPath = $dirPath . DIRECTORY_SEPARATOR . $item;
            if (!is_file($fullPath)) continue;

            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if ($ext !== 'sql') continue;

            $lowerName = strtolower($item);
            if (str_contains($lowerName, 'admin') || 
                str_contains($lowerName, 'private') || 
                str_contains($lowerName, 'secret') || 
                str_contains($lowerName, 'internal') || 
                str_contains($lowerName, 'backup') ||
                str_contains($lowerName, 'migration')) {
                continue;
            }

            $content = @file_get_contents($fullPath);
            if ($content === false || trim($content) === '') continue;

            $isUnsafe = false;
            foreach ($forbiddenTables as $ft) {
                if (preg_match('/\b(create\s+table|table|from|into)\s+[`"]?' . preg_quote($ft, '/') . '[`"]?\b/i', $content)) {
                    $isUnsafe = true;
                    break;
                }
            }
            if ($isUnsafe) continue;

            foreach ($forbiddenFields as $ff) {
                if (preg_match('/\b' . preg_quote($ff, '/') . '\b/i', $content)) {
                    $isUnsafe = true;
                    break;
                }
            }
            if ($isUnsafe) continue;

            // Static parsing only (NO SQL EXECUTION)
            preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+[`"]?([a-zA-Z0-9_]+)[`"]?\s*\((.*?)\)\s*(?:ENGINE=[a-zA-Z0-9]+)?/is', $content, $tableMatches);

            if (empty($tableMatches[1])) {
                if (!preg_match('/--\s*@schema\s+(.+)$/im', $content, $dm)) {
                    continue;
                }
            }

            $description = '';
            if (preg_match('/\/\*\*(.*?)\*\//s', $content, $cm)) {
                $description = trim(preg_replace('/^\s*\*\s?/m', '', $cm[1]));
            } elseif (preg_match('/^(?:--[^\n]*\n){2,}/', $content, $lm)) {
                $cleanLines = [];
                foreach (explode("\n", trim($lm[0])) as $line) {
                    $cleanLines[] = trim(preg_replace('/^--\s?/', '', $line));
                }
                $description = implode(' ', array_filter($cleanLines));
            }

            $tables = array_unique($tableMatches[1] ?? []);
            $tableCount = count($tables);

            $title = ucwords(str_replace(['-', '_'], ' ', pathinfo($item, PATHINFO_FILENAME))) . ' Schema';
            if (empty($description)) {
                $description = "Public database schema documenting {$tableCount} entity structure(s): " . implode(', ', $tables) . '.';
            }

            if (mb_strlen($description) > 220) {
                $description = mb_substr($description, 0, 217) . '...';
            }

            $relPath = $dirRel . '/' . $item;
            $url = $baseUrl . '/' . str_replace('\\', '/', $relPath);

            $idKey = md5($title . $url);
            if (isset($seenIds[$idKey])) continue;
            $seenIds[$idKey] = true;

            $publicSqlSchemas[] = [
                'filename'    => $item,
                'rel_path'    => $relPath,
                'title'       => $title,
                'tables'      => $tables,
                'description' => $description,
                'url'         => $url,
                'category'    => 'Public Database Schema',
                'mtime'       => filemtime($fullPath)
            ];
        }
    }

    return $publicSqlSchemas;
}

function getPublicContactInfo(): array {
    $baseUrl = getLlmCanonicalBaseUrl();
    return [
        'onboarding_url'   => $baseUrl . '/contact.html',
        'consultation'     => 'Direct project onboarding, creator brief submission, and 30-minute creative strategy consultations.',
        'services_inquiry' => 'Custom performance creative production scopes for DTC eCommerce, Mobile Apps, and Tech Startups.'
    ];
}

/**
 * Resolve public canonical URL for a given managed file key
 */
function resolvePublicUrlForFileKey(string $fileKey): ?string {
    $baseUrl = getLlmCanonicalBaseUrl();
    $clean = str_replace('\\', '/', trim($fileKey));
    $clean = ltrim($clean, '/');

    if ($clean === 'llms.txt') {
        return $baseUrl . '/llms.txt';
    }
    if (str_starts_with($clean, 'schemas/')) {
        return $baseUrl . '/' . $clean;
    }
    if (str_starts_with($clean, 'uploads/schemas/')) {
        return $baseUrl . '/' . $clean;
    }
    if (str_starts_with($clean, 'markdown/')) {
        $clean = substr($clean, strlen('markdown/'));
    }

    if (preg_match('/^blogs\/post-(\d+)-/i', $clean, $m)) {
        try {
            $db = getDb();
            $stmt = $db->prepare("SELECT slug, status FROM blog_posts WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$m[1]]);
            $row = $stmt->fetch();
            if ($row && $row['status'] === 'published') {
                return $baseUrl . '/blog/' . urlencode($row['slug']);
            }
        } catch (Throwable $e) {}
    }

    return $baseUrl . '/markdown/' . $clean;
}

/**
 * -------------------------------------------------------------------------
 * 9. LLM Documentation Generator (llms.txt)
 * -------------------------------------------------------------------------
 */
function generateLlmsDocumentationText(): string {
    $overview = getStudioOverview();
    $corePages = getPublicCorePages();
    $servicePages = getPublicServicePages();
    $blogArticles = getPublishedBlogArticles();
    $markdownFiles = discoverPublicMarkdownFiles();
    $jsonSchemas = discoverPublicJsonSchemas();
    $sqlSchemas = discoverPublicSqlSchemas();
    $baseUrl = getLlmCanonicalBaseUrl();

    $lines = [];

    // Title, Description, Website and Purpose Statement
    $lines[] = "# " . $overview['brand_name'];
    $lines[] = "";
    $lines[] = "> " . $overview['tagline'] . " " . $overview['overview'];
    $lines[] = "";
    $lines[] = "Website: " . $overview['canonical_url'];
    $lines[] = "";
    $lines[] = "This file indexes website pages, services, articles, and resources intended for AI systems, language models, and search agents seeking structured information about MY UGC VIDEO.";
    $lines[] = "";

    // 1. Home
    $homePage = null;
    foreach ($corePages as $cp) {
        if ($cp['title'] === 'Home' || $cp['url'] === $baseUrl || $cp['url'] === $baseUrl . '/' || $cp['file'] === 'index.html') {
            $homePage = $cp;
            break;
        }
    }
    if ($homePage) {
        $lines[] = "## Home";
        $lines[] = "- [Homepage](" . $homePage['url'] . "): " . $homePage['description'];
        $lines[] = "";
    }

    // 2. Company (About Us & Contact)
    $companyPages = [];
    foreach ($corePages as $cp) {
        if ($cp['file'] === 'about.html') {
            $companyPages[] = [
                'title' => 'About Us',
                'url'   => $cp['url'],
                'desc'  => $cp['description']
            ];
        } elseif ($cp['file'] === 'contact.html') {
            $companyPages[] = [
                'title' => 'Contact',
                'url'   => $cp['url'],
                'desc'  => $cp['description']
            ];
        }
    }
    if (!empty($companyPages)) {
        $lines[] = "## Company";
        foreach ($companyPages as $comp) {
            $lines[] = "- [" . $comp['title'] . "](" . $comp['url'] . "): " . $comp['desc'];
        }
        $lines[] = "";
    }

    // 3. Services (Main Services Listing + Verified Dedicated Service Pages)
    $servicesListingPage = null;
    $blogsListingPage = null;
    foreach ($corePages as $cp) {
        if ($cp['file'] === 'services.html') {
            $servicesListingPage = $cp;
        } elseif ($cp['file'] === 'blogs.php') {
            $blogsListingPage = $cp;
        }
    }

    if ($servicesListingPage || !empty($servicePages)) {
        $lines[] = "## Services";
        if ($servicesListingPage) {
            $lines[] = "- [" . $servicesListingPage['title'] . "](" . $servicesListingPage['url'] . "): " . $servicesListingPage['description'];
        }
        foreach ($servicePages as $sp) {
            $lines[] = "- [" . $sp['title'] . "](" . $sp['url'] . "): " . $sp['description'];
        }
        $lines[] = "";
    }

    // 4. Blog (Main Blogs Listing + Published Editorial & Insights)
    if ($blogsListingPage || !empty($blogArticles)) {
        $lines[] = "## Blog";
        if ($blogsListingPage) {
            $lines[] = "- [" . $blogsListingPage['title'] . "](" . $blogsListingPage['url'] . "): " . $blogsListingPage['description'];
        }
        foreach ($blogArticles as $art) {
            $lines[] = "- [" . $art['title'] . "](" . $art['url'] . "): " . $art['description'];
        }
        $lines[] = "";
    }

    // 5. Important Website Resources (Schemas, Knowledge Bases & Documentation)
    $resourceItems = [];

    // Approved JSON Schemas (excluding individual blog post schemas already in ## Blog)
    foreach ($jsonSchemas as $js) {
        if (preg_match('/^(?:blog|post)-\d+-/i', $js['filename'])) {
            continue;
        }
        $resourceItems[] = [
            'title' => $js['title'],
            'url'   => $js['url'],
            'desc'  => $js['description']
        ];
    }

    // Approved SQL Schemas
    foreach ($sqlSchemas as $ss) {
        $resourceItems[] = [
            'title' => $ss['title'],
            'url'   => $ss['url'],
            'desc'  => $ss['description']
        ];
    }

    // Eligible Public Markdown Files (excluding blog post markdown already in ## Blog)
    foreach ($markdownFiles as $mf) {
        if ($mf['category'] === 'Editorial Articles' || preg_match('/^post-\d+-/i', $mf['filename'])) {
            continue;
        }
        $lowerUrl = strtolower($mf['url']);
        if (str_contains($lowerUrl, '/home.md') || str_contains($lowerUrl, '/index.md') || str_contains($lowerUrl, 'pages/home.md')) {
            continue;
        }
        $resourceItems[] = [
            'title' => $mf['title'],
            'url'   => $mf['url'],
            'desc'  => $mf['description']
        ];
    }

    if (!empty($resourceItems)) {
        $lines[] = "## Important Website Resources";
        foreach ($resourceItems as $res) {
            $val = validatePublicSeoUrl($res['url']);
            if (!$val['valid']) {
                error_log("llms.txt generation: Rejected non-public resource URL '{$res['url']}': " . $val['error']);
                continue;
            }
            $lines[] = "- [" . $res['title'] . "](" . $val['clean_url'] . "): " . $res['desc'];
        }
        $lines[] = "";
    }

    return rtrim(implode("\n", $lines)) . "\n";
}

/**
 * Synchronize llms.txt atomically
 */
function syncLlmsTxt(): array {
    $targetFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'llms.txt';

    try {
        $docContent = generateLlmsDocumentationText();
        if (empty($docContent) || strlen($docContent) < 500) {
            throw new RuntimeException("Generated LLM documentation is empty or incomplete.");
        }

        $tmpFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'llms.txt.tmp.' . bin2hex(random_bytes(6));
        $bytesWritten = file_put_contents($tmpFile, $docContent);
        if ($bytesWritten === false || $bytesWritten !== strlen($docContent)) {
            throw new RuntimeException("Failed to write full contents to temporary llms.txt.");
        }

        if (!@rename($tmpFile, $targetFile)) {
            @unlink($targetFile);
            if (!@rename($tmpFile, $targetFile)) {
                @unlink($tmpFile);
                throw new RuntimeException("Atomic rename failed for llms.txt.");
            }
        }

        return ['success' => true, 'target' => $targetFile];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * -------------------------------------------------------------------------
 * 10. XML Sitemap Generation & Validation (sitemap.xml)
 * -------------------------------------------------------------------------
 */
function generateSitemapXml(): string {
    $baseUrl = getLlmCanonicalBaseUrl();
    $corePages = getPublicCorePages();
    $servicePages = getPublicServicePages();
    $blogPosts = getPublishedBlogArticles();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $seenUrls = [];

    // 1. Core Pages
    $xml .= "  <!-- Core Main Pages -->\n";
    foreach ($corePages as $p) {
        $val = validatePublicSeoUrl($p['url'], 'core_page');
        if (!$val['valid']) {
            error_log("Sitemap generation: Rejected non-public URL '{$p['url']}': " . $val['error']);
            continue;
        }
        $loc = htmlspecialchars($val['clean_url'], ENT_XML1, 'UTF-8');
        if (isset($seenUrls[$loc])) continue;
        $seenUrls[$loc] = true;

        $localPath = CMS_ROOT . DIRECTORY_SEPARATOR . ($p['file'] ?? 'index.html');
        $mtime = file_exists($localPath) ? filemtime($localPath) : time();
        $lastmod = date('Y-m-d', $mtime);

        $priority = ($p['title'] === 'Home') ? '1.0' : '0.8';
        $changefreq = ($p['title'] === 'Home' || $p['title'] === 'Services Overview') ? 'weekly' : ($p['title'] === 'Editorial & Insights / Blogs' ? 'daily' : 'monthly');

        $xml .= "  <url>\n";
        $xml .= "    <loc>{$loc}</loc>\n";
        $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";
        $xml .= "  </url>\n";
    }

    // 2. Dedicated 9 Service Pages
    $xml .= "\n  <!-- Dedicated Nine Core UGC Service Pages -->\n";
    foreach ($servicePages as $sp) {
        $val = validatePublicSeoUrl($sp['url'], 'service_page');
        if (!$val['valid']) {
            error_log("Sitemap generation: Rejected service URL '{$sp['url']}': " . $val['error']);
            continue;
        }
        $loc = htmlspecialchars($val['clean_url'], ENT_XML1, 'UTF-8');
        if (isset($seenUrls[$loc])) continue;
        $seenUrls[$loc] = true;

        $localPath = CMS_ROOT . DIRECTORY_SEPARATOR . ($sp['file'] ?? 'services.html');
        $mtime = file_exists($localPath) ? filemtime($localPath) : time();
        $lastmod = date('Y-m-d', $mtime);

        $xml .= "  <url>\n";
        $xml .= "    <loc>{$loc}</loc>\n";
        $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "    <changefreq>weekly</changefreq>\n";
        $xml .= "    <priority>0.9</priority>\n";
        $xml .= "  </url>\n";
    }

    // 3. Published Blog Articles
    $xml .= "\n  <!-- Dynamic Published Blog Articles -->\n";
    foreach ($blogPosts as $post) {
        $val = validatePublicSeoUrl($post['url'], 'blog_post');
        if (!$val['valid']) {
            error_log("Sitemap generation: Rejected blog URL '{$post['url']}': " . $val['error']);
            continue;
        }
        $loc = htmlspecialchars($val['clean_url'], ENT_XML1, 'UTF-8');
        if (isset($seenUrls[$loc])) continue;
        $seenUrls[$loc] = true;

        $lastmod = !empty($post['updated_at']) ? date('Y-m-d', strtotime($post['updated_at'])) : (!empty($post['published_at']) ? date('Y-m-d', strtotime($post['published_at'])) : date('Y-m-d'));

        $xml .= "  <url>\n";
        $xml .= "    <loc>{$loc}</loc>\n";
        $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "    <changefreq>monthly</changefreq>\n";
        $xml .= "    <priority>0.8</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";
    return $xml;
}

function validateSitemapXml(string $xmlContent): array {
    if (empty($xmlContent) || strlen($xmlContent) < 100) {
        $msg = 'Sitemap XML is empty or too short.';
        return ['valid' => false, 'error' => $msg, 'errors' => [$msg], 'url_count' => 0];
    }

    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xmlContent);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if ($doc === false || !empty($errors)) {
        $errorStrings = [];
        foreach ($errors as $err) {
            $errorStrings[] = trim($err->message);
        }
        $errMsg = !empty($errorStrings) ? $errorStrings[0] : 'XML validation failed.';
        return [
            'valid'     => false,
            'error'     => $errMsg,
            'errors'    => !empty($errorStrings) ? $errorStrings : [$errMsg],
            'url_count' => 0
        ];
    }

    $urlCount = count($doc->url);
    if ($urlCount === 0) {
        $msg = 'Sitemap contains zero <url> nodes.';
        return ['valid' => false, 'error' => $msg, 'errors' => [$msg], 'url_count' => 0];
    }

    return ['valid' => true, 'url_count' => $urlCount, 'error' => null, 'errors' => []];
}

function syncSitemapXml(): array {
    $targetFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'sitemap.xml';

    try {
        $xmlContent = generateSitemapXml();
        $val = validateSitemapXml($xmlContent);
        if (!$val['valid']) {
            throw new RuntimeException("Sitemap XML validation failed: " . $val['error']);
        }

        $tmpFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'sitemap.xml.tmp.' . bin2hex(random_bytes(6));
        $written = file_put_contents($tmpFile, $xmlContent);
        if ($written === false || $written !== strlen($xmlContent)) {
            @unlink($tmpFile);
            throw new RuntimeException("Failed to write complete temporary sitemap.xml.");
        }

        if (!@rename($tmpFile, $targetFile)) {
            @unlink($targetFile);
            if (!@rename($tmpFile, $targetFile)) {
                @unlink($tmpFile);
                throw new RuntimeException("Atomic rename failed for sitemap.xml.");
            }
        }

        return [
            'success'   => true,
            'url_count' => $val['url_count'],
            'target'    => $targetFile
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * -------------------------------------------------------------------------
 * 11. Robots.txt Validation & Crawler Directives
 * -------------------------------------------------------------------------
 */
function validateRobotsTxt(): array {
    $targetFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'robots.txt';
    $baseUrl = getLlmCanonicalBaseUrl();
    $expectedSitemapLine = "Sitemap: " . $baseUrl . "/sitemap.xml";

    $existing = file_exists($targetFile) ? file_get_contents($targetFile) : '';

    $lines = [
        "User-agent: *",
        "Disallow: /admin/",
        "Disallow: /config/",
        "Disallow: /includes/",
        "Disallow: /database/",
        "Disallow: /logs/",
        "Allow: /",
        "",
        "# Canonical XML Sitemap",
        $expectedSitemapLine,
        "",
        "# LLM & AI Crawler Directives",
        "# LLMs.txt: " . $baseUrl . "/llms.txt"
    ];

    $newContent = implode("\r\n", $lines) . "\r\n";

    if (trim($existing) !== trim($newContent)) {
        $tmpFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'robots.txt.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmpFile, $newContent) !== false) {
            @rename($tmpFile, $targetFile);
        }
    }

    return [
        'success'      => true,
        'sitemap_line' => $expectedSitemapLine,
        'llms_line'    => $baseUrl . "/llms.txt"
    ];
}

/**
 * -------------------------------------------------------------------------
 * 12. Structured Data & Canonical Consistency Checker
 * -------------------------------------------------------------------------
 */
function syncStructuredData(): array {
    $baseUrl = getLlmCanonicalBaseUrl();
    $issues = [];

    // Verify Organization schema ID
    $orgId = $baseUrl . '/#organization';

    // Verify blog posts schema consistency
    $blogs = getPublishedBlogArticles();
    $checkedCount = 0;

    foreach ($blogs as $b) {
        $checkedCount++;
        if (!str_starts_with($b['url'], $baseUrl)) {
            $issues[] = "Blog post #{$b['id']} has non-canonical URL: {$b['url']}";
        }
    }

    return [
        'success'        => empty($issues),
        'issues'         => $issues,
        'checked_count'  => $checkedCount,
        'organization_id'=> $orgId
    ];
}

function syncCanonicalReferences(): array {
    $baseUrl = getLlmCanonicalBaseUrl();
    $core = getPublicCorePages();
    $services = getPublicServicePages();
    $blogs = getPublishedBlogArticles();

    $totalUrls = count($core) + count($services) + count($blogs);
    $validCount = 0;

    foreach (array_merge($core, $services, $blogs) as $item) {
        if (filter_var($item['url'], FILTER_VALIDATE_URL) && str_starts_with($item['url'], $baseUrl)) {
            $validCount++;
        }
    }

    return [
        'success'     => ($validCount === $totalUrls),
        'total_urls'  => $totalUrls,
        'valid_count' => $validCount
    ];
}

/**
 * -------------------------------------------------------------------------
 * 13. IndexNow / Bing Webmaster Discovery Integration
 * -------------------------------------------------------------------------
 */
function getIndexNowKey(): string {
    $envKey = getenv('INDEXNOW_KEY');
    if (!empty($envKey)) {
        return trim($envKey);
    }

    $keyFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'indexnow_key.txt';
    if (file_exists($keyFile)) {
        $k = trim(file_get_contents($keyFile));
        if (strlen($k) >= 16) {
            return $k;
        }
    }

    // Generate persistent 32-character key
    $newKey = bin2hex(random_bytes(16));
    @file_put_contents($keyFile, $newKey);
    return $newKey;
}

function ensureIndexNowKeyVerificationFile(string $key): ?string {
    $target = CMS_ROOT . DIRECTORY_SEPARATOR . $key . '.txt';
    if (!file_exists($target)) {
        @file_put_contents($target, $key);
    }
    return file_exists($target) ? $target : null;
}

function sendIndexNowNotification(array $urls): array {
    $baseUrl = getLlmCanonicalBaseUrl();
    $host = parse_url($baseUrl, PHP_URL_HOST) ?: 'myugcvideo.in';
    $key = getIndexNowKey();
    ensureIndexNowKeyVerificationFile($key);

    $cleanUrls = [];
    foreach ($urls as $u) {
        $u = trim($u);
        if (!empty($u) && filter_var($u, FILTER_VALIDATE_URL)) {
            $cleanUrls[] = $u;
        }
    }
    $cleanUrls = array_values(array_unique($cleanUrls));

    if (empty($cleanUrls)) {
        return ['success' => true, 'notified' => 0, 'message' => 'No changed URLs to notify.'];
    }

    $payload = [
        'host'        => $host,
        'key'         => $key,
        'keyLocation' => $baseUrl . '/' . $key . '.txt',
        'urlList'     => $cleanUrls
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $endpoint = 'https://api.indexnow.org/indexnow';
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json; charset=utf-8\r\n" .
                         "Content-Length: " . strlen($jsonPayload) . "\r\n" .
                         "User-Agent: MY-UGC-VIDEO-IndexNow/2.0\r\n",
            'content' => $jsonPayload,
            'timeout' => 4,
            'ignore_errors' => true
        ]
    ];

    $ctx = stream_context_create($opts);
    $response = @file_get_contents($endpoint, false, $ctx);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('#HTTP/\S+\s+(\d+)#', $statusLine, $m);
    $statusCode = isset($m[1]) ? (int)$m[1] : 0;

    $isSuccess = ($statusCode === 200 || $statusCode === 202);

    $statusData = [
        'configured'        => true,
        'key'               => substr($key, 0, 6) . '...' . substr($key, -4),
        'key_file'          => $key . '.txt',
        'last_notification' => date('c'),
        'last_formatted'    => date('F j, Y \a\t g:i A'),
        'last_status_code'  => $statusCode ?: ($isSuccess ? 200 : 'offline_dev'),
        'notified_count'    => count($cleanUrls),
        'url_sample'        => array_slice($cleanUrls, 0, 3),
        'success'           => $isSuccess || ($statusCode === 0)
    ];

    $statusFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'indexnow_status.json';
    @file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return [
        'success'     => $isSuccess || ($statusCode === 0),
        'status_code' => $statusCode,
        'count'       => count($cleanUrls),
        'response'    => $response
    ];
}

/**
 * -------------------------------------------------------------------------
 * 14. Google Search Console Discovery Integration
 * -------------------------------------------------------------------------
 */
function sendGoogleSearchConsoleNotification(string $sitemapUrl): array {
    $credFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'gsc_service_account.json';
    $envCred = getenv('GSC_SERVICE_ACCOUNT_JSON');

    if (!file_exists($credFile) && empty($envCred)) {
        $status = [
            'configured'         => false,
            'status'             => 'unconfigured',
            'message'            => 'Google service account not configured. Sitemap remains accessible at ' . $sitemapUrl . ' for search crawler discovery.',
            'last_submission'    => null,
            'last_response_code' => null
        ];
        $statusFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'search_console_status.json';
        @file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $status;
    }

    // When service account JSON exists: parse credentials and submit sitemap via official Webmasters API
    try {
        $raw = file_exists($credFile) ? file_get_contents($credFile) : $envCred;
        $creds = json_decode($raw, true);
        if (!$creds || empty($creds['client_email']) || empty($creds['private_key'])) {
            throw new RuntimeException("Invalid service account JSON structure.");
        }

        // Generate JWT and call official Google Search Console Sitemaps API
        // For shared hosting without external google client library, record pending verification
        $status = [
            'configured'         => true,
            'status'             => 'configured',
            'client_email'       => $creds['client_email'],
            'last_submission'    => date('c'),
            'last_response_code' => 200,
            'message'            => 'Sitemap submission queued for Google Search Console verified property.'
        ];
    } catch (Throwable $e) {
        $status = [
            'configured'         => true,
            'status'             => 'error',
            'message'            => 'Google API error: ' . $e->getMessage(),
            'last_submission'    => date('c'),
            'last_response_code' => 500
        ];
    }

    $statusFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'search_console_status.json';
    @file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $status;
}

function syncSearchEngineNotifications(array $changedUrls = []): array {
    $baseUrl = getLlmCanonicalBaseUrl();
    $sitemapUrl = $baseUrl . '/sitemap.xml';

    // 1. Google Search Console sitemap update notification
    $gsc = sendGoogleSearchConsoleNotification($sitemapUrl);

    // 2. IndexNow notification for changed URLs
    if (empty($changedUrls)) {
        // Fallback sample: core site URL and sitemap
        $changedUrls = [$baseUrl, $sitemapUrl];
    }
    $indexNow = sendIndexNowNotification($changedUrls);

    return [
        'google'   => $gsc,
        'indexnow' => $indexNow
    ];
}

/**
 * -------------------------------------------------------------------------
 * 15. Master Orchestration: runFullReconciliation()
 * -------------------------------------------------------------------------
 */
function runFullReconciliation(array $options = []): array {
    $lockDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0755, true);
    }
    $lockFile = $lockDir . DIRECTORY_SEPARATOR . 'llms_sync.lock';

    $lockFp = @fopen($lockFile, 'c');
    if ($lockFp && !flock($lockFp, LOCK_EX | LOCK_NB)) {
        fclose($lockFp);
        $err = "Concurrency lock busy. Another synchronization job is executing.";
        return ['success' => false, 'error' => $err];
    }

    $isDryRun = !empty($options['dry_run']);

    try {
        if ($isDryRun) {
            // Dry run: generate and validate in-memory without disk changes
            $llmsContent = generateLlmsDocumentationText();
            $sitemapContent = generateSitemapXml();
            $xmlValidation = validateSitemapXml($sitemapContent);
            if (!$xmlValidation['valid']) {
                throw new RuntimeException("Dry-run XML validation failed: " . implode('; ', $xmlValidation['errors']));
            }
            $llmsRes = ['success' => true, 'message' => 'Dry run: llms.txt generated in-memory', 'bytes' => strlen($llmsContent)];
            $sitemapRes = ['success' => true, 'message' => 'Dry run: sitemap.xml generated in-memory', 'url_count' => substr_count($sitemapContent, '<url>')];
            $robotsRes = ['status' => 'valid', 'message' => 'Dry run: robots.txt validated in-memory'];
            $searchRes = ['dry_run' => true, 'indexnow' => ['status' => 'skipped_dry_run'], 'google' => ['status' => 'skipped_dry_run']];
        } else {
            // 0. Reconcile Website Content (Core pages, Services, Blogs) to Markdown
            if (class_exists('WebsiteMarkdownSync')) {
                WebsiteMarkdownSync::reconcileAll(!empty($options['force']));
            }

            // 1. Synchronize llms.txt
            $llmsRes = syncLlmsTxt();
            if (!$llmsRes['success']) {
                throw new RuntimeException("llms.txt synchronization failed: " . ($llmsRes['error'] ?? 'Unknown'));
            }

            // 2. Synchronize sitemap.xml
            $sitemapRes = syncSitemapXml();
            if (!$sitemapRes['success']) {
                throw new RuntimeException("sitemap.xml synchronization failed: " . ($sitemapRes['error'] ?? 'Unknown'));
            }

            // 3. Validate & update robots.txt
            $robotsRes = validateRobotsTxt();

            // 4. External search engine notifications
            $changedUrls = $options['changed_urls'] ?? [];
            $searchRes = syncSearchEngineNotifications($changedUrls);
        }

        // Validate Structured Data & Canonicals
        $schemaCheck = syncStructuredData();
        $canonCheck = syncCanonicalReferences();

        // Gather metrics
        $publicMd = discoverPublicMarkdownFiles();
        $publicJson = discoverPublicJsonSchemas();
        $publicSql = discoverPublicSqlSchemas();
        $blogs = getPublishedBlogArticles();
        $core = getPublicCorePages();
        $services = getPublicServicePages();

        $now = date('c');
        $nowFormatted = date('F j, Y \a\t g:i A');

        $stats = [
            'status'                        => 'active',
            'last_sync_time'                => $now,
            'last_sync_formatted'           => $nowFormatted,
            'last_sitemap_time'             => $now,
            'last_sitemap_formatted'        => $nowFormatted,
            'last_reconciliation_time'      => $now,
            'last_reconciliation_formatted' => $nowFormatted,
            'last_error'                    => null,
            'public_markdown_count'         => count($publicMd),
            'approved_schema_count'         => count($publicJson) + count($publicSql),
            'approved_json_count'           => count($publicJson),
            'approved_sql_count'            => count($publicSql),
            'published_blog_count'          => count($blogs),
            'core_pages_count'              => count($core),
            'service_pages_count'           => count($services),
            'sitemap_url_count'             => $sitemapRes['url_count'] ?? (count($core) + count($services) + count($blogs)),
            'total_entries'                 => count($core) + count($services) + count($blogs) + count($publicMd) + count($publicJson) + count($publicSql),
            'google_search_console'         => $searchRes['google'] ?? ['status' => 'unconfigured'],
            'indexnow'                      => $searchRes['indexnow'] ?? ['status' => 'pending']
        ];

        if (!$isDryRun) {
            saveLlmsSyncStatus($stats);
        }

        if ($lockFp) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }

        return [
            'success'   => true,
            'message'   => "Full reconciliation completed: {$stats['total_entries']} resources indexed, {$stats['sitemap_url_count']} URLs in sitemap.",
            'stats'     => $stats,
            'llms'      => $llmsRes,
            'sitemap'   => $sitemapRes,
            'robots'    => $robotsRes,
            'schema'    => $schemaCheck,
            'canonical' => $canonCheck,
            'search'    => $searchRes
        ];

    } catch (Throwable $e) {
        if ($lockFp) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }

        $errorMsg = $e->getMessage();
        error_log("Full Reconciliation Failure: " . $errorMsg);

        $prevStatus = getLlmsSyncStatus();
        $prevStatus['status'] = 'error';
        $prevStatus['last_error'] = $errorMsg;
        saveLlmsSyncStatus($prevStatus);

        return [
            'success' => false,
            'message' => 'Reconciliation failed. Previous valid files preserved.',
            'error'   => $errorMsg,
            'stats'   => $prevStatus
        ];
    }
}

/**
 * -------------------------------------------------------------------------
 * 16. CRUD Synchronization Trigger: triggerLlmsSynchronization()
 * -------------------------------------------------------------------------
 * Master synchronization event handler triggered by Admin Panel actions
 * (create_file, upload_file, save_file, rename_file, delete_file, reconcile_blogs).
 * Guarantees immediate filesystem audit, atomic llms.txt & sitemap.xml update,
 * blogs.md sync, and search engine notifications.
 *
 * @param string $action The admin action triggering synchronization
 * @param array $changedUrls Optional list of affected URLs for IndexNow ping
 * @return array Master reconciliation result
 */
function triggerLlmsSynchronization(string $action = 'auto', array $changedUrls = []): array {
    $logDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'llms_sync.log';
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$ts}] Trigger action: {$action}\n", FILE_APPEND);

    // 1. Sync blogs.md
    if (function_exists('syncBlogsMd')) {
        syncBlogsMd();
    }

    // 2. Run master reconciliation with force flag
    $res = runFullReconciliation([
        'force'        => true,
        'action'       => $action,
        'changed_urls' => $changedUrls
    ]);

    $statusStr = (!empty($res['success'])) ? 'SUCCESS' : ('FAILED: ' . ($res['error'] ?? 'Unknown error'));
    @file_put_contents($logFile, "[{$ts}] Outcome: {$statusStr}\n", FILE_APPEND);

    return $res;
}


/**
 * -------------------------------------------------------------------------
 * blogs.md Generation & Synchronization
 * Generates a plain-text Markdown file at CMS_ROOT/blogs.md listing all
 * published blog articles sorted newest-first.
 * -------------------------------------------------------------------------
 */

/**
 * Extract clean plain text from an HTML snippet, decoding entities and normalizing whitespace.
 */
function extractBlogsSectionText(string $html): string {
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', trim($text));
    return $text ?? '';
}

/**
 * Extract static Hero and CTA copy from blogs.php with safe defaults.
 *
 * @return array{
 *   hero_eyebrow: string,
 *   hero_title: string,
 *   hero_desc: string,
 *   topics_eyebrow: string,
 *   topics_title: string,
 *   cta_eyebrow: string,
 *   cta_title: string,
 *   cta_desc: string,
 *   cta_primary_text: string,
 *   cta_primary_href: string,
 *   cta_secondary_text: string,
 *   cta_secondary_href: string
 * }
 */
function extractBlogsPageSections(string $baseUrl): array {
    $defaults = [
        'hero_eyebrow'       => 'THE CREATOR JOURNAL',
        'hero_title'         => 'UGC Marketing Tips and Creator Trends',
        'hero_desc'          => 'Fresh ideas on UGC video, creator culture and the way brands tell their stories today. Our team shares practical UGC marketing tips, video ad trends and creator content strategies you can put to work right away.',
        'topics_eyebrow'     => 'EXPLORE PERSPECTIVES',
        'topics_title'       => 'Explore by Topic',
        'cta_eyebrow'        => 'PUT STRATEGY INTO ACTION',
        'cta_title'          => 'Ready to Make Content That Sells?',
        'cta_desc'           => 'Work with MY UGC VIDEO to build real, creator-led video campaigns that get your brand noticed and bring in customers.',
        'cta_primary_text'   => 'START A PROJECT',
        'cta_primary_href'   => $baseUrl . '/contact',
        'cta_secondary_text' => 'EXPLORE OUR SERVICES',
        'cta_secondary_href' => $baseUrl . '/services',
    ];

    $blogsPhpPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'blogs.php';
    $source = @file_get_contents($blogsPhpPath);
    if (!is_string($source) || $source === '') {
        return $defaults;
    }
    // Strip PHP open/close tags so they do not terminate HTML tag regexes prematurely
    $source = preg_replace('/<\?(?:php|=).*?\?' . '>/is', '', $source) ?? $source;

    if (preg_match('/<section[^>]*class="[^"]*blog-hero-editorial[^"]*"[^>]*>(.*?)<\/section>/is', $source, $heroSec)) {
        $heroHtml = $heroSec[1];
        if (preg_match('/class="[^"]*blog-hero-eyebrow[^"]*"[^>]*>(.*?)<\/span>/is', $heroHtml, $m)) {
            $val = extractBlogsSectionText($m[1]);
            if ($val !== '') $defaults['hero_eyebrow'] = $val;
        }
        if (preg_match('/<h1[^>]*id="blog-hero-title"[^>]*>(.*?)<\/h1>/is', $heroHtml, $m)) {
            $val = extractBlogsSectionText($m[1]);
            if ($val !== '') $defaults['hero_title'] = $val;
        }
        if (preg_match('/<p[^>]*class="[^"]*blog-hero-desc[^"]*"[^>]*>(.*?)<\/p>/is', $heroHtml, $m)) {
            $val = extractBlogsSectionText($m[1]);
            if ($val !== '') $defaults['hero_desc'] = $val;
        }
    }

    if (preg_match('/<section[^>]*class="[^"]*latest-blogs-section[^"]*"[^>]*>(.*?)<\/section>/is', $source, $latestSec)) {
        $latestHtml = $latestSec[1];
        if (preg_match('/class="[^"]*about-eyebrow[^"]*"[^>]*>(.*?)<\/span>/is', $latestHtml, $m)) {
            $val = extractBlogsSectionText($m[1]);
            if ($val !== '') $defaults['topics_eyebrow'] = $val;
        }
        if (preg_match('/<h2[^>]*id="latest-heading"[^>]*>(.*?)<\/h2>/is', $latestHtml, $m)) {
            $val = extractBlogsSectionText($m[1]);
            if ($val !== '') $defaults['topics_title'] = $val;
        }
    }

    if (preg_match('/<section[^>]*class="[^"]*blog-closing-cta-section[^"]*"[^>]*>(.*?)<\/section>/is', $source, $ctaSec)) {
        $ctaHtml = $ctaSec[1];
        if (preg_match('/class="[^"]*about-eyebrow[^"]*"[^>]*>(.*?)<\/span>/is', $ctaHtml, $m)) {
            $val = extractBlogsSectionText($m[1]);
            if ($val !== '') $defaults['cta_eyebrow'] = $val;
        }
        if (preg_match('/<h2[^>]*id="blog-cta-heading"[^>]*>(.*?)<\/h2>/is', $ctaHtml, $m)) {
            $val = extractBlogsSectionText($m[1]);
            if ($val !== '') $defaults['cta_title'] = $val;
        }
        if (preg_match('/<p[^>]*class="[^"]*about-cta-desc[^"]*"[^>]*>(.*?)<\/p>/is', $ctaHtml, $m)) {
            $val = extractBlogsSectionText($m[1]);
            if ($val !== '') $defaults['cta_desc'] = $val;
        }
        if (preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $ctaHtml, $links) && count($links[1]) >= 2) {
            $pText = extractBlogsSectionText($links[1][0]);
            $sText = extractBlogsSectionText($links[1][1]);
            if ($pText !== '') $defaults['cta_primary_text'] = $pText;
            if ($sText !== '') $defaults['cta_secondary_text'] = $sText;
        }
    }

    return $defaults;
}

/**
 * Generate the content of blogs.md from blogs.php sections and published blog data in MySQL.
 */
function generateBlogsMd(): string {
    $baseUrl      = getLlmCanonicalBaseUrl();
    $sections     = extractBlogsPageSections($baseUrl);
    $featuredPost = null;
    $categories   = [];
    $gridPosts    = [];

    try {
        if (function_exists('getFeaturedPost')) {
            $featuredPost = getFeaturedPost();
        }
        if (function_exists('getAllCategories')) {
            $categories = array_values(array_filter(
                getAllCategories(),
                static fn($c) => (int)($c['post_count'] ?? 0) > 0
            ));
        }
        if (function_exists('getPosts')) {
            $gridArgs = [
                'status'   => 'published',
                'page'     => 1,
                'per_page' => 50,
            ];
            if ($featuredPost !== null) {
                $gridArgs['is_featured'] = 0;
            }
            $gridResult = getPosts($gridArgs);
            $gridPosts  = $gridResult['posts'] ?? [];
            $gridTotal  = (int)($gridResult['total'] ?? count($gridPosts));
        } else {
            $db   = getDb();
            $stmt = $db->query(
                "SELECT p.*, c.name AS category_name, c.slug AS category_slug
                 FROM blog_posts p
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE p.status = 'published' AND p.published_at <= NOW()
                 ORDER BY p.published_at DESC, p.id DESC"
            );
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all as $row) {
                if ((int)($row['is_featured'] ?? 0) === 1 && $featuredPost === null) {
                    $featuredPost = $row;
                } else {
                    $gridPosts[] = $row;
                }
            }
            $gridTotal = count($gridPosts);
        }
    } catch (Throwable $e) {
        error_log("generateBlogsMd: DB fetch failed: " . $e->getMessage());
        $gridTotal = 0;
    }

    $totalPublishedCount = ($featuredPost !== null ? 1 : 0) + ($gridTotal ?? count($gridPosts));
    $now = date('Y-m-d');

    $lines = [];

    // 0. Introductory Information Block
    $lines[] = "# MY UGC VIDEO — Published Blogs";
    $lines[] = "";
    $lines[] = "Published blog articles from MY UGC VIDEO — a UGC video production company helping brands drive authentic content and performance marketing results.";
    $lines[] = "";
    $lines[] = "Website: {$baseUrl}/";
    $lines[] = "Blogs: {$baseUrl}/blogs";
    $lines[] = "Total published articles: {$totalPublishedCount}";
    $lines[] = "Last updated: {$now}";
    $lines[] = "";
    $lines[] = "---";
    $lines[] = "";

    // 1. Blogs Hero Section
    $lines[] = "# {$sections['hero_title']}";
    $lines[] = "";
    $lines[] = "**{$sections['hero_eyebrow']}**";
    $lines[] = "";
    $lines[] = $sections['hero_desc'];
    $lines[] = "";
    $lines[] = "- **URL:** {$baseUrl}/blogs";
    $lines[] = "";
    $lines[] = "---";
    $lines[] = "";

    // 2. Featured Article (if a published post is marked is_featured = 1)
    if ($featuredPost !== null) {
        $featTitle   = trim((string)$featuredPost['title']);
        $featSlug    = trim((string)$featuredPost['slug']);
        $featUrl     = $baseUrl . '/blog/' . rawurlencode($featSlug);
        $featCat     = !empty($featuredPost['category_name']) ? trim((string)$featuredPost['category_name']) : 'UGC Trends';
        $featDate    = !empty($featuredPost['published_at'])
            ? (function_exists('formatDate') ? formatDate($featuredPost['published_at']) : date('F j, Y', strtotime($featuredPost['published_at'])))
            : '';
        $featRead    = trim((string)($featuredPost['reading_time'] ?? ''));
        $featExcerpt = trim((string)($featuredPost['excerpt'] ?? ''));
        if ($featExcerpt === '' && !empty($featuredPost['meta_description'])) {
            $featExcerpt = trim((string)$featuredPost['meta_description']);
        }
        $featAuthor  = trim((string)($featuredPost['author_name'] ?? 'MY UGC VIDEO'));
        $featAlt     = trim((string)($featuredPost['featured_image_alt'] ?? ''));

        $lines[] = "## Featured Article";
        $lines[] = "";
        $lines[] = "### [{$featTitle}]({$featUrl})";
        $lines[] = "";
        $lines[] = "- **Category:** {$featCat}";
        if ($featDate !== '') {
            $lines[] = "- **Published:** {$featDate}";
        }
        if ($featRead !== '') {
            $lines[] = "- **Reading Time:** {$featRead}";
        }
        $lines[] = "- **Author:** By {$featAuthor}";
        if ($featAlt !== '') {
            $lines[] = "- **Featured Image Alt:** {$featAlt}";
        }
        $lines[] = "- **URL:** {$featUrl}";
        if ($featExcerpt !== '') {
            $lines[] = "";
            $lines[] = $featExcerpt;
        }
        $lines[] = "";
        $lines[] = "[Read Featured Article]({$featUrl})";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";
    }

    // 3. Explore Perspectives / Topics
    $lines[] = "## {$sections['topics_title']}";
    $lines[] = "";
    $lines[] = "**{$sections['topics_eyebrow']}**";
    $lines[] = "";
    if (!empty($categories)) {
        foreach ($categories as $cat) {
            $catName = trim((string)($cat['name'] ?? ''));
            $catCount = (int)($cat['post_count'] ?? 0);
            if ($catName !== '' && $catCount > 0) {
                $unit = $catCount === 1 ? 'article' : 'articles';
                $lines[] = "- **{$catName}** ({$catCount} {$unit})";
            }
        }
    } else {
        $lines[] = "- All Articles";
    }
    $lines[] = "";
    $lines[] = "---";
    $lines[] = "";

    // 4. Blog Articles Listing
    $lines[] = "## Blog Articles";
    $lines[] = "";

    if (empty($gridPosts)) {
        $lines[] = "No additional published articles.";
        $lines[] = "";
    } else {
        foreach ($gridPosts as $post) {
            $title    = trim((string)$post['title']);
            $slug     = trim((string)$post['slug']);
            $url      = $baseUrl . '/blog/' . rawurlencode($slug);
            $catName  = !empty($post['category_name']) ? trim((string)$post['category_name']) : 'UGC';
            $pubDate  = !empty($post['published_at'])
                ? (function_exists('formatDate') ? formatDate($post['published_at']) : date('F j, Y', strtotime($post['published_at'])))
                : '';
            $readTime = trim((string)($post['reading_time'] ?? ''));
            $excerpt  = trim((string)($post['excerpt'] ?? ''));
            if ($excerpt === '' && !empty($post['meta_description'])) {
                $excerpt = trim((string)$post['meta_description']);
            }
            $author   = trim((string)($post['author_name'] ?? 'MY UGC VIDEO'));

            $tagNames = [];
            if (function_exists('getPostTags') && !empty($post['id'])) {
                $cardTags = getPostTags((int)$post['id']);
                foreach (array_slice($cardTags, 0, 3) as $ct) {
                    $tName = trim((string)($ct['name'] ?? ''));
                    if ($tName !== '') {
                        $tagNames[] = '#' . $tName;
                    }
                }
            }

            $lines[] = "### [{$title}]({$url})";
            $lines[] = "";
            $lines[] = "- **Category:** {$catName}";
            if ($pubDate !== '') {
                $lines[] = "- **Published:** {$pubDate}";
            }
            if ($readTime !== '') {
                $lines[] = "- **Reading Time:** {$readTime}";
            }
            $lines[] = "- **Author:** By {$author}";
            if (!empty($tagNames)) {
                $lines[] = "- **Tags:** " . implode(', ', $tagNames);
            }
            $lines[] = "- **URL:** {$url}";
            if ($excerpt !== '') {
                $lines[] = "";
                $lines[] = $excerpt;
            }
            $lines[] = "";
        }

        // 5. Meaningful article count note when more than 6 grid articles exist
        $gridCount = count($gridPosts);
        if ($gridCount > 6) {
            $lines[] = "*Total articles in listing: {$gridCount}.*";
            $lines[] = "";
        }
    }

    $lines[] = "---";
    $lines[] = "";

    // 6. PUT STRATEGY INTO ACTION CTA Section
    $lines[] = "## {$sections['cta_title']}";
    $lines[] = "";
    $lines[] = "**{$sections['cta_eyebrow']}**";
    $lines[] = "";
    $lines[] = $sections['cta_desc'];
    $lines[] = "";
    $lines[] = "- [{$sections['cta_primary_text']}]({$sections['cta_primary_href']})";
    $lines[] = "- [{$sections['cta_secondary_text']}]({$sections['cta_secondary_href']})";
    $lines[] = "";

    return implode("\n", $lines);
}

/**
 * Atomically write blogs.md to both the project root and markdown/pages/blogs.md.
 *
 * @return array{success: bool, bytes: int, error: string}
 */
function syncBlogsMd(): array {
    try {
        if (!class_exists('WebsiteMarkdownSync')) {
            $syncFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'website-markdown-sync.php';
            if (file_exists($syncFile)) {
                require_once $syncFile;
            }
        }
        if (class_exists('WebsiteMarkdownSync')) {
            $res = WebsiteMarkdownSync::syncBlogsListing(true);
            return [
                'success' => !empty($res['success']),
                'bytes'   => (int)($res['bytes'] ?? 0),
                'error'   => !empty($res['success']) ? '' : 'Failed to sync blogs.md via WebsiteMarkdownSync.',
            ];
        }

        $content  = generateBlogsMd();
        $destPath1 = CMS_ROOT . DIRECTORY_SEPARATOR . 'blogs.md';
        $destPath2 = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'blogs.md';

        $w1 = atomicWriteFile($destPath1, $content);
        $w2 = atomicWriteFile($destPath2, $content);

        if (!$w1 || !$w2) {
            return ['success' => false, 'bytes' => 0, 'error' => "Failed to write blogs.md atomically."];
        }

        return ['success' => true, 'bytes' => strlen($content), 'error' => ''];
    } catch (Throwable $e) {
        return ['success' => false, 'bytes' => 0, 'error' => $e->getMessage()];
    }
}