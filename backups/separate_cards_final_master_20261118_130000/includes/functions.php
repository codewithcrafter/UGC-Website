<?php
/**
 * MY UGC VIDEO - Core Helper Functions
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

/**
 * Shortcut to database PDO connection
 */
function getDb(): PDO {
    return Database::getConnection();
}

/**
 * Safe HTML escaping
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate clean URL slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'post-' . time() : $text;
}

/**
 * Format publication date
 */
function formatDate(?string $datetime, string $format = 'F j, Y'): string {
    if (!$datetime) return '';
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Estimate reading time in minutes
 */
function estimateReadingTime(string $content, int $wpm = 200): string {
    $cleanText = strip_tags($content);
    $wordCount = str_word_count($cleanText);
    $minutes = max(1, (int) ceil($wordCount / $wpm));
    return "{$minutes} Min Read";
}

/**
 * Sanitize article body HTML safely while preserving formatting
 */
function sanitizeHtmlContent(string $html): string {
    // Permitted HTML tags for blog formatting
    $allowedTags = '<p><br><h2><h3><h4><h5><h6><strong><b><em><i><u><s>' .
                   '<ul><ol><li><blockquote><a><img><div><span><code><pre><hr><section><figure><figcaption>';
    
    $clean = strip_tags($html, $allowedTags);

    // Remove any inline javascript: or vbscript: links or on* attributes
    $clean = preg_replace('/(<[^>]+?)(on[a-zA-Z]+\s*=\s*([\'"][^\'"]*[\'"]|[^\s>]+))/i', '$1', $clean);
    $clean = preg_replace('/href\s*=\s*[\'"]\s*(javascript|data|vbscript):[^\'"]*[\'"]/i', 'href="#"', $clean);

    return $clean;
}

/**
 * Retrieve single post by slug
 */
function getPostBySlug(string $slug, bool $publishedOnly = true): ?array {
    $db = getDb();
    $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
            FROM blog_posts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.slug = :slug";
    
    if ($publishedOnly) {
        $sql .= " AND p.status = 'published'";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $post = $stmt->fetch();
    return $post ?: null;
}

/**
 * Retrieve featured article
 * Only returns a post that is published and explicitly marked as is_featured = 1.
 * Never automatically falls back to other posts.
 */
function getFeaturedPost(): ?array {
    $db = getDb();
    $stmt = $db->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
         FROM blog_posts p 
         LEFT JOIN categories c ON p.category_id = c.id 
         WHERE p.status = 'published' 
           AND p.published_at <= NOW() 
           AND p.is_featured = 1 
         ORDER BY p.published_at DESC, p.id DESC 
         LIMIT 1"
    );
    $stmt->execute();
    $post = $stmt->fetch();
    return $post ?: null;
}

/**
 * Retrieve paginated posts with optional category & search filter
 */
function getPosts(array $options = []): array {
    $db = getDb();
    
    $categorySlug  = $options['category'] ?? null;
    $tag           = !empty($options['tag']) ? trim($options['tag']) : null;
    $search        = $options['search'] ?? null;
    $status        = $options['status'] ?? 'published'; // 'published', 'draft', or null for all
    $excludeId     = $options['exclude_id'] ?? null;
    $isFeatured    = $options['is_featured'] ?? null;
    $includeFuture = $options['include_future'] ?? false;
    $page          = max(1, (int) ($options['page'] ?? 1));
    $perPage       = max(1, (int) ($options['per_page'] ?? 10));
    $offset        = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    $joins = "LEFT JOIN categories c ON p.category_id = c.id";

    if ($tag !== null && $tag !== '') {
        $joins .= " INNER JOIN blog_post_tags bpt ON p.id = bpt.post_id INNER JOIN tags t ON bpt.tag_id = t.id";
        $where[] = "(t.slug = :tag_slug OR t.name = :tag_name)";
        $params[':tag_slug'] = slugify(ltrim($tag, '#'));
        $params[':tag_name'] = ltrim($tag, '#');
    }

    if ($status !== null) {
        $where[] = "p.status = :status";
        $params[':status'] = $status;

        // Prevent scheduled future posts from appearing in public published queries
        if ($status === 'published' && !$includeFuture) {
            $where[] = "p.published_at <= NOW()";
        }
    }

    if ($isFeatured !== null) {
        if ($isFeatured === 0 || $isFeatured === false || $isFeatured === '0') {
            $where[] = "p.is_featured = 0";
        } elseif ($isFeatured === 1 || $isFeatured === true || $isFeatured === '1') {
            $where[] = "p.is_featured = 1";
        }
    }

    if ($excludeId) {
        $where[] = "p.id != :exclude_id";
        $params[':exclude_id'] = $excludeId;
    }

    if ($categorySlug && $categorySlug !== 'all') {
        $where[] = "c.slug = :category_slug";
        $params[':category_slug'] = $categorySlug;
    }

    if ($search) {
        $where[] = "(p.title LIKE :search1 OR p.excerpt LIKE :search2 OR p.content LIKE :search3 OR p.keywords LIKE :search4)";
        $searchParam = '%' . $search . '%';
        $params[':search1'] = $searchParam;
        $params[':search2'] = $searchParam;
        $params[':search3'] = $searchParam;
        $params[':search4'] = $searchParam;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count (using DISTINCT p.id for tag joins)
    $countSql = "SELECT COUNT(DISTINCT p.id) FROM blog_posts p {$joins} {$whereSql}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Get rows sorted strictly by published date descending (newest first)
    $sql = "SELECT DISTINCT p.*, c.name AS category_name, c.slug AS category_slug 
            FROM blog_posts p 
            {$joins} 
            {$whereSql} 
            ORDER BY p.published_at DESC, p.id DESC 
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    return [
        'posts'        => $posts,
        'total'        => $total,
        'page'         => $page,
        'per_page'     => $perPage,
        'total_pages'  => ceil($total / $perPage)
    ];
}

/**
 * Retrieve related published articles (up to 3)
 */
function getRelatedPosts(int $currentPostId, ?int $categoryId, int $limit = 3): array {
    $db = getDb();
    
    // First try same category
    if ($categoryId) {
        $stmt = $db->prepare(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
             FROM blog_posts p 
             LEFT JOIN categories c ON p.category_id = c.id 
             WHERE p.id != :current_id AND p.category_id = :category_id AND p.status = 'published' AND p.published_at <= NOW()
             ORDER BY p.published_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':current_id', $currentPostId, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $related = $stmt->fetchAll();
        if (count($related) >= $limit) {
            return $related;
        }
    } else {
        $related = [];
    }

    // Fill remainder with other recent published posts
    $needed = $limit - count($related);
    $excludeIds = array_merge([$currentPostId], array_column($related, 'id'));
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

    $fillStmt = $db->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
         FROM blog_posts p 
         LEFT JOIN categories c ON p.category_id = c.id 
         WHERE p.id NOT IN ($placeholders) AND p.status = 'published' AND p.published_at <= NOW()
         ORDER BY p.published_at DESC 
         LIMIT {$needed}"
    );
    $fillStmt->execute($excludeIds);
    $more = $fillStmt->fetchAll();

    return array_merge($related, $more);
}

/**
 * Retrieve all categories with post counts
 */
function getAllCategories(): array {
    $db = getDb();
    $sql = "SELECT c.*, COUNT(p.id) AS post_count 
            FROM categories c 
            LEFT JOIN blog_posts p ON c.id = p.category_id AND p.status = 'published' AND p.published_at <= NOW()
            GROUP BY c.id 
            ORDER BY c.name ASC";
    return $db->query($sql)->fetchAll();
}

/**
 * Secure image upload handler
 */
function handleImageUpload(array $file): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload parameters.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File exceeds maximum upload size limit.'];
        default:
            return ['success' => false, 'error' => 'Upload error occurred. Code: ' . $file['error']];
    }

    $maxBytes = (int) (getenv('MAX_UPLOAD_SIZE') ?: 5242880); // 5MB
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed (' . round($maxBytes / 1048576, 1) . 'MB).'];
    }

    // Server-side MIME verification using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif'
    ];

    $ext = array_search($mime, $allowedMimes, true);
    if ($ext === false) {
        return ['success' => false, 'error' => 'Invalid image format. Allowed: JPG, PNG, WebP, GIF.'];
    }

    // Safe random filename without path traversal risk
    $filename = sprintf('blog_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(6)), $ext);
    $targetPath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file to destination directory.'];
    }

    // Set safe permissions
    @chmod($targetPath, 0644);

    return [
        'success'   => true,
        'filename'  => $filename,
        'path'      => 'uploads/blogs/' . $filename,
        'url'       => UPLOADS_URL . '/' . $filename
    ];
}

