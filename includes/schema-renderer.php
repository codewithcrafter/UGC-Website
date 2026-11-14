<?php
/**
 * MY UGC VIDEO — Centralized Schema Renderer & Manager Helper
 * 
 * Manages static page schemas, global blog templates, individual overrides,
 * server-side schema validation, duplicate prevention, and frontend JSON-LD rendering.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}
require_once CMS_ROOT . '/config/config.php';

/**
 * Supported Schema.org Types in the Manager
 */
function getSupportedSchemaTypes(): array {
    return [
        'Organization'        => 'Organization (Corporate & Brand Identity)',
        'WebPage'             => 'WebPage (Standard Webpage)',
        'VideoObject'         => 'VideoObject (Hero / Showcase Video)',
        'BreadcrumbList'      => 'BreadcrumbList (Hierarchical Navigation)',
        'FAQPage'             => 'FAQPage (Frequently Asked Questions)',
        'Review'              => 'Review (Customer Testimonial / Review)',
        'BlogPosting'         => 'BlogPosting (Blog Article & Thought Leadership)',
        'Article'             => 'Article (General News & Editorial)',
        'Custom'              => 'Custom Schema (Freeform Custom JSON-LD / Upload)'
    ];
}

/**
 * Baseline JSON-LD templates for supported Schema types
 */
function getDefaultSchemaTemplate(string $type): string {
    $baseUrl = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $templates = [
        'Organization' => [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'MY UGC VIDEO',
            'url' => $baseUrl . '/',
            'logo' => $baseUrl . '/images/hero/hero-creator.jpg',
            'description' => 'Premier performance-driven UGC video production studio and creator network.',
            'sameAs' => [
                'https://instagram.com/myugcvideo',
                'https://linkedin.com/company/myugcvideo'
            ]
        ],
        'ProfessionalService' => [
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => 'MY UGC VIDEO',
            'url' => $baseUrl . '/',
            'logo' => $baseUrl . '/images/hero/hero-ugc-production.jpg',
            'image' => $baseUrl . '/images/hero/hero-ugc-production.jpg',
            'description' => 'Premium UGC video production agency specializing in creator-led video advertising.',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Delhi',
                'addressRegion' => 'DL',
                'addressCountry' => 'IN'
            ],
            'priceRange' => '$$'
        ],
        'WebSite' => [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'MY UGC VIDEO',
            'url' => $baseUrl . '/',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $baseUrl . '/blogs.php?search={search_term_string}',
                'query-input' => 'required name=search_term_string'
            ]
        ],
        'WebPage' => [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'MY UGC VIDEO',
            'url' => $baseUrl . '/',
            'description' => 'Performance UGC video production, creator sourcing, and high-impact social advertising.'
        ],
        'VideoObject' => [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => 'Creator-Led Video Production — UGC Video Showcase',
            'description' => 'Watch our creator-led video production preview showcasing authentic 9:16 vertical UGC video content crafted for direct-to-consumer brand campaigns.',
            'thumbnailUrl' => [
                $baseUrl . '/images/hero/hero-ugc-production.jpg'
            ],
            'uploadDate' => '2026-01-01T00:00:00+05:30',
            'contentUrl' => $baseUrl . '/images/hero/hero-ugc-production.jpg',
            'embedUrl' => $baseUrl . '/#video-showcase'
        ],
        'Review' => [
            '@context' => 'https://schema.org',
            '@type' => 'Review',
            'itemReviewed' => [
                '@type' => 'Organization',
                'name' => 'MY UGC VIDEO'
            ],
            'author' => [
                '@type' => 'Person',
                'name' => 'Verified Client'
            ],
            'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => '5',
                'bestRating' => '5'
            ],
            'reviewBody' => 'Exceptional performance UGC video production delivered on-brief.'
        ],
        'AboutPage' => [
            '@context' => 'https://schema.org',
            '@type' => 'AboutPage',
            'name' => 'About MY UGC VIDEO',
            'url' => $baseUrl . '/about.html',
            'description' => 'Learn about our creator network and data-driven UGC video production philosophy.'
        ],
        'ContactPage' => [
            '@context' => 'https://schema.org',
            '@type' => 'ContactPage',
            'name' => 'Contact MY UGC VIDEO',
            'url' => $baseUrl . '/contact.html',
            'description' => 'Get in touch with our creative production team for bespoke UGC video campaigns.'
        ],
        'Service' => [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => 'UGC Video Production Service',
            'provider' => [
                '@type' => 'Organization',
                'name' => 'MY UGC VIDEO'
            ],
            'serviceType' => 'UGC Video Marketing & Creation',
            'areaServed' => 'Global',
            'description' => 'End-to-end user-generated content video creation, actor sourcing, scripting, and post-production.'
        ],
        'Article' => [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => 'Article Headline',
            'description' => 'Summary of editorial article.',
            'author' => [
                '@type' => 'Organization',
                'name' => 'MY UGC VIDEO Editorial Team'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'MY UGC VIDEO'
            ],
            'datePublished' => date('c')
        ],
        'BlogPosting' => [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => 'Blog Post Headline',
            'description' => 'Detailed summary of the blog post.',
            'image' => $baseUrl . '/images/blogs/blog-1.jpg',
            'author' => [
                '@type' => 'Person',
                'name' => 'Editorial Team',
                'jobTitle' => 'Content Creator'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'MY UGC VIDEO',
                'url' => $baseUrl . '/'
            ],
            'datePublished' => date('c'),
            'dateModified' => date('c')
        ],
        'BreadcrumbList' => [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => $baseUrl . '/'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Services',
                    'item' => $baseUrl . '/services'
                ]
            ]
        ],
        'CollectionPage' => [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => 'UGC Video Production Services',
            'url' => $baseUrl . '/services',
            'description' => 'Browse our full portfolio of UGC video production services for DTC and e-commerce brands.'
        ],
        'FAQPage' => [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'What is User Generated Content (UGC) video production?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'UGC video production creates authentic, creator-led product demonstrations, reviews, and unboxings specifically optimized for digital marketing campaigns and short-form social feeds.'
                    ]
                ]
            ]
        ],
        'Custom' => [
            '@context' => 'https://schema.org',
            '@type' => 'Thing',
            'name' => 'Custom Schema Entity',
            'description' => 'Custom structured data payload'
        ]
    ];

    $data = $templates[$type] ?? [
        '@context' => 'https://schema.org',
        '@type' => $type
    ];

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Discover all project static pages dynamically (including individual service pages)
 */
