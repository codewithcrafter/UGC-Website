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

    // Get total count
    $countSql = "SELECT COUNT(*) FROM blog_posts p LEFT JOIN categories c ON p.category_id = c.id {$whereSql}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Get rows sorted strictly by published date descending (newest first)
    $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
            FROM blog_posts p 
            LEFT JOIN categories c ON p.category_id = c.id 
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
    $canonicalUrl = !empty($post['canonical_url']) ? $post['canonical_url'] : (SITE_URL . '/blog.php?slug=' . urlencode($post['slug']));
    $featuredImgUrl = !empty($post['featured_image'])
        ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : SITE_URL . '/' . ltrim($post['featured_image'], '/'))
        : SITE_URL . '/images/blogs/blog-1.jpg';

    $metaDescription = !empty($post['meta_description']) ? $post['meta_description'] : ($post['excerpt'] ?? '');

    // Canonical Organization
    $organization = [
        "@type" => "Organization",
        "@id"   => SITE_URL . "/#organization",
        "name"  => "MY UGC VIDEO",
        "url"   => SITE_URL . "/",
        "logo"  => [
            "@type" => "ImageObject",
            "url"   => SITE_URL . "/images/hero/hero-creator.jpg"
        ],
        "description" => "Premier performance-driven UGC video production, creator management and content marketing studio."
    ];

    // BreadcrumbList
    $breadcrumbItems = [
        [
            "@type"    => "ListItem",
            "position" => 1,
            "name"     => "Home",
            "item"     => SITE_URL . "/"
        ],
        [
            "@type"    => "ListItem",
            "position" => 2,
            "name"     => "Blogs",
            "item"     => SITE_URL . "/blogs.php"
        ]
    ];

    $pos = 3;
    if (!empty($post['category_name']) && !empty($post['category_slug'])) {
        $breadcrumbItems[] = [
            "@type"    => "ListItem",
            "position" => $pos,
            "name"     => $post['category_name'],
            "item"     => SITE_URL . "/blogs.php?category=" . urlencode($post['category_slug'])
        ];
        $pos++;
    }

    $breadcrumbItems[] = [
        "@type"    => "ListItem",
        "position" => $pos,
        "name"     => $post['title'] ?? 'Untitled Article',
        "item"     => $canonicalUrl
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
            "@id"   => SITE_URL . "/#website",
            "name"  => "MY UGC VIDEO",
            "url"   => SITE_URL . "/"
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
            "@id" => SITE_URL . "/#organization"
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

    // Convert Links
    $text = preg_replace('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text);

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
 * Blog ↔ Markdown Automatic Synchronization Engine
 * =========================================================================
 */
function syncBlogPostToMarkdown(int $postId): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT p.*, c.name AS category_name FROM blog_posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = :id");
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch();

    if (!$post) {
        return ['success' => false, 'error' => "Blog post #{$postId} not found."];
    }

    if (!defined('MARKDOWN_DIR')) {
        define('MARKDOWN_DIR', CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown');
    }

    $blogDir = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'blogs';
    if (!is_dir($blogDir)) {
        @mkdir($blogDir, 0755, true);
    }

    $cleanSlug = $post['slug'] ?: slugify($post['title']);
    $filename = sprintf('post-%d-%s.md', $postId, $cleanSlug);
    $targetPath = $blogDir . DIRECTORY_SEPARATOR . $filename;

    // Convert HTML article body to clean Markdown
    $bodyMarkdown = htmlToMarkdown($post['content']);

    // Parse FAQs if present
    $faqs = [];
    if (!empty($post['faqs_json'])) {
        $decoded = json_decode($post['faqs_json'], true);
        if (is_array($decoded)) {
            $faqs = $decoded;
        }
    }

    // Build Frontmatter
    $frontmatter = "---\n";
    $frontmatter .= "id: " . (int)$post['id'] . "\n";
    $frontmatter .= "title: " . json_encode($post['title'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    $frontmatter .= "slug: " . json_encode($post['slug'], JSON_UNESCAPED_SLASHES) . "\n";
    $frontmatter .= "status: " . json_encode($post['status']) . "\n";
    $frontmatter .= "is_featured: " . ((int)$post['is_featured'] ? 1 : 0) . "\n";
    $frontmatter .= "category: " . json_encode($post['category_name'] ?? 'UGC Strategy', JSON_UNESCAPED_UNICODE) . "\n";
    $frontmatter .= "author: " . json_encode($post['author_name'], JSON_UNESCAPED_UNICODE) . "\n";
    $frontmatter .= "published_at: " . json_encode($post['published_at']) . "\n";
    $frontmatter .= "reading_time: " . json_encode($post['reading_time']) . "\n";
    $frontmatter .= "excerpt: " . json_encode($post['excerpt'], JSON_UNESCAPED_UNICODE) . "\n";
    if (!empty($post['meta_title'])) $frontmatter .= "meta_title: " . json_encode($post['meta_title'], JSON_UNESCAPED_UNICODE) . "\n";
    if (!empty($post['meta_description'])) $frontmatter .= "meta_description: " . json_encode($post['meta_description'], JSON_UNESCAPED_UNICODE) . "\n";
    $frontmatter .= "---\n\n";

    // Content Body
    $content = $frontmatter;
    $content .= "# " . $post['title'] . "\n\n";
    if (!empty($post['excerpt'])) {
        $content .= "> " . $post['excerpt'] . "\n\n";
    }
    $content .= $bodyMarkdown . "\n";

    // FAQs section in Markdown
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

    // Write file
    if (file_put_contents($targetPath, $content) === false) {
        return ['success' => false, 'error' => "Failed to write markdown file at: {$targetPath}"];
    }

    // Update blog_posts markdown_file record
    $relPath = 'blogs/' . $filename;
    $upStmt = $db->prepare("UPDATE blog_posts SET markdown_file = :mf WHERE id = :id");
    $upStmt->execute([':mf' => $relPath, ':id' => $postId]);

    return [
        'success'   => true,
        'filename'  => $filename,
        'filepath'  => $targetPath,
        'relpath'   => $relPath
    ];
}

/**
 * Reconcile all existing blogs to Markdown files
 */
function reconcileAllBlogsToMarkdown(): array {
    $db = getDb();
    $posts = $db->query("SELECT id, title FROM blog_posts ORDER BY id ASC")->fetchAll();
    $synced = 0;
    $errors = [];

    foreach ($posts as $p) {
        $res = syncBlogPostToMarkdown((int)$p['id']);
        if ($res['success']) {
            $synced++;
        } else {
            $errors[] = "#{$p['id']}: " . ($res['error'] ?? 'Unknown error');
        }
    }

    return [
        'total'  => count($posts),
        'synced' => $synced,
        'errors' => $errors
    ];
}