/**
 * Flash messaging helpers
 */
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * =========================================================================
 * Intelligent Dynamic Schema Generator
 * =========================================================================
 */
function generateBlogSchema(array $post, array $faqs = []): array {
    $baseUrl = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $cleanSlug = $post['slug'] ?: slugify($post['title'] ?? 'article');
    $canonicalUrl = (!empty($post['canonical_url']) && str_starts_with($post['canonical_url'], 'https://') && !str_contains($post['canonical_url'], 'example.com') && !str_contains($post['canonical_url'], 'demo.myugcvideo') && !str_ends_with($post['canonical_url'], '.html') && !str_contains($post['canonical_url'], 'blog.php')) 
        ? rtrim($post['canonical_url'], '/') 
        : ($baseUrl . '/blog/' . urlencode($cleanSlug));
    $featuredImgUrl = !empty($post['featured_image'])
        ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : $baseUrl . '/' . ltrim($post['featured_image'], '/'))
        : $baseUrl . '/images/blogs/blog-1.jpg';

    $metaDescription = !empty($post['meta_description']) ? $post['meta_description'] : ($post['excerpt'] ?? '');

    // Canonical Organization
    $organization = [
        "@type" => "Organization",
        "@id"   => $baseUrl . "/#organization",
        "name"  => "MY UGC VIDEO",
        "url"   => $baseUrl . "/",
        "logo"  => [
            "@type" => "ImageObject",
            "url"   => $baseUrl . "/images/hero/hero-creator.jpg"
        ],
        "description" => "Premier performance-driven UGC video production, creator management and content marketing studio."
    ];

    // BreadcrumbList (Home -> Blogs -> Article)
    $breadcrumbItems = [
        [
            "@type"    => "ListItem",
            "position" => 1,
            "name"     => "Home",
            "item"     => $baseUrl . "/"
        ],
        [
            "@type"    => "ListItem",
            "position" => 2,
            "name"     => "Blogs",
            "item"     => $baseUrl . "/blogs"
        ],
        [
            "@type"    => "ListItem",
            "position" => 3,
            "name"     => $post['title'] ?? 'Untitled Article',
            "item"     => $canonicalUrl
        ]
    ];

    $breadcrumbList = [
        "@type"           => "BreadcrumbList",
        "@id"             => $canonicalUrl . "#breadcrumb",
        "itemListElement" => $breadcrumbItems
    ];

    // BlogPosting
    $blogPosting = [
        "@type"            => "BlogPosting",
        "@id"              => $canonicalUrl . "#article",
        "isPartOf"         => [
            "@type" => "WebSite",
            "@id"   => $baseUrl . "/#website",
            "name"  => "MY UGC VIDEO",
            "url"   => $baseUrl . "/"
        ],
        "headline"         => $post['title'] ?? '',
        "description"      => $metaDescription,
        "image"            => $featuredImgUrl,
        "datePublished"    => !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c'),
        "dateModified"     => !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : (!empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c')),
        "mainEntityOfPage" => [
            "@type" => "WebPage",
            "@id"   => $canonicalUrl
        ],
        "author"           => [
            "@type"    => "Person",
            "name"     => $post['author_name'] ?? 'MY UGC VIDEO Editorial Team',
            "jobTitle" => $post['author_role'] ?? 'UGC Creative Studio'
        ],
        "publisher"        => [
            "@id" => $baseUrl . "/#organization"
        ]
    ];

    $graph = [$organization, $breadcrumbList, $blogPosting];

    // FAQPage (Included only when actual FAQs exist)
    $validFaqs = [];
    foreach ($faqs as $f) {
        $q = trim($f['question'] ?? '');
        $a = trim($f['answer'] ?? '');
        if ($q !== '' && $a !== '') {
            $validFaqs[] = [
                "@type"          => "Question",
                "name"           => $q,
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text"  => strip_tags($a)
                ]
            ];
        }
    }

    if (!empty($validFaqs)) {
        $faqPage = [
            "@type"      => "FAQPage",
            "@id"        => $canonicalUrl . "#faq",
            "mainEntity" => $validFaqs
        ];
        $graph[] = $faqPage;
    }

    return [
        "@context" => "https://schema.org",
        "@graph"   => $graph
    ];
}

/**
 * Retrieve admin sidebar navigation items for blog navigation selection
 * Exactly 6 items in defined order, matching the admin panel sidebar
 */