function getManagedStaticPages(): array {
    $pages = [
        'homepage'     => ['title' => 'Homepage', 'file' => 'index.html', 'category' => 'Core Pages'],
        'about'        => ['title' => 'About Us', 'file' => 'about.html', 'category' => 'Core Pages'],
        'services'     => ['title' => 'Services Directory', 'file' => 'services.html', 'category' => 'Core Pages'],
        'contact'      => ['title' => 'Contact Us', 'file' => 'contact.html', 'category' => 'Core Pages'],
        'blog-listing' => ['title' => 'Blog Listing', 'file' => 'blogs.php', 'category' => 'Core Pages']
    ];

    // Dynamically discover all service pages in services/ directory
    $servicesDir = CMS_ROOT . DIRECTORY_SEPARATOR . 'services';
    if (is_dir($servicesDir)) {
        $files = scandir($servicesDir);
        foreach ($files as $f) {
            if (str_ends_with(strtolower($f), '.html')) {
                $slug = basename($f, '.html');
                $cleanTitle = ucwords(str_replace(['-', '_'], ' ', str_replace('-company', '', $slug)));
                $pages['service-' . $slug] = [
                    'title'    => $cleanTitle . ' Service',
                    'file'     => 'services/' . $f,
                    'category' => 'Individual Service Pages',
                    'service_slug' => $slug
                ];
            }
        }
    }

    return $pages;
}

/**
 * Complete website manageable pages inventory:
 * Core Pages (5), Service Pages (9), and All Published Blog Articles (10).
 */
function getAllWebsiteManageablePages(): array {
    $staticPages = getManagedStaticPages();
    $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $pages = [];

    foreach ($staticPages as $key => $meta) {
        $canonicalPath = ($key === 'homepage') ? '/' : (($key === 'blog-listing') ? '/blogs' : '/' . cleanPublicPath($meta['file']));
        $pages[$key] = [
            'title'        => $meta['title'],
            'file'         => $meta['file'],
            'category'     => $meta['category'] ?? ($key === 'homepage' || $key === 'about' || $key === 'contact' || $key === 'services' || $key === 'blog-listing' ? 'Core Pages' : 'Individual Service Pages'),
            'url'          => $canonicalPath,
            'canonical_url'=> $canonicalBase . $canonicalPath,
            'is_static'    => true
        ];
    }

    // Add all blog posts from database
    try {
        $db = getDb();
        $stmt = $db->query("SELECT id, title, slug, status, schema_type, custom_schema, faqs_json, published_at, updated_at FROM blog_posts ORDER BY id DESC");
        while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $slug = $p['slug'] ?: (function_exists('slugify') ? slugify($p['title']) : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $p['title']), '-')));
            $key = 'blog-' . $slug;
            $pages[$key] = [
                'title'        => $p['title'],
                'file'         => 'blog.php?slug=' . $slug,
                'category'     => 'Blog Articles',
                'url'          => '/blog/' . $slug,
                'canonical_url'=> $canonicalBase . '/blog/' . $slug,
                'blog_slug'    => $slug,
                'post_id'      => (int)$p['id'],
                'post_status'  => $p['status'],
                'schema_type'  => $p['schema_type'] ?? 'recommended',
                'custom_schema'=> $p['custom_schema'] ?? '',
                'faqs_json'    => $p['faqs_json'] ?? '[]',
                'published_at' => $p['published_at'] ?? null,
                'updated_at'   => $p['updated_at'] ?? null,
                'is_static'    => false
            ];
        }
    } catch (Throwable $e) {
        error_log("Error fetching blog posts for manageable pages: " . $e->getMessage());
    }

    return $pages;
}

/**
 * Fetch a setting from schema_settings
 */
function getSchemaSetting(string $key, $default = null) {
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT `setting_value` FROM `schema_settings` WHERE `setting_key` = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

/**
 * Save a setting in schema_settings
 */
function setSchemaSetting(string $key, $value): bool {
    try {
        $db = getDb();
        $stmt = $db->prepare("INSERT INTO `schema_settings` (`setting_key`, `setting_value`) 
                              VALUES (:k, :v) 
                              ON DUPLICATE KEY UPDATE `setting_value` = :v2");
        return $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
    } catch (Throwable $e) {
        error_log("Schema Manager: Error saving setting {$key}: " . $e->getMessage());
        return false;
    }
}

/**
 * Strict Server-Side JSON-LD & Schema.org Validator
 */
function validateSchemaJsonLd(string $jsonString): array {
    $trimmed = trim($jsonString);
    if ($trimmed === '') {
        return [
            'valid' => false,
            'errors' => ['JSON payload cannot be empty.'],
            'warnings' => []
        ];
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'valid' => false,
            'errors' => ['JSON Syntax Error: ' . json_last_error_msg()],
            'warnings' => []
        ];
    }

    if (!is_array($decoded)) {
        return [
            'valid' => false,
            'errors' => ['Root element must be a valid JSON Object.'],
            'warnings' => []
        ];
    }

    $errors = [];
    $warnings = [];

    // Validate @context
    $context = $decoded['@context'] ?? '';
    if (empty($context)) {
        $errors[] = 'Missing standard Schema.org context property: "@context": "https://schema.org".';
    } elseif (!str_contains((string)$context, 'schema.org')) {
        $errors[] = 'Invalid @context property. Must declare https://schema.org.';
    }

    // Validate @type or @graph
    $type = $decoded['@type'] ?? '';
    $hasGraph = isset($decoded['@graph']) && is_array($decoded['@graph']);

    if (empty($type) && !$hasGraph) {
        $errors[] = 'Missing "@type" declaration or "@graph" array.';
    }

    // Checks for specific schema types
    if (!empty($type)) {
        $supportedTypes = array_keys(getSupportedSchemaTypes());
        if (!in_array($type, $supportedTypes)) {
            $warnings[] = "Schema type '{$type}' is not in the standard recommended list. Ensure it conforms to Schema.org standards.";
        }

        if ($type === 'BlogPosting' || $type === 'Article') {
            if (empty($decoded['headline'])) $errors[] = 'Missing required property "headline" for BlogPosting/Article.';
            if (empty($decoded['author'])) $warnings[] = 'Recommended property "author" is missing.';
            if (empty($decoded['publisher'])) $warnings[] = 'Recommended property "publisher" is missing.';
            if (empty($decoded['datePublished'])) $warnings[] = 'Recommended property "datePublished" is missing.';
        } elseif ($type === 'Organization' || $type === 'ProfessionalService') {
            if (empty($decoded['name'])) $errors[] = "Missing required property 'name' for {$type}.";
            if (empty($decoded['url'])) $warnings[] = 'Recommended property "url" is missing.';
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings,
        'data' => $decoded
    ];
}

/**
 * Fetch all published schemas for a given static page
 */
function getPublishedPageSchemas(string $pageKey): array {
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM `page_schemas` WHERE `page_key` = :pk AND `status` = 'published' ORDER BY `id` ASC");
        $stmt->execute([':pk' => $pageKey]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("Schema Manager: Error fetching page schemas: " . $e->getMessage());
        return [];
    }
}

/**
 * Resolve BlogPosting Schema for a blog post based on priority rules:
 * 1. If individual custom override is enabled and valid, use it.
 * 2. Otherwise, if automatic schema is enabled globally, use the active global template.
 * 3. If schema is disabled globally or for that blog, return null.
 * 4. Draft articles return null.
 */
function resolveBlogPostingSchema(array $post, array $faqs = []): ?array {
    // Critical: Drafts NEVER output public schema
    if (($post['status'] ?? 'draft') !== 'published') {
        return null;
    }

    // PRIORITY RULE 1: If Global Blog Schema is OFF, no blog BlogPosting schema is publicly output
    $autoEnabled = (string)getSchemaSetting('auto_blog_schema_enabled', '1');
    if ($autoEnabled !== '1') {
        return null;
    }

    $postId = (int)($post['id'] ?? 0);
    $db = getDb();

    // Check Individual Blog Schema Override
    if ($postId > 0) {
        try {
            $stmt = $db->prepare("SELECT * FROM `blog_schema_overrides` WHERE `post_id` = :id LIMIT 1");
            $stmt->execute([':id' => $postId]);
            $override = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($override) {
                // PRIORITY RULE 3: If explicitly disabled for this blog
                if ((int)$override['schema_disabled'] === 1) {
                    return null;
                }

                // PRIORITY RULE 2: If override is enabled and published
                if ((int)$override['override_enabled'] === 1) {
                    // Draft custom overrides must not be publicly exposed
                    if (($override['status'] ?? 'draft') !== 'published') {
                        return null;
                    }
                    if (!empty($override['custom_schema'])) {
                        $val = validateSchemaJsonLd($override['custom_schema']);
                        if ($val['valid']) {
                            return $val['data'];
                        }
                    }
                    return null;
                }
            }
        } catch (Throwable $e) {
            error_log("Schema Manager: Error checking blog override: " . $e->getMessage());
        }
    }

    // PRIORITY RULE 4: Inherit Active Global BlogPosting Template
    $globalStatus = (string)getSchemaSetting('global_template_status', 'published');
    if ($globalStatus !== 'published') {
        return null;
    }
    $globalTemplateJson = getSchemaSetting('global_blog_template');
    if (!empty($globalTemplateJson)) {
        $cleanSlug = $post['slug'] ?: slugify($post['title']);
        $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
        
        $canonicalUrl = (!empty($post['canonical_url']) && !str_contains($post['canonical_url'], 'example.com') && !str_ends_with($post['canonical_url'], '.html') && !str_contains($post['canonical_url'], 'blog.php'))
            ? $post['canonical_url']
            : $canonicalBase . '/blog/' . urlencode($cleanSlug);

        $featuredImg = !empty($post['featured_image'])
            ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : $canonicalBase . '/' . ltrim($post['featured_image'], '/'))
            : $canonicalBase . '/images/blogs/blog-1.jpg';

        $desc = !empty($post['meta_description']) ? trim($post['meta_description']) : (!empty($post['excerpt']) ? trim($post['excerpt']) : trim($post['title']));
        $author = !empty($post['author_name']) ? trim($post['author_name']) : 'MY UGC VIDEO Editorial Team';
        $role = !empty($post['author_role']) ? trim($post['author_role']) : 'UGC Creative Studio';
        $pubDate = !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c');
        $modDate = !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : $pubDate;

        $replacements = [
            '{{canonical_url}}'  => $canonicalUrl,
            '{{title}}'          => addcslashes($post['title'], '"\\/'),
            '{{description}}'    => addcslashes($desc, '"\\/'),
            '{{featured_image}}' => $featuredImg,
            '{{author_name}}'    => addcslashes($author, '"\\/'),
            '{{author_role}}'    => addcslashes($role, '"\\/'),
            '{{published_at}}'   => $pubDate,
            '{{updated_at}}'     => $modDate
        ];

        $compiled = str_replace(array_keys($replacements), array_values($replacements), $globalTemplateJson);
        $val = validateSchemaJsonLd($compiled);
        if ($val['valid']) {
            return $val['data'];
        }
    }

    // Fallback to standard BlogPosting schema function if template is unavailable
    if (function_exists('generateBlogPostSchema')) {
        return generateBlogPostSchema($post);
    }

    return null;
}

/**
 * Centralized Full Graph Generator for blog.php
 * Strictly prevents duplicate schema objects (Organization, Breadcrumbs, BlogPosting, FAQPage)
 */
function renderCentralizedBlogSchema(array $post, array $faqs = []): ?array {
    // Draft articles must NEVER expose public schema
    if (($post['status'] ?? 'draft') !== 'published') {
        return null;
    }

    // Priority: If post itself has an uploaded custom schema (Option B in Blog Create/Edit)
    if (($post['schema_type'] ?? '') === 'upload' && !empty($post['custom_schema'])) {
        $val = validateSchemaJsonLd($post['custom_schema']);
        if ($val['valid'] && !empty($val['data'])) {
            return $val['data'];
        }
    }

    $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $cleanSlug = $post['slug'] ?: slugify($post['title']);
    $canonical = $canonicalBase . '/blog/' . urlencode($cleanSlug);

    $graph = [];

    // Fetch any saved/custom page-level schemas from page_schemas table
    $customSchemasByType = [];
    $otherCustomSchemas = [];
    try {
        $db = getDb();
        $customStmt = $db->prepare("SELECT `schema_type`, `schema_data` FROM `page_schemas` WHERE (`page_key` = :pk1 OR `page_key` = :pk2) AND `status` = 'published'");
        $customStmt->execute([':pk1' => 'blog-' . $cleanSlug, ':pk2' => 'blog:' . $cleanSlug]);
        $customRows = $customStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($customRows as $crow) {
            $cdata = json_decode($crow['schema_data'], true);
            if ($cdata && is_array($cdata)) {
                if (isset($cdata['@graph']) && is_array($cdata['@graph'])) {
                    foreach ($cdata['@graph'] as $gItem) {
                        $t = $gItem['@type'] ?? '';
                        if (in_array($t, ['Organization', 'BreadcrumbList', 'BlogPosting'], true)) {
                            $customSchemasByType[$t] = $gItem;
                        } else {
                            $otherCustomSchemas[] = $gItem;
                        }
                    }
                } else {
                    unset($cdata['@context']);
                    $t = $crow['schema_type'] ?: ($cdata['@type'] ?? '');
                    if (in_array($t, ['Organization', 'BreadcrumbList', 'BlogPosting'], true)) {
                        $customSchemasByType[$t] = $cdata;
                    } else {
                        $otherCustomSchemas[] = $cdata;
                    }
                }
            }
        }
    } catch (Throwable $e) {}

    // 1. Organization Schema (Custom override from page_schemas or Canonical complete entity)
    if (!empty($customSchemasByType['Organization'])) {
        $graph[] = $customSchemasByType['Organization'];
    } else {
        $orgEntity = getCentralizedOrganizationSchema();
        unset($orgEntity['@context']);
        $graph[] = $orgEntity;
    }

    // 2. Breadcrumbs Schema (Custom override from page_schemas or Canonical breadcrumbs)
    if (!empty($customSchemasByType['BreadcrumbList'])) {
        $graph[] = $customSchemasByType['BreadcrumbList'];
    } else {
        $homeUrl = rtrim($canonicalBase, '/');
        $breadcrumbs = [
            ["@type" => "ListItem", "position" => 1, "name" => "Home", "item" => $homeUrl],
            ["@type" => "ListItem", "position" => 2, "name" => "Blogs", "item" => $canonicalBase . "/blogs"]
        ];
        $pos = 3;
        if (!empty($post['category_name'])) {
            $catSlug = function_exists('slugify') ? slugify($post['category_name']) : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $post['category_name']), '-'));
            $breadcrumbs[] = ["@type" => "ListItem", "position" => $pos, "name" => $post['category_name'], "item" => $canonicalBase . "/blogs?category=" . urlencode($catSlug)];
            $pos++;
        }
        $breadcrumbs[] = ["@type" => "ListItem", "position" => $pos, "name" => $post['title'], "item" => $canonical];

        $graph[] = [
            "@type" => "BreadcrumbList",
            "@id"   => $canonical . "#breadcrumb",
            "itemListElement" => $breadcrumbs
        ];
    }

    // 3. BlogPosting Schema (Custom override from page_schemas or Resolved via Centralized Manager Priority Rules)
    if (!empty($customSchemasByType['BlogPosting'])) {
        $graph[] = $customSchemasByType['BlogPosting'];
    } else {
        $blogPosting = resolveBlogPostingSchema($post, $faqs);
        if ($blogPosting) {
            $graph[] = $blogPosting;
        }
    }

    // 4. FAQPage Schema (Only if valid visible FAQs exist)
    $validFaqs = array_filter($faqs, fn($f) => !empty(trim($f['question'] ?? '')) && !empty(trim($f['answer'] ?? '')));
    if (!empty($validFaqs)) {
        $graph[] = [
            "@type" => "FAQPage",
            "@id"   => $canonical . "#faq",
            "mainEntity" => array_values(array_map(fn($f) => [
                "@type" => "Question",
                "name"  => trim($f['question']),
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text"  => trim($f['answer'])
                ]
            ], $validFaqs))
        ];
    }

    // 5. Append any Other Page-Level Schemas from page_schemas table
    foreach ($otherCustomSchemas as $other) {
        $graph[] = $other;
    }

    return [
        "@context" => "https://schema.org",
        "@graph"   => $graph
    ];
}