function getWebsiteNavigationItems(): array {
    return [
        [
            'id'    => 'admin_nav_dashboard',
            'title' => 'Dashboard',
            'url'   => '/admin/dashboard.php',
            'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect></svg>'
        ],
        [
            'id'    => 'admin_nav_posts',
            'title' => 'All Blog Articles',
            'url'   => '/admin/posts/index.php',
            'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>'
        ],
        [
            'id'    => 'admin_nav_create',
            'title' => 'Add New Blog',
            'url'   => '/admin/posts/create.php',
            'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
        ],
        [
            'id'    => 'admin_nav_markdown',
            'title' => 'Markdown & LLMs',
            'url'   => '/admin/markdown/index.php',
            'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>'
        ],
        [
            'id'    => 'admin_nav_categories',
            'title' => 'Categories',
            'url'   => '/admin/categories/index.php',
            'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>'
        ],
        [
            'id'       => 'admin_nav_public_blogs',
            'title'    => 'View Live Blogs',
            'subtitle' => 'Public Site',
            'url'      => '/blogs.php',
            'icon'     => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>'
        ]
    ];
}

function getAdminSidebarNavigationItems(): array {
    return getWebsiteNavigationItems();
}

/**
 * Validate JSON-LD String with comprehensive diagnostics
 */
function validateJsonLd(string $json): array {
    $trimmed = trim($json);
    if ($trimmed === '') {
        return ['valid' => false, 'error' => 'Schema JSON is empty.', 'data' => null];
    }

    $data = json_decode($trimmed, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'error' => 'JSON Syntax Error: ' . json_last_error_msg(), 'data' => null];
    }

    if (!is_array($data)) {
        return ['valid' => false, 'error' => 'Root JSON must be an object (e.g. { ... }) or an array of objects.', 'data' => null];
    }

    // Check @context
    $hasContext = false;
    if (isset($data['@context'])) {
        $ctx = is_string($data['@context']) ? $data['@context'] : (is_array($data['@context']) ? json_encode($data['@context']) : '');
        if (stripos($ctx, 'schema.org') !== false) {
            $hasContext = true;
        }
    }

    // Check @type or @graph
    $hasType = !empty($data['@type']);
    $hasGraph = !empty($data['@graph']) && is_array($data['@graph']);

    if (!$hasContext && !$hasGraph) {
        return [
            'valid' => false,
            'error' => 'Missing standard "@context": "https://schema.org". Valid JSON-LD structured data must specify Schema.org context.',
            'data' => null
        ];
    }

    if (!$hasType && !$hasGraph) {
        return [
            'valid' => false,
            'error' => 'Missing "@type" property. Schema must specify an entity type such as "BlogPosting", "Article", or a "@graph" array.',
            'data' => null
        ];
    }

    return [
        'valid'     => true,
        'error'     => null,
        'data'      => $data,
        'cleanJson' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ];
}

/**
 * Secure handler for uploaded schema JSON file
 */
function validateUploadedSchemaFile(array $file): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload parameters.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'No JSON file was selected.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error (Code: ' . $file['error'] . ').'];
    }

    // Validate size (max 512KB for JSON schema)
    $maxBytes = 524288;
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'Schema file exceeds maximum limit of 500KB.'];
    }

    // Validate extension
    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext !== 'json') {
        return ['success' => false, 'error' => 'Invalid file type. Only .json files are accepted.'];
    }

    // Read content
    $content = file_get_contents($file['tmp_name']);
    if ($content === false || trim($content) === '') {
        return ['success' => false, 'error' => 'Uploaded file is empty or cannot be read.'];
    }

    // Validate JSON-LD structure
    $val = validateJsonLd($content);
    if (!$val['valid']) {
        return ['success' => false, 'error' => $val['error']];
    }

    // Store in controlled private storage
    $schemaDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'schemas';
    if (!is_dir($schemaDir)) {
        @mkdir($schemaDir, 0755, true);
    }

    $safeFilename = sprintf('schema_%s_%s.json', date('Ymd_His'), bin2hex(random_bytes(6)));
    $destPath = $schemaDir . DIRECTORY_SEPARATOR . $safeFilename;
    file_put_contents($destPath, $val['cleanJson']);

    return [
        'success'       => true,
        'orig_filename' => $origName,
        'stored_path'   => $destPath,
        'clean_json'    => $val['cleanJson'],
        'data'          => $val['data']
    ];
}

/**
 * =========================================================================
 * Markdown <-> HTML Conversion Engine with Table Support
 * Zero-dependency robust parser
 * =========================================================================
 */