/**
 * Render all published schemas for a static page into HTML <script> tags
 */
function renderStaticPageSchemas(string $pageKey): string {
    $schemas = getPublishedPageSchemas($pageKey);
    if (empty($schemas)) {
        return '';
    }

    $html = "\n  <!-- Centralized Schema Manager JSON-LD -->\n";
    foreach ($schemas as $s) {
        $cleanJson = trim($s['schema_data']);
        if ($cleanJson !== '') {
            $html .= "  <script type=\"application/ld+json\">\n  " . $cleanJson . "\n  </script>\n";
        }
    }

    return $html;
}

/**
 * Synchronize published schemas for a static HTML page directly to the physical file
 */
function syncPublishedSchemasToStaticFile(string $pageKey): bool {
    $pages = getManagedStaticPages();
    if (!isset($pages[$pageKey])) {
        return false;
    }

    $relFile = $pages[$pageKey]['file'];
    $filesToSync = [];
    if ($pageKey === 'blog-listing') {
        $filesToSync[] = CMS_ROOT . DIRECTORY_SEPARATOR . 'blogs.html';
    } else {
        $filesToSync[] = CMS_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relFile);
    }

    $schemas = getPublishedPageSchemas($pageKey);
    $newSchemaBlock = '';
    if (!empty($schemas)) {
        $graphItems = [];
        $seenIds = [];

        foreach ($schemas as $s) {
            $parsed = json_decode(trim($s['schema_data']), true);
            if (!$parsed || !is_array($parsed)) continue;

            if (isset($parsed['@graph']) && is_array($parsed['@graph'])) {
                foreach ($parsed['@graph'] as $item) {
                    if (is_array($item)) {
                        $id = $item['@id'] ?? null;
                        if ($id && isset($seenIds[$id])) continue;
                        if ($id) $seenIds[$id] = true;
                        unset($item['@context']);
                        $graphItems[] = $item;
                    }
                }
            } else {
                $id = $parsed['@id'] ?? null;
                if ($id && isset($seenIds[$id])) continue;
                if ($id) $seenIds[$id] = true;
                unset($parsed['@context']);
                $graphItems[] = $parsed;
            }
        }

        if (!empty($graphItems)) {
            $compiledGraph = [
                "@context" => "https://schema.org",
                "@graph" => $graphItems
            ];
            $jsonFormatted = json_encode($compiledGraph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $newSchemaBlock = "  <!-- Centralized Schema Manager JSON-LD -->\n  <script type=\"application/ld+json\">\n" . $jsonFormatted . "\n  </script>\n";
        }
    }

    $allSuccess = true;
    foreach ($filesToSync as $absPath) {
        if (!file_exists($absPath) || !str_ends_with(strtolower($absPath), '.html')) {
            continue;
        }

        $content = file_get_contents($absPath);
        if ($content === false) {
            $allSuccess = false;
            continue;
        }

        // 1. Remove any existing Centralized Schema block
        $content = preg_replace('/<!-- Centralized Schema Manager JSON-LD -->\s*(?:<script type="application\/ld\+json">.*?<\/script>\s*)*/s', '', $content);

        // 2. Remove any legacy standalone JSON-LD scripts to avoid duplicates
        $content = preg_replace('/<script\s+type=[\'"]application\/ld\+json[\'"][^>]*>.*?<\/script>\s*/is', '', $content);

        // 3. Insert newly compiled clean schema block before </head>
        if (!empty($newSchemaBlock) && stripos($content, '</head>') !== false) {
            $content = str_ireplace('</head>', $newSchemaBlock . "</head>", $content);
        }

        if (file_put_contents($absPath, $content) === false) {
            $allSuccess = false;
        }
    }

    return $allSuccess;
}