function markdownToHtml(string $markdown): string {
    $text = trim($markdown);
    if ($text === '') return '';

    // Standardize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // 1. First parse any Markdown Pipe Tables into HTML tables
    $text = preg_replace_callback('/((?:(?:^|\n)\|[^\n]+\|[ \t]*)+)/m', function($match) {
        $block = trim($match[1]);
        $lines = explode("\n", $block);
        if (count($lines) < 2) return $match[0];

        $htmlTable = '<div class="table-responsive"><table class="editorial-table">';
        $headerDone = false;
        $inBody = false;

        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if (!$line || !str_contains($line, '|')) continue;

            $trimmedLine = trim($line, '|');
            $cells = array_map('trim', explode('|', $trimmedLine));

            // Check if separator line
            $isSeparator = true;
            foreach ($cells as $c) {
                if (!preg_match('/^:?-+:?$/', $c)) {
                    $isSeparator = false;
                    break;
                }
            }

            if ($isSeparator) {
                $headerDone = true;
                continue;
            }

            if (!$headerDone && $idx === 0) {
                $htmlTable .= '<thead><tr>';
                foreach ($cells as $c) {
                    $htmlTable .= '<th>' . parseMarkdownInline($c) . '</th>';
                }
                $htmlTable .= '</tr></thead>';
            } else {
                if (!$inBody) {
                    $htmlTable .= '<tbody>';
                    $inBody = true;
                }
                $htmlTable .= '<tr>';
                foreach ($cells as $c) {
                    $htmlTable .= '<td>' . parseMarkdownInline($c) . '</td>';
                }
                $htmlTable .= '</tr>';
            }
        }

        if ($inBody) {
            $htmlTable .= '</tbody>';
        }
        $htmlTable .= '</table></div>';
        return "\n\n" . $htmlTable . "\n\n";
    }, $text);

    // 2. Protect existing HTML tables from line-by-line escaping
    $tablePlaceholders = [];
    $tableIndex = 0;
    $text = preg_replace_callback('/<div class="table-responsive">.*?<\/div>|<table[^>]*>.*?<\/table>/is', function($tm) use (&$tablePlaceholders, &$tableIndex) {
        $ph = "___TABLE_BLOCK_{$tableIndex}___";
        $tablePlaceholders[$ph] = $tm[0];
        $tableIndex++;
        return "\n\n" . $ph . "\n\n";
    }, $text);

    $lines = explode("\n", $text);
    $output = [];
    $inList = false;
    $listType = '';
    $inBlockquote = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Blank line closes lists and blockquotes
        if ($trimmed === '') {
            if ($inList) {
                $output[] = ($listType === 'ul') ? '</ul>' : '</ol>';
                $inList = false;
                $listType = '';
            }
            if ($inBlockquote) {
                $output[] = '</blockquote>';
                $inBlockquote = false;
            }
            continue;
        }

        // Table Placeholder
        if (preg_match('/^___TABLE_BLOCK_\d+___$/', $trimmed)) {
            if ($inList) { $output[] = ($listType === 'ul') ? '</ul>' : '</ol>'; $inList = false; }
            if ($inBlockquote) { $output[] = '</blockquote>'; $inBlockquote = false; }
            $output[] = $tablePlaceholders[$trimmed] ?? '';
            continue;
        }

        // Horizontal Rule
        if (preg_match('/^(?:---|\*\*\*|___)$/', $trimmed)) {
            if ($inList) { $output[] = ($listType === 'ul') ? '</ul>' : '</ol>'; $inList = false; }
            if ($inBlockquote) { $output[] = '</blockquote>'; $inBlockquote = false; }
            $output[] = '<hr>';
            continue;
        }

        // Headings
        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
            if ($inList) { $output[] = ($listType === 'ul') ? '</ul>' : '</ol>'; $inList = false; }
            if ($inBlockquote) { $output[] = '</blockquote>'; $inBlockquote = false; }
            $level = strlen($m[1]);
            $title = parseMarkdownInline($m[2]);
            $output[] = "<h{$level}>{$title}</h{$level}>";
            continue;
        }

        // Blockquote
        if (preg_match('/^>\s*(.*)$/', $trimmed, $m)) {
            if ($inList) { $output[] = ($listType === 'ul') ? '</ul>' : '</ol>'; $inList = false; }
            if (!$inBlockquote) {
                $output[] = '<blockquote>';
                $inBlockquote = true;
            }
            $quoteText = parseMarkdownInline($m[1]);
            $output[] = "<p>{$quoteText}</p>";
            continue;
        } elseif ($inBlockquote) {
            $output[] = '</blockquote>';
            $inBlockquote = false;
        }

        // Unordered List (- or *)
        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
            if ($inBlockquote) { $output[] = '</blockquote>'; $inBlockquote = false; }
            if (!$inList || $listType !== 'ul') {
                if ($inList) $output[] = ($listType === 'ul') ? '</ul>' : '</ol>';
                $output[] = '<ul>';
                $inList = true;
                $listType = 'ul';
            }
            $item = parseMarkdownInline($m[1]);
            $output[] = "  <li>{$item}</li>";
            continue;
        }

        // Ordered List (1. )
        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
            if ($inBlockquote) { $output[] = '</blockquote>'; $inBlockquote = false; }
            if (!$inList || $listType !== 'ol') {
                if ($inList) $output[] = ($listType === 'ul') ? '</ul>' : '</ol>';
                $output[] = '<ol>';
                $inList = true;
                $listType = 'ol';
            }
            $item = parseMarkdownInline($m[1]);
            $output[] = "  <li>{$item}</li>";
            continue;
        }

        // If currently in a list but line doesn't match list item, close list
        if ($inList) {
            $output[] = ($listType === 'ul') ? '</ul>' : '</ol>';
            $inList = false;
            $listType = '';
        }

        // Standard Paragraph
        $para = parseMarkdownInline($trimmed);
        $output[] = "<p>{$para}</p>";
    }

    if ($inList) {
        $output[] = ($listType === 'ul') ? '</ul>' : '</ol>';
    }
    if ($inBlockquote) {
        $output[] = '</blockquote>';
    }

    return implode("\n", $output);
}

/**
 * Helper: Parse inline markdown formatting (bold, italic, links, images, code)
 */
function parseMarkdownInline(string $str): string {
    // Escape standard HTML first
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');

    // Inline Code
    $str = preg_replace('/`([^`]+)`/', '<code>$1</code>', $str);

    // Images: ![alt](url)
    $str = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1">', $str);

    // Links: [text](url)
    $str = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $str);

    // Bold: **text** or __text__
    $str = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $str);
    $str = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $str);

    // Italic: *text* or _text_
    $str = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $str);
    $str = preg_replace('/_([^_]+)_/', '<em>$1</em>', $str);

    return $str;
}

/**
 * Convert HTML to Clean Markdown with Table Support
 */
function htmlToMarkdown(string $html): string {
    $text = trim($html);
    if ($text === '') return '';

    // Convert HTML Tables into Markdown Pipe Tables before stripping tags
    $text = preg_replace_callback('/<table[^>]*>(.*?)<\/table>/is', function($matches) {
        $rows = [];
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $matches[1], $trMatches);
        $hasHeader = false;
        foreach ($trMatches[1] as $tr) {
            $cells = [];
            if (preg_match_all('/<th[^>]*>(.*?)<\/th>/is', $tr, $thMatches)) {
                foreach ($thMatches[1] as $c) {
                    $clean = trim(strip_tags($c));
                    $clean = str_replace('|', '\|', $clean);
                    $cells[] = $clean;
                }
                $rows[] = '| ' . implode(' | ', $cells) . ' |';
                $sep = array_fill(0, count($cells), '---');
                $rows[] = '| ' . implode(' | ', $sep) . ' |';
                $hasHeader = true;
            } elseif (preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $tr, $tdMatches)) {
                foreach ($tdMatches[1] as $c) {
                    $clean = trim(strip_tags($c));
                    $clean = str_replace('|', '\|', $clean);
                    $cells[] = $clean;
                }
                if (!$hasHeader && empty($rows)) {
                    $dummy = [];
                    foreach ($cells as $idx => $v) { $dummy[] = 'Col ' . ($idx + 1); }
                    $rows[] = '| ' . implode(' | ', $dummy) . ' |';
                    $rows[] = '| ' . implode(' | ', array_fill(0, count($cells), '---')) . ' |';
                    $hasHeader = true;
                }
                $rows[] = '| ' . implode(' | ', $cells) . ' |';
            }
        }
        return "\n\n" . implode("\n", $rows) . "\n\n";
    }, $text);

    // Convert Headings
    for ($i = 6; $i >= 1; $i--) {
        $hashes = str_repeat('#', $i);
        $text = preg_replace('/<h' . $i . '[^>]*>(.*?)<\/h' . $i . '>/is', "\n\n{$hashes} $1\n\n", $text);
    }

    // Convert Blockquotes
    $text = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n\n> $1\n\n", $text);

    // Convert Lists
    $text = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $text);
    $text = preg_replace('/<\/?(ul|ol)[^>]*>/is', "\n", $text);

    // Convert Bold
    $text = preg_replace('/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/is', '**$2**', $text);

    // Convert Italic
    $text = preg_replace('/<(em|i)[^>]*>(.*?)<\/(em|i)>/is', '*$2*', $text);

    // Convert Links - Normalize relative internal links to root-relative
    $text = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function($m) {
        $href = trim($m[1]);
        $anchor = $m[2];
        if (!str_starts_with($href, 'http://') && !str_starts_with($href, 'https://') && !str_starts_with($href, '#') && !str_starts_with($href, 'mailto:') && !str_starts_with($href, 'tel:')) {
            $cleanHref = preg_replace('#^(\.\./|\./)+#', '', $href);
            if ($cleanHref === 'blogs.html') {
                $cleanHref = 'blogs.php';
            }
            $href = '/' . ltrim($cleanHref, '/');
        }
        return "[{$anchor}]({$href})";
    }, $text);

    // Convert Paragraphs & Breaks
    $text = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $text);
    $text = preg_replace('/<br\s*\/?>/is', "\n", $text);
    $text = preg_replace('/<hr\s*\/?>/is', "\n\n---\n\n", $text);

    // Strip leftover tags
    $text = strip_tags($text);

    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // Clean up excessive blank lines
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}