/**
 * Append to includes/schema-renderer.php:
 * Complete Website-Wide Schema Audit, Generation, and Automation
 */

/**
 * Recommended schema types by page key per project specifications
 * Strictly implements the approved schema structure (Task 7):
 * - Homepage: Organization, WebPage, VideoObject
 * - About: Organization, BreadcrumbList, WebPage
 * - Services Listing: Organization, BreadcrumbList, WebPage, FAQPage
 * - Individual Service Pages: Organization, BreadcrumbList, FAQPage, WebPage (Review only if genuine data exists)
 * - Blogs Listing: BreadcrumbList, WebPage
 * - Individual Blog Pages: Organization, BreadcrumbList, BlogPosting
 * - Contact: BreadcrumbList, WebPage, Organization
 */
function getPageRecommendedSchemaTypes(string $pageKey): array {
    if ($pageKey === 'homepage') {
        return ['Organization', 'WebPage', 'VideoObject'];
    }
    if ($pageKey === 'about') {
        return ['Organization', 'BreadcrumbList', 'WebPage'];
    }
    if ($pageKey === 'services') {
        return ['Organization', 'BreadcrumbList', 'WebPage', 'FAQPage'];
    }
    if (str_starts_with($pageKey, 'service-')) {
        return ['Organization', 'BreadcrumbList', 'FAQPage', 'WebPage'];
    }
    if ($pageKey === 'blog-listing') {
        return ['BreadcrumbList', 'WebPage'];
    }
    if ($pageKey === 'contact') {
        return ['BreadcrumbList', 'WebPage', 'Organization'];
    }
    if (str_starts_with($pageKey, 'blog-') || str_starts_with($pageKey, 'blog:')) {
        return ['Organization', 'BreadcrumbList', 'BlogPosting'];
    }
    return ['WebPage'];
}

/**
 * Centralized Canonical Organization Entity
 * Stable @id: https://myugcvideo.in/#organization
 */
function getCentralizedOrganizationSchema(): array {
    $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $homeUrl = rtrim($canonicalBase, '/');
    return [
        "@context" => "https://schema.org",
        "@type" => "Organization",
        "@id" => $homeUrl . "/#organization",
        "name" => "MY UGC VIDEO",
        "url" => $homeUrl,
        "logo" => [
            "@type" => "ImageObject",
            "@id" => $homeUrl . "/#logo",
            "url" => $homeUrl . "/images/hero/hero-creator.jpg",
            "caption" => "MY UGC VIDEO Studio Logo"
        ],
        "image" => $homeUrl . "/images/hero/hero-ugc-production.jpg",
        "description" => "Premier performance-driven UGC video production studio and creator network connecting brands with authentic creator-led video content.",
        "email" => "hello@myugcvideo.in",
        "sameAs" => [
            "https://instagram.com",
            "https://linkedin.com"
        ],
        "contactPoint" => [
            [
                "@type" => "ContactPoint",
                "contactType" => "customer service",
                "email" => "hello@myugcvideo.in",
                "availableLanguage" => ["English", "Hindi"]
            ]
        ],
        "areaServed" => [
            [
                "@type" => "Country",
                "name" => "India"
            ]
        ]
    ];
}

/**
 * Helper to get referenced organization entity for secondary pages
 * Always returns the complete canonical entity to ensure 100% validator pass
 */
function getReferencedOrganizationSchema(): array {
    return getCentralizedOrganizationSchema();
}

/**
 * Extract visible FAQs from physical HTML file
 */