/**
 * =========================================================================
 * Blog ↔ Markdown & Schema.org BlogPosting Automatic Synchronization Suite
 * =========================================================================
 */

/**
 * Fetch assigned tag names for a blog post
 */
function getBlogPostTags(int $postId): array {
    try {
        $db = getDb();
        $stmt = $db->prepare(
            "SELECT t.name FROM blog_post_tags bpt 
             JOIN tags t ON bpt.tag_id = t.id 
             WHERE bpt.post_id = :id 
             ORDER BY t.name ASC"
        );
        $stmt->execute([':id' => $postId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Generate clean Markdown content with structured YAML frontmatter from blog database record
 */
function generateBlogPostMarkdown(array $post, array $tags = []): string {
    $cleanSlug = $post['slug'] ?: slugify($post['title']);
    $baseUrl = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $canonicalUrl = !empty($post['canonical_url']) && str_starts_with($post['canonical_url'], 'https://') && !str_contains($post['canonical_url'], 'example.com') && !str_contains($post['canonical_url'], 'demo.myugcvideo')
        ? rtrim($post['canonical_url'], '/')
        : $baseUrl . '/blog/' . urlencode($cleanSlug);

    $imgUrl = '';
    if (!empty($post['featured_image'])) {
        $imgUrl = str_starts_with($post['featured_image'], 'http')
            ? $post['featured_image']
            : $baseUrl . '/' . ltrim($post['featured_image'], '/');
    }

    $bodyMarkdown = htmlToMarkdown($post['content'] ?? '');

    $faqs = [];
    if (!empty($post['faqs_json'])) {
        $decoded = json_decode($post['faqs_json'], true);
        if (is_array($decoded)) {
            $faqs = $decoded;
        }
    }

    // Frontmatter construction
    $frontmatter = "---\n";
    $frontmatter .= "id: " . (int)$post['id'] . "\n";
    $frontmatter .= "title: " . json_encode($post['title'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    $frontmatter .= "slug: " . json_encode($cleanSlug, JSON_UNESCAPED_SLASHES) . "\n";
    $frontmatter .= "status: " . json_encode($post['status']) . "\n";
    $frontmatter .= "is_featured: " . ((int)($post['is_featured'] ?? 0) ? 1 : 0) . "\n";
    $frontmatter .= "category: " . json_encode($post['category_name'] ?? 'UGC Strategy', JSON_UNESCAPED_UNICODE) . "\n";
    if (!empty($tags)) {
        $frontmatter .= "tags: " . json_encode(array_values($tags), JSON_UNESCAPED_UNICODE) . "\n";
    }
    $frontmatter .= "author: " . json_encode($post['author_name'] ?? 'MY UGC VIDEO Editorial Team', JSON_UNESCAPED_UNICODE) . "\n";
    if (!empty($post['author_role'])) {
        $frontmatter .= "author_role: " . json_encode($post['author_role'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    $frontmatter .= "published_at: " . json_encode(!empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c')) . "\n";
    $frontmatter .= "updated_at: " . json_encode(!empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : date('c')) . "\n";
    $frontmatter .= "canonical_url: " . json_encode($canonicalUrl, JSON_UNESCAPED_SLASHES) . "\n";
    if (!empty($imgUrl)) {
        $frontmatter .= "featured_image: " . json_encode($imgUrl, JSON_UNESCAPED_SLASHES) . "\n";
        if (!empty($post['featured_image_alt'])) {
            $frontmatter .= "featured_image_alt: " . json_encode($post['featured_image_alt'], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    $frontmatter .= "reading_time: " . json_encode($post['reading_time'] ?? '5 Min Read') . "\n";
    $frontmatter .= "excerpt: " . json_encode($post['excerpt'] ?? '', JSON_UNESCAPED_UNICODE) . "\n";
    if (!empty($post['meta_title'])) {
        $frontmatter .= "meta_title: " . json_encode($post['meta_title'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    if (!empty($post['meta_description'])) {
        $frontmatter .= "meta_description: " . json_encode($post['meta_description'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    $frontmatter .= "---\n\n";

    $content = $frontmatter;
    $content .= "# " . trim($post['title']) . "\n\n";
    if (!empty($post['excerpt'])) {
        $content .= "> " . trim($post['excerpt']) . "\n\n";
    }
    $content .= $bodyMarkdown . "\n";

    if (!empty($faqs)) {
        $content .= "\n\n## Frequently Asked Questions\n\n";
        foreach ($faqs as $f) {
            $q = trim($f['question'] ?? '');
            $a = trim($f['answer'] ?? '');
            if ($q !== '' && $a !== '') {
                $content .= "### " . $q . "\n";
                $content .= $a . "\n\n";
            }
        }
    }

    return $content;
}

/**
 * Generate Schema.org BlogPosting JSON-LD array from blog database record
 */
function generateBlogPostSchema(array $post, array $tags = []): array {
    $cleanSlug = $post['slug'] ?: slugify($post['title']);
    $baseUrl = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $canonicalUrl = !empty($post['canonical_url']) && str_starts_with($post['canonical_url'], 'https://') && !str_contains($post['canonical_url'], 'example.com') && !str_contains($post['canonical_url'], 'demo.myugcvideo')
        ? rtrim($post['canonical_url'], '/')
        : $baseUrl . '/blog/' . urlencode($cleanSlug);

    $desc = !empty($post['meta_description']) 
        ? trim($post['meta_description']) 
        : (!empty($post['excerpt']) ? trim($post['excerpt']) : trim($post['title']));

    $authorName = !empty($post['author_name']) ? trim($post['author_name']) : 'MY UGC VIDEO Editorial Team';
    $authorObj = [
        '@type' => 'Person',
        'name'  => $authorName
    ];
    if (!empty($post['author_role'])) {
        $authorObj['jobTitle'] = trim($post['author_role']);
    }

    $publisherObj = [
        '@type' => 'Organization',
        'name'  => 'MY UGC VIDEO',
        'url'   => $baseUrl . '/',
        'logo'  => [
            '@type' => 'ImageObject',
            'url'   => $baseUrl . '/images/logo.png'
        ]
    ];

    $datePub = !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c');
    $dateMod = !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : $datePub;

    $schema = [
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'headline'         => trim($post['title']),
        'description'      => $desc,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id'   => $canonicalUrl
        ],
        'url'              => $canonicalUrl,
        'author'           => $authorObj,
        'publisher'        => $publisherObj,
        'datePublished'    => $datePub,
        'dateModified'     => $dateMod
    ];

    if (!empty($post['featured_image'])) {
        $imgUrl = str_starts_with($post['featured_image'], 'http')
            ? $post['featured_image']
            : $baseUrl . '/' . ltrim($post['featured_image'], '/');
        $schema['image'] = $imgUrl;
    }

    if (!empty($post['category_name'])) {
        $schema['articleSection'] = trim($post['category_name']);
    }

    if (!empty($tags)) {
        $schema['keywords'] = implode(', ', $tags);
    } elseif (!empty($post['keywords'])) {
        $schema['keywords'] = trim($post['keywords']);
    }

    return $schema;
}

/**
 * Validate generated Markdown content
 */
function validateBlogPostMarkdown(string $md): array {
    if (trim($md) === '' || strlen($md) < 50) {
        return ['valid' => false, 'error' => 'Markdown content is empty or incomplete.'];
    }
    if (!mb_check_encoding($md, 'UTF-8')) {
        return ['valid' => false, 'error' => 'Markdown content is not valid UTF-8.'];
    }
    if (!str_starts_with($md, "---\n") || !str_contains($md, "\n---\n")) {
        return ['valid' => false, 'error' => 'Markdown YAML frontmatter missing or malformed.'];
    }
    if (!preg_match('/^#\s+.+/m', $md)) {
        return ['valid' => false, 'error' => 'Markdown document title heading (# Title) is missing.'];
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Validate generated Schema.org BlogPosting JSON-LD array
 */
function validateBlogPostSchema(array $schema): array {
    if (empty($schema['@context']) || stripos((string)$schema['@context'], 'schema.org') === false) {
        return ['valid' => false, 'error' => 'Schema @context must point to schema.org.'];
    }
    if (empty($schema['@type']) || $schema['@type'] !== 'BlogPosting') {
        return ['valid' => false, 'error' => 'Schema @type must be BlogPosting.'];
    }
    if (empty($schema['headline'])) {
        return ['valid' => false, 'error' => 'Schema headline is missing.'];
    }
    if (empty($schema['datePublished']) || strtotime($schema['datePublished']) === false) {
        return ['valid' => false, 'error' => 'Schema datePublished is missing or not a valid date.'];
    }
    if (empty($schema['author']['name'])) {
        return ['valid' => false, 'error' => 'Schema author name is missing.'];
    }
    if (empty($schema['publisher']['name'])) {
        return ['valid' => false, 'error' => 'Schema publisher name is missing.'];
    }
    if (empty($schema['mainEntityOfPage']['@id'])) {
        return ['valid' => false, 'error' => 'Schema mainEntityOfPage is missing.'];
    }

    $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'error' => 'JSON encoding failed: ' . json_last_error_msg()];
    }

    return ['valid' => true, 'error' => null, 'json' => $json];
}

/**
 * Atomic file writer helper
 */
function atomicWriteFile(string $targetPath, string $content): bool {
    $dir = dirname($targetPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $tmpPath = $targetPath . '.tmp.' . bin2hex(random_bytes(6));
    if (file_put_contents($tmpPath, $content) === false) {
        return false;
    }
    if (!@rename($tmpPath, $targetPath)) {
        @unlink($targetPath);
        if (!@rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            return false;
        }
    }
    return true;
}

/**
 * Remove obsolete/stale generated artifacts for a blog post
 */
function removeBlogPostArtifacts(int $postId, ?string $slug = null): bool {
    $patterns = [
        CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'blogs' . DIRECTORY_SEPARATOR . "post-{$postId}-*.md",
        CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . "post-{$postId}-*.md",
        CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . "blog-{$postId}-*.json",
        CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . "blog-{$postId}-*.json"
    ];

    foreach ($patterns as $pat) {
        $matches = glob($pat);
        if ($matches) {
            foreach ($matches as $m) {
                if (file_exists($m)) {
                    @unlink($m);
                }
            }
        }
    }
    return true;
}

/**
 * Master Artifact Synchronizer for a Single Blog Post
 * Generates both Markdown (.md) and Schema.org BlogPosting (.json) atomically,
 * enforcing draft privacy staging and published discovery promotion.
 */
function syncBlogPostArtifacts(int $postId, ?string $oldSlug = null): array {
    $db = getDb();
    $stmt = $db->prepare(
        "SELECT p.*, c.name AS category_name 
         FROM blog_posts p 
         LEFT JOIN categories c ON p.category_id = c.id 
         WHERE p.id = :id"
    );
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch();

    if (!$post) {
        return ['success' => false, 'error' => "Blog post #{$postId} not found in database."];
    }

    $tags = getBlogPostTags($postId);
    $cleanSlug = $post['slug'] ?: slugify($post['title']);

    // 1. Generate both artifacts
    $markdownContent = generateBlogPostMarkdown($post, $tags);
    $schemaArray = generateBlogPostSchema($post, $tags);

    // 2. Validate both artifacts before committing any file changes
    $vMd = validateBlogPostMarkdown($markdownContent);
    if (!$vMd['valid']) {
        return ['success' => false, 'error' => "Markdown validation failed: " . $vMd['error']];
    }

    $vSchema = validateBlogPostSchema($schemaArray);
    if (!$vSchema['valid']) {
        return ['success' => false, 'error' => "Schema validation failed: " . $vSchema['error']];
    }
    $schemaJson = $vSchema['json'];

    // 3. Remove obsolete files if slug was changed
    if ($oldSlug && $oldSlug !== $cleanSlug) {
        removeBlogPostArtifacts($postId, $oldSlug);
    }

    $isPublished = ($post['status'] === 'published');

    if ($isPublished) {
        // --- PUBLISHED LIFECYCLE ---
        // Target public locations
        $mdDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'blogs';
        $mdFilename = sprintf('post-%d-%s.md', $postId, $cleanSlug);
        $mdPath = $mdDir . DIRECTORY_SEPARATOR . $mdFilename;
        $mdRelPath = 'blogs/' . $mdFilename;

        $schemaDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas';
        $schemaFilename = sprintf('blog-%d-%s.json', $postId, $cleanSlug);
        $schemaPath = $schemaDir . DIRECTORY_SEPARATOR . $schemaFilename;
        $schemaRelPath = 'schemas/' . $schemaFilename;

        // Atomic write public files
        if (!atomicWriteFile($mdPath, $markdownContent)) {
            return ['success' => false, 'error' => "Failed to write public Markdown file at {$mdPath}"];
        }
        if (!atomicWriteFile($schemaPath, $schemaJson)) {
            @unlink($mdPath); // Rollback markdown if schema fails
            return ['success' => false, 'error' => "Failed to write public Schema file at {$schemaPath}"];
        }

        // Clean up any previous staging artifacts
        @unlink(CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . "post-{$postId}-{$cleanSlug}.md");
        @unlink(CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . "blog-{$postId}-{$cleanSlug}.json");

        // Update database references
        $up = $db->prepare("UPDATE blog_posts SET markdown_file = :mf, schema_filename = :sf WHERE id = :id");
        $up->execute([':mf' => $mdRelPath, ':sf' => $schemaRelPath, ':id' => $postId]);

        return [
            'success'         => true,
            'status'          => 'published',
            'markdown_file'   => $mdRelPath,
            'markdown_path'   => $mdPath,
            'schema_filename' => $schemaRelPath,
            'schema_path'     => $schemaPath
        ];
    } else {
        // --- DRAFT LIFECYCLE ---
        // Target private staging locations
        $mdStagingDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'staging';
        $mdFilename = sprintf('post-%d-%s.md', $postId, $cleanSlug);
        $mdStagingPath = $mdStagingDir . DIRECTORY_SEPARATOR . $mdFilename;
        $mdRelPath = 'staging/' . $mdFilename;

        $schemaStagingDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'staging';
        $schemaFilename = sprintf('blog-%d-%s.json', $postId, $cleanSlug);
        $schemaStagingPath = $schemaStagingDir . DIRECTORY_SEPARATOR . $schemaFilename;
        $schemaRelPath = 'schemas/staging/' . $schemaFilename;

        // Atomic write staging files
        if (!atomicWriteFile($mdStagingPath, $markdownContent)) {
            return ['success' => false, 'error' => "Failed to write staging Markdown file at {$mdStagingPath}"];
        }
        if (!atomicWriteFile($schemaStagingPath, $schemaJson)) {
            @unlink($mdStagingPath);
            return ['success' => false, 'error' => "Failed to write staging Schema file at {$schemaStagingPath}"];
        }

        // Clean up any public artifacts (in case article was previously published)
        @unlink(CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'blogs' . DIRECTORY_SEPARATOR . "post-{$postId}-{$cleanSlug}.md");
        @unlink(CMS_ROOT . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . "blog-{$postId}-{$cleanSlug}.json");

        // Update database references
        $up = $db->prepare("UPDATE blog_posts SET markdown_file = :mf, schema_filename = :sf WHERE id = :id");
        $up->execute([':mf' => $mdRelPath, ':sf' => $schemaRelPath, ':id' => $postId]);

        return [
            'success'         => true,
            'status'          => 'draft',
            'markdown_file'   => $mdRelPath,
            'markdown_path'   => $mdStagingPath,
            'schema_filename' => $schemaRelPath,
            'schema_path'     => $schemaStagingPath
        ];
    }
}

/**
 * Backward compatibility wrapper for existing callers
 */
function syncBlogPostToMarkdown(int $postId): array {
    return syncBlogPostArtifacts($postId);
}

/**
 * Reconcile all existing blogs to Markdown and Schema artifacts
 */
function reconcileAllBlogsToMarkdown(): array {
    $db = getDb();
    $posts = $db->query("SELECT id, title FROM blog_posts ORDER BY id ASC")->fetchAll();
    $synced = 0;
    $errors = [];

    foreach ($posts as $p) {
        $res = syncBlogPostArtifacts((int)$p['id']);
        if ($res['success']) {
            $synced++;
        } else {
            $errors[] = "#{$p['id']}: " . ($res['error'] ?? 'Unknown error');
        }
    }

    if (function_exists('syncLlmsTxt')) {
        syncLlmsTxt();
    }

    if (function_exists('syncBlogsMd')) {
        syncBlogsMd();
    }

    return [
        'total'  => count($posts),
        'synced' => $synced,
        'errors' => $errors
    ];
}

function reconcileAllBlogArtifacts(): array {
    return reconcileAllBlogsToMarkdown();
}

/**
 * =========================================================================
 * BLOG TAGS, TAXONOMY & TABLE OF CONTENTS HELPERS
 * =========================================================================
 */

/**
 * Retrieve all tags assigned to a specific blog post
 */
function getPostTags(int $postId): array {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT t.id, t.name, t.slug 
        FROM tags t 
        INNER JOIN blog_post_tags bpt ON t.id = bpt.tag_id 
        WHERE bpt.post_id = :post_id 
        ORDER BY t.name ASC
    ");
    $stmt->execute([':post_id' => $postId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retrieve single tag by slug or name
 */
function getTagBySlug(string $slugOrName): ?array {
    $db = getDb();
    $clean = trim(ltrim($slugOrName, '#'));
    $slug = slugify($clean);

    $stmt = $db->prepare("SELECT * FROM tags WHERE slug = :slug OR name = :name LIMIT 1");
    $stmt->execute([':slug' => $slug, ':name' => $clean]);
    $tag = $stmt->fetch(PDO::FETCH_ASSOC);
    return $tag ?: null;
}

/**
 * Retrieve all tags with published post counts
 */
function getAllTags(): array {
    $db = getDb();
    $sql = "
        SELECT t.*, COUNT(DISTINCT p.id) AS post_count 
        FROM tags t 
        LEFT JOIN blog_post_tags bpt ON t.id = bpt.tag_id 
        LEFT JOIN blog_posts p ON bpt.post_id = p.id AND p.status = 'published' AND p.published_at <= NOW() 
        GROUP BY t.id 
        HAVING post_count > 0 
        ORDER BY t.name ASC
    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Synchronize tags for a blog post
 * Safely creates missing tags, removes unassigned pivot rows, and keeps keywords column in sync
 */
function syncPostTags(int $postId, $tagsInput): array {
    $db = getDb();

    if (is_string($tagsInput)) {
        $rawTags = array_map('trim', explode(',', $tagsInput));
    } elseif (is_array($tagsInput)) {
        $rawTags = array_map('trim', $tagsInput);
    } else {
        $rawTags = [];
    }

    $cleanTags = [];
    foreach ($rawTags as $t) {
        $clean = trim(ltrim($t, '#'));
        if ($clean !== '' && mb_strlen($clean) <= 100) {
            $cleanTags[$clean] = slugify($clean);
        }
    }

    if (empty($cleanTags)) {
        $stmtDel = $db->prepare("DELETE FROM blog_post_tags WHERE post_id = :post_id");
        $stmtDel->execute([':post_id' => $postId]);
        
        $stmtKw = $db->prepare("UPDATE blog_posts SET keywords = '' WHERE id = :post_id");
        $stmtKw->execute([':post_id' => $postId]);
        return [];
    }

    $assignedTags = [];
    $tagIds = [];

    foreach ($cleanTags as $tagName => $tagSlug) {
        // Find existing tag by slug
        $stmtFind = $db->prepare("SELECT id, name, slug FROM tags WHERE slug = :slug LIMIT 1");
        $stmtFind->execute([':slug' => $tagSlug]);
        $existing = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $tagId = (int)$existing['id'];
            $assignedTags[] = $existing;
        } else {
            $stmtIns = $db->prepare("INSERT INTO tags (name, slug) VALUES (:name, :slug)");
            $stmtIns->execute([':name' => $tagName, ':slug' => $tagSlug]);
            $tagId = (int)$db->lastInsertId();
            $assignedTags[] = ['id' => $tagId, 'name' => $tagName, 'slug' => $tagSlug];
        }
        $tagIds[] = $tagId;
    }

    // Sync pivot table: delete removed tags, insert new
    if (!empty($tagIds)) {
        $inClause = implode(',', array_fill(0, count($tagIds), '?'));
        $stmtDel = $db->prepare("DELETE FROM blog_post_tags WHERE post_id = ? AND tag_id NOT IN ($inClause)");
        $stmtDel->execute(array_merge([$postId], $tagIds));

        $stmtInsPivot = $db->prepare("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)");
        foreach ($tagIds as $tid) {
            $stmtInsPivot->execute([':post_id' => $postId, ':tag_id' => $tid]);
        }
    }

    // Backwards compatibility with blog_posts.keywords
    $keywordStr = implode(', ', array_column($assignedTags, 'name'));
    $stmtKw = $db->prepare("UPDATE blog_posts SET keywords = :kw WHERE id = :post_id");
    $stmtKw->execute([':kw' => $keywordStr, ':post_id' => $postId]);

    return $assignedTags;
}

/**
 * Dynamic Table of Contents Parser & Heading ID Injector
 * 
 * Inspects the rendered HTML for non-empty <h2> and <h3> tags.
 * Preserves existing valid unique IDs, generates slugified unique IDs for missing ones,
 * and nests <h3> children hierarchically under their preceding <h2>.
 */
function parseAndInjectTableOfContents(string $htmlContent): array {
    if (empty(trim($htmlContent))) {
        return ['html' => $htmlContent, 'toc' => [], 'count' => 0];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // Load as UTF-8 XML/HTML snippet
    $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $htmlContent . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//h2 | //h3');

    $toc = [];
    $usedIds = [];
    $currentH2Index = null;
    $count = 0;

    foreach ($nodes as $node) {
        $text = trim($node->textContent);
        if ($text === '') {
            continue; // Exclude empty headings
        }

        $tagName = strtolower($node->nodeName);
        $existingId = trim($node->getAttribute('id'));

        if (!empty($existingId)) {
            $id = $existingId;
        } else {
            $id = slugify($text);
        }

        // Guarantee ID uniqueness
        $baseId = $id;
        $suffix = 1;
        while (isset($usedIds[$id])) {
            $id = $baseId . '-' . $suffix;
            $suffix++;
        }
        $usedIds[$id] = true;
        $node->setAttribute('id', $id);
        $count++;

        $item = [
            'id'       => $id,
            'title'    => $text,
            'level'    => ($tagName === 'h2' ? 2 : 3),
            'children' => []
        ];

        if ($tagName === 'h2') {
            $toc[] = $item;
            $currentH2Index = count($toc) - 1;
        } else { // h3
            if ($currentH2Index !== null) {
                $toc[$currentH2Index]['children'][] = $item;
            } else {
                // If an H3 appears before any H2, attach as top-level item
                $toc[] = $item;
            }
        }
    }

    // Extract updated inner HTML from the wrapper div
    $container = $dom->getElementsByTagName('div')->item(0);
    $updatedHtml = '';
    if ($container) {
        foreach ($container->childNodes as $child) {
            $updatedHtml .= $dom->saveHTML($child);
        }
    } else {
        $updatedHtml = $htmlContent;
    }

    return [
        'html'  => $updatedHtml,
        'toc'   => $toc,
        'count' => $count
    ];
}

/**
 * Generate Dynamic Self-Referencing Canonical URL
 * 
 * Strips tracking query parameters (utm_*, gclid, fbclid, msclkid, ref, etc.)
 * Preserves whitelisted semantic parameters (tag, page) with normalized encoding.
 */
function generateCleanCanonicalUrl(string $basePath, array $preserveParams = []): string {
    $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $baseUrl = rtrim($canonicalBase, '/') . '/' . ltrim($basePath, '/');

    $trackingParams = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 
        'gclid', 'fbclid', 'msclkid', 'ref', 'source', 'mc_cid', 'mc_eid'
    ];
    
    $cleanQuery = [];
    foreach ($preserveParams as $key => $val) {
        if ($val === null || $val === '' || in_array(strtolower($key), $trackingParams, true)) {
            continue;
        }
        if ($key === 'page' && ((int)$val <= 1)) {
            continue; // Page 1 is default root canonical
        }
        $cleanQuery[$key] = $val;
    }

    if (!empty($cleanQuery)) {
        $parts = [];
        foreach ($cleanQuery as $k => $v) {
            $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $baseUrl .= '?' . implode('&', $parts);
    }

    return $baseUrl;
}