function extractFaqsFromHtmlContent(string $html): array {
    $faqs = [];
    if (preg_match_all('/<div[^>]*class=[\'"][^\'"]*faq-item[^\'"]*[\'"][^>]*>.*?<button[^>]*class=[\'"][^\'"]*faq-question[^\'"]*[\'"][^>]*>\s*<span>(.*?)<\/span>.*?<div[^>]*class=[\'"][^\'"]*faq-answer[^\'"]*[\'"][^>]*>\s*<p>(.*?)<\/p>/is', $html, $m)) {
        for ($i = 0; $i < count($m[1]); $i++) {
            $q = trim(html_entity_decode(strip_tags($m[1][$i]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $a = trim(html_entity_decode(strip_tags($m[2][$i]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($q && $a) {
                $faqs[] = ['question' => $q, 'answer' => $a];
            }
        }
    }
    return $faqs;
}

/**
 * Build automatic content-accurate Schema for any page & schema type
 */
function buildAutomaticPageSchema(string $pageKey, string $schemaType): ?array {
    $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $pages = getAllWebsiteManageablePages();
    if (!isset($pages[$pageKey])) {
        return null;
    }

    $pageMeta = $pages[$pageKey];
    $relFile = $pageMeta['file'] ?? '';
    $absPath = CMS_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relFile);
    $htmlContent = (!empty($relFile) && file_exists($absPath)) ? file_get_contents($absPath) : '';

    // Custom Schema
    if ($schemaType === 'Custom') {
        return [
            "@context" => "https://schema.org",
            "@type" => "Thing",
            "name" => $pageMeta['title'] . " Custom Schema",
            "url" => $pageMeta['canonical_url'] ?? ($canonicalBase . '/' . ltrim($pageMeta['url'] ?? '', '/'))
        ];
    }

    // 1. Organization
    if ($schemaType === 'Organization') {
        if ($pageKey === 'homepage') {
            return getCentralizedOrganizationSchema();
        }
        return getReferencedOrganizationSchema();
    }

    // 2. BreadcrumbList
    if ($schemaType === 'BreadcrumbList') {
        $homeUrl = rtrim($canonicalBase, '/');
        $crumbs = [
            ["@type" => "ListItem", "position" => 1, "name" => "Home", "item" => $homeUrl]
        ];
        if ($pageKey === 'about') {
            $crumbs[] = ["@type" => "ListItem", "position" => 2, "name" => "About Us", "item" => $canonicalBase . "/about"];
        } elseif ($pageKey === 'services') {
            $crumbs[] = ["@type" => "ListItem", "position" => 2, "name" => "Services", "item" => $canonicalBase . "/services"];
        } elseif (str_starts_with($pageKey, 'service-')) {
            $slug = $pageMeta['service_slug'] ?? str_replace('service-', '', $pageKey);
            $crumbs[] = ["@type" => "ListItem", "position" => 2, "name" => "Services", "item" => $canonicalBase . "/services"];
            $crumbs[] = ["@type" => "ListItem", "position" => 3, "name" => $pageMeta['title'], "item" => $canonicalBase . "/services/" . $slug];
        } elseif ($pageKey === 'blog-listing') {
            $crumbs[] = ["@type" => "ListItem", "position" => 2, "name" => "Blogs", "item" => $canonicalBase . "/blogs"];
        } elseif ($pageKey === 'contact') {
            $crumbs[] = ["@type" => "ListItem", "position" => 2, "name" => "Contact Us", "item" => $canonicalBase . "/contact"];
        } elseif (str_starts_with($pageKey, 'blog-') || str_starts_with($pageKey, 'blog:')) {
            $slug = $pageMeta['blog_slug'] ?? str_replace(['blog-', 'blog:'], '', $pageKey);
            $crumbs[] = ["@type" => "ListItem", "position" => 2, "name" => "Blogs", "item" => $canonicalBase . "/blogs"];
            $crumbs[] = ["@type" => "ListItem", "position" => 3, "name" => $pageMeta['title'], "item" => $canonicalBase . "/blog/" . $slug];
        }

        $lastItem = end($crumbs)['item'];
        return [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "@id" => $lastItem . "#breadcrumb",
            "itemListElement" => $crumbs
        ];
    }

    // BlogPosting for blog article
    if ($schemaType === 'BlogPosting' && (str_starts_with($pageKey, 'blog-') || str_starts_with($pageKey, 'blog:'))) {
        $slug = $pageMeta['blog_slug'] ?? str_replace(['blog-', 'blog:'], '', $pageKey);
        try {
            $db = getDb();
            $s = $db->prepare("SELECT * FROM blog_posts WHERE slug = :slug LIMIT 1");
            $s->execute([':slug' => $slug]);
            $p = $s->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                return resolveBlogPostingSchema($p);
            }
        } catch (Throwable $e) {}
    }

    // 3. WebPage (Standard page entity across Homepage, About, Services, Contact, Blog Listing, and Individual Services)
    if ($schemaType === 'WebPage') {
        $homeUrl = rtrim($canonicalBase, '/');
        if ($pageKey === 'homepage') {
            return [
                "@context" => "https://schema.org",
                "@type" => "WebPage",
                "@id" => $homeUrl . "/#webpage",
                "url" => $homeUrl . "/",
                "name" => "MY UGC VIDEO — Premier UGC Video Production Agency",
                "description" => "High-performing user-generated content video ads, product demonstrations, and creator-led marketing campaigns.",
                "publisher" => ["@id" => $homeUrl . "/#organization"],
                "about" => ["@id" => $homeUrl . "/#organization"]
            ];
        }
        if ($pageKey === 'about') {
            return [
                "@context" => "https://schema.org",
                "@type" => "WebPage",
                "@id" => $canonicalBase . "/about#webpage",
                "url" => $canonicalBase . "/about",
                "name" => "About MY UGC VIDEO",
                "description" => "Discover MY UGC VIDEO — a premier platform bridging ambitious brands and authentic content creators to craft engaging, high-performing creator-led video content.",
                "publisher" => ["@id" => $homeUrl . "/#organization"]
            ];
        }
        if ($pageKey === 'services') {
            return [
                "@context" => "https://schema.org",
                "@type" => "WebPage",
                "@id" => $canonicalBase . "/services#webpage",
                "url" => $canonicalBase . "/services",
                "name" => "UGC Video Production Services Directory",
                "description" => "Explore our comprehensive suite of user-generated content video production services, from performance video ads to unboxings and reviews.",
                "publisher" => ["@id" => $homeUrl . "/#organization"]
            ];
        }
        if ($pageKey === 'contact') {
            return [
                "@context" => "https://schema.org",
                "@type" => "WebPage",
                "@id" => $canonicalBase . "/contact#webpage",
                "url" => $canonicalBase . "/contact",
                "name" => "Contact MY UGC VIDEO",
                "description" => "Contact channels and project inquiry form for MY UGC VIDEO production agency.",
                "publisher" => ["@id" => $homeUrl . "/#organization"]
            ];
        }
        if ($pageKey === 'blog-listing') {
            return [
                "@context" => "https://schema.org",
                "@type" => "WebPage",
                "@id" => $canonicalBase . "/blogs#webpage",
                "url" => $canonicalBase . "/blogs",
                "name" => "The Creator Journal — UGC Insights, Trends & Strategy",
                "description" => "Explore in-depth articles on UGC video strategy, creator management, paid social performance, and e-commerce content marketing from MY UGC VIDEO.",
                "publisher" => ["@id" => $homeUrl . "/#organization"]
            ];
        }
        if (str_starts_with($pageKey, 'service-')) {
            $slug = $pageMeta['service_slug'] ?? str_replace('service-', '', $pageKey);
            $pageUrl = $canonicalBase . '/services/' . $slug;
            $desc = "Professional " . $pageMeta['title'] . " delivered with high-converting creator-led content.";
            if (preg_match('/<meta\s+name=[\'"]description[\'"]\s+content=[\'"]([^\'"]+)[\'"]/i', $htmlContent, $dm)) {
                $desc = trim($dm[1]);
            }
            return [
                "@context" => "https://schema.org",
                "@type" => "WebPage",
                "@id" => $pageUrl . "#webpage",
                "url" => $pageUrl,
                "name" => $pageMeta['title'] . " — MY UGC VIDEO",
                "description" => $desc,
                "publisher" => ["@id" => $homeUrl . "/#organization"]
            ];
        }
    }

    // 4. VideoObject (Homepage Hero Video Showcase)
    if ($schemaType === 'VideoObject') {
        if ($pageKey === 'homepage') {
            $homeUrl = rtrim($canonicalBase, '/');
            return [
                "@context" => "https://schema.org",
                "@type" => "VideoObject",
                "@id" => $homeUrl . "/#video-showcase",
                "name" => "Creator-Led Video Production — UGC Video Showcase",
                "description" => "Watch our creator-led video production preview showcasing authentic 9:16 vertical UGC video content crafted for direct-to-consumer brand campaigns.",
                "thumbnailUrl" => [
                    $homeUrl . "/images/hero/hero-ugc-production.jpg"
                ],
                "uploadDate" => "2026-01-01T00:00:00+05:30",
                "contentUrl" => $homeUrl . "/images/hero/hero-ugc-production.jpg",
                "embedUrl" => $homeUrl . "/#video-showcase"
            ];
        }
        return null;
    }

    // 5. FAQPage schema (Strictly generated only when visible FAQs exist on page)
    if ($schemaType === 'FAQPage') {
        $faqs = extractFaqsFromHtmlContent($htmlContent);
        if (empty($faqs)) {
            return null;
        }

        $pageUrl = $canonicalBase . ($pageKey === 'services' ? '/services' : '/services/' . ($pageMeta['service_slug'] ?? str_replace('service-', '', $pageKey)));
        return [
            "@context" => "https://schema.org",
            "@type" => "FAQPage",
            "@id" => $pageUrl . "#faq",
            "mainEntity" => array_map(fn($f) => [
                "@type" => "Question",
                "name" => $f['question'],
                "acceptedAnswer" => ["@type" => "Answer", "text" => $f['answer']]
            ], $faqs)
        ];
    }

    // 6. Review schema (Special Review condition: strictly genuine data only; if not present, return null)
    if ($schemaType === 'Review') {
        return null;
    }

    // All unapproved/legacy schema types return null to prevent generation
    return null;
}

/**
 * Execute Complete Website-Wide Schema Audit
 * Returns structured metrics and page-by-page audit results
 */
function runWebsiteWideSchemaAudit(): array {
    $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
    $db = getDb();
    $managedPages = getAllWebsiteManageablePages();

    // Fetch existing records in page_schemas
    $existingSchemas = [];
    try {
        $stmt = $db->query("SELECT * FROM page_schemas ORDER BY page_key ASC, id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingSchemas[$row['page_key']][] = $row;
        }
    } catch (Throwable $e) {
        $existingSchemas = [];
    }

    $pagesReport = [];
    $totalExisting = 0;
    $totalValid = 0;
    $totalInvalid = 0;
    $totalMissing = 0;
    $totalPublished = 0;
    $totalDraft = 0;
    $totalDuplicate = 0;

    foreach ($managedPages as $pKey => $pMeta) {
        $canonicalUrl = $pMeta['canonical_url'] ?? ($canonicalBase . ($pKey === 'homepage' ? '' : ($pKey === 'blog-listing' ? '/blogs' : '/' . cleanPublicPath($pMeta['file']))));
        $recommended = getPageRecommendedSchemaTypes($pKey);
        $configured = $existingSchemas[$pKey] ?? [];

        $configuredByType = [];
        $pageErrors = [];
        $seenTypes = [];

        foreach ($configured as $s) {
            $totalExisting++;
            $t = $s['schema_type'];
            if (isset($seenTypes[$t])) {
                $totalDuplicate++;
            }
            $seenTypes[$t] = true;

            $val = validateSchemaJsonLd($s['schema_data']);
            $isValid = $val['valid'];
            if ($isValid) {
                $totalValid++;
            } else {
                $totalInvalid++;
                $pageErrors[] = "{$t}: " . implode('; ', $val['errors']);
            }

            if ($s['status'] === 'published') {
                $totalPublished++;
            } else {
                $totalDraft++;
            }

            $configuredByType[$t] = [
                'id' => $s['id'],
                'name' => $s['schema_name'],
                'type' => $s['schema_type'],
                'status' => $s['status'],
                'valid' => $isValid,
                'errors' => $val['errors'] ?? [],
                'updated_at' => $s['updated_at'],
                'source' => 'Custom Page Schema'
            ];
        }

        // For Blog Articles: check if dynamic schemas or uploaded JSON are active
        $isBlog = !empty($pMeta['category']) && $pMeta['category'] === 'Blog Articles';
        if ($isBlog) {
            $isUploadMode = ($pMeta['schema_type'] ?? '') === 'upload' && !empty($pMeta['custom_schema']);

            if ($isUploadMode) {
                if (!isset($configuredByType['Custom']) && !isset($configuredByType['BlogPosting'])) {
                    $val = validateJsonLd($pMeta['custom_schema']);
                    $isValid = $val['valid'];
                    $totalExisting++;
                    if ($isValid) $totalValid++; else $totalInvalid++;
                    $totalPublished++;
                    $configuredByType['Custom (Uploaded JSON)'] = [
                        'id' => 'blog_' . ($pMeta['post_id'] ?? 0) . '_upload',
                        'name' => 'Uploaded Custom JSON-LD',
                        'type' => 'Custom',
                        'status' => 'published',
                        'valid' => $isValid,
                        'errors' => $val['errors'] ?? [],
                        'updated_at' => $pMeta['updated_at'] ?? date('Y-m-d H:i:s'),
                        'source' => 'Uploaded JSON File'
                    ];
                }
            } else {
                // Dynamic Recommended Schemas from Centralized Blog Schema
                $faqs = json_decode($pMeta['faqs_json'] ?? '[]', true) ?: [];
                $hasFaqs = false;
                foreach ($faqs as $f) {
                    if (!empty($f['question']) && !empty($f['answer'])) {
                        $hasFaqs = true;
                        break;
                    }
                }

                $dynTypes = ['Organization', 'BreadcrumbList', 'BlogPosting'];
                if ($hasFaqs) {
                    $dynTypes[] = 'FAQPage';
                }

                foreach ($dynTypes as $dt) {
                    if (!isset($configuredByType[$dt])) {
                        $totalExisting++;
                        $totalValid++;
                        $totalPublished++;
                        $configuredByType[$dt] = [
                            'id' => 'dyn_' . ($pMeta['post_id'] ?? 0) . '_' . strtolower($dt),
                            'name' => $pMeta['title'] . ' ' . $dt . ' (Dynamic)',
                            'type' => $dt,
                            'status' => 'published',
                            'valid' => true,
                            'errors' => [],
                            'updated_at' => $pMeta['updated_at'] ?? date('Y-m-d H:i:s'),
                            'source' => 'Dynamic Centralized Schema'
                        ];
                    }
                }
            }
        }

        $missingTypes = [];
        foreach ($recommended as $recType) {
            if ($isBlog && isset($configuredByType['Custom (Uploaded JSON)'])) {
                continue;
            }
            if (!isset($configuredByType[$recType])) {
                $missingTypes[] = $recType;
                $totalMissing++;
            }
        }

        // Determine Page Status
        $pageStatus = 'Already Correct';
        if (!empty($missingTypes)) {
            $pageStatus = 'Missing Recommended';
        } elseif (!empty($pageErrors)) {
            $pageStatus = 'Requires Fix';
        }

        $pagesReport[$pKey] = [
            'page_key' => $pKey,
            'title' => $pMeta['title'],
            'canonical_url' => $canonicalUrl,
            'category' => $pMeta['category'],
            'file' => $pMeta['file'],
            'recommended_types' => $recommended,
            'configured_schemas' => $configuredByType,
            'missing_types' => $missingTypes,
            'errors' => $pageErrors,
            'page_status' => $pageStatus,
            'has_drafts' => !empty(array_filter($configuredByType, fn($c) => $c['status'] === 'draft'))
        ];
    }

    return [
        'timestamp' => date('c'),
        'metrics' => [
            'total_pages' => count($managedPages),
            'existing_schemas' => $totalExisting,
            'valid_schemas' => $totalValid,
            'invalid_schemas' => $totalInvalid,
            'missing_schemas' => $totalMissing,
            'duplicate_schemas' => $totalDuplicate,
            'published_schemas' => $totalPublished,
            'draft_schemas' => $totalDraft
        ],
        'pages' => $pagesReport
    ];
}

/**
 * Automatically Create, Validate, Save, Publish and Sync all missing recommended schemas
 */
function autoCreateAndPublishAllMissingSchemas(): array {
    $db = getDb();
    $managedPages = getManagedStaticPages();
    $createdCount = 0;
    $updatedCount = 0;
    $errors = [];

    foreach ($managedPages as $pKey => $pMeta) {
        $recommended = getPageRecommendedSchemaTypes($pKey);

        // Remove obsolete / unapproved schema types for this page
        $recEscaped = implode("','", array_map('addslashes', $recommended));
        $delStmt = $db->prepare("DELETE FROM page_schemas WHERE page_key = :pk AND schema_type NOT IN ('$recEscaped')");
        $delStmt->execute([':pk' => $pKey]);

        foreach ($recommended as $recType) {
            // Check if already exists in DB
            $stmt = $db->prepare("SELECT id, status FROM page_schemas WHERE page_key = :pk AND schema_type = :st LIMIT 1");
            $stmt->execute([':pk' => $pKey, ':st' => $recType]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate Schema JSON
            $schemaData = buildAutomaticPageSchema($pKey, $recType);
            if (!$schemaData) {
                continue;
            }

            $jsonStr = json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $val = validateSchemaJsonLd($jsonStr);
            if (!$val['valid']) {
                $errors[] = "Failed validation for {$pKey} > {$recType}: " . implode('; ', $val['errors']);
                continue;
            }

            $pageTitle = $pMeta['title'] ?? 'Page';
            $schemaName = $pageTitle . ' ' . $recType . ' Schema';
            if ($recType === 'Organization' && $pKey !== 'homepage') {
                $schemaName = 'Referenced Organization';
            }

            if ($existing) {
                // Update and ensure published
                $uStmt = $db->prepare("UPDATE page_schemas SET page_title = :pt, schema_name = :sn, schema_data = :sd, status = 'published', updated_at = NOW() WHERE id = :id");
                $uStmt->execute([
                    ':pt' => $pageTitle,
                    ':sn' => $schemaName,
                    ':sd' => $jsonStr,
                    ':id' => $existing['id']
                ]);
                $updatedCount++;
            } else {
                // Insert new published schema
                $iStmt = $db->prepare("INSERT INTO page_schemas (page_key, page_title, schema_name, schema_type, schema_data, status, created_at, updated_at) 
                                       VALUES (:pk, :pt, :sn, :st, :sd, 'published', NOW(), NOW())");
                $iStmt->execute([
                    ':pk' => $pKey,
                    ':pt' => $pageTitle,
                    ':sn' => $schemaName,
                    ':st' => $recType,
                    ':sd' => $jsonStr
                ]);
                $createdCount++;
            }
        }

        // Sync to physical file
        syncPublishedSchemasToStaticFile($pKey);
    }

    // Also sync blogs.html
    syncPublishedSchemasToStaticFile('blog-listing');
    $blogsHtmlPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'blogs.html';
    if (file_exists($blogsHtmlPath)) {
        $schemas = getPublishedPageSchemas('blog-listing');
        if (!empty($schemas)) {
            $newSchemaBlock = "  <!-- Centralized Schema Manager JSON-LD -->\n";
            foreach ($schemas as $s) {
                $json = trim($s['schema_data']);
                if ($json !== '') {
                    $newSchemaBlock .= "  <script type=\"application/ld+json\">\n  " . $json . "\n  </script>\n";
                }
            }
            $c = file_get_contents($blogsHtmlPath);
            if (preg_match('/<!-- Centralized Schema Manager JSON-LD -->\s*(<script type="application\/ld\+json">.*?<\/script>\s*)+/s', $c)) {
                $c = preg_replace('/<!-- Centralized Schema Manager JSON-LD -->\s*(<script type="application\/ld\+json">.*?<\/script>\s*)+/s', $newSchemaBlock, $c, 1);
            } elseif (preg_match('/(<script type="application\/ld\+json">.*?<\/script>)/s', $c)) {
                $c = preg_replace('/(<script type="application\/ld\+json">.*?<\/script>)/s', $newSchemaBlock, $c, 1);
            }
            file_put_contents($blogsHtmlPath, $c);
        }
    }

    return [
        'success' => empty($errors),
        'created' => $createdCount,
        'updated' => $updatedCount,
        'errors' => $errors
    ];
}
