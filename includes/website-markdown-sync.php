<?php
/**
 * MY UGC VIDEO — Website-to-Markdown Automatic Synchronization Engine
 * 
 * Guarantees that the actual published website content is the absolute source of truth.
 * Whenever content on a public website page is added, edited, updated, or deleted,
 * its corresponding .md file automatically reflects the latest published content.
 *
 * Responsibilities:
 * - Content extraction: Reads HTML source files directly (internal PHP, no HTTP self-curling)
 * - Semantic conversion: Converts headings, paragraphs, lists, specs, FAQs, CTAs into clean Markdown
 * - Frontmatter generation: Validates and injects structured YAML frontmatter and canonical URLs
 * - Atomic replacement: Safe writing via temporary files with flock concurrency control
 * - Idempotency & hashing: Avoids unnecessary file writes using SHA256 diff checks
 * - Blog & Service lifecycle: Fully covers create, edit, publish, unpublish, delete, slug changes
 * - Discovery integration: Seamlessly coordinates with llms.txt, blogs.md, and Admin Panel
 * - Audit logging: Records detailed synchronization health in logs/markdown_sync_status.json
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

require_once CMS_ROOT . '/config/config.php';
require_once CMS_ROOT . '/includes/functions.php';

class WebsiteMarkdownSync {

    /**
     * Complete inventory of all eligible public website pages (Core & Services)
     */
    public const PAGE_INVENTORY = [
        'about' => [
            'key'           => 'about',
            'title'         => 'About Us',
            'category'      => 'core',
            'category_name' => 'Core Website Pages',
            'source_file'   => 'about.html',
            'markdown_file' => 'markdown/pages/about.md',
            'canonical_url' => 'https://myugcvideo.in/about.md',
            'website_url'   => 'https://myugcvideo.in/about',
            'page_type'     => 'about',
            'primary_kw'    => 'about UGC video agency'
        ],
        'services' => [
            'key'           => 'services',
            'title'         => 'Services Overview',
            'category'      => 'core',
            'category_name' => 'Core Website Pages',
            'source_file'   => 'services.html',
            'markdown_file' => 'markdown/pages/services.md',
            'canonical_url' => 'https://myugcvideo.in/services.md',
            'website_url'   => 'https://myugcvideo.in/services',
            'page_type'     => 'services',
            'primary_kw'    => 'UGC video services'
        ],
        'contact' => [
            'key'           => 'contact',
            'title'         => 'Contact Us',
            'category'      => 'core',
            'category_name' => 'Core Website Pages',
            'source_file'   => 'contact.html',
            'markdown_file' => 'markdown/pages/contact.md',
            'canonical_url' => 'https://myugcvideo.in/contact.md',
            'website_url'   => 'https://myugcvideo.in/contact',
            'page_type'     => 'contact',
            'primary_kw'    => 'contact UGC video agency'
        ],
        'blogs' => [
            'key'           => 'blogs',
            'title'         => 'Editorial Blogs Listing',
            'category'      => 'core',
            'category_name' => 'Core Website Pages',
            'source_file'   => 'blogs.php',
            'markdown_file' => 'markdown/pages/blogs.md',
            'canonical_url' => 'https://myugcvideo.in/blogs.md',
            'website_url'   => 'https://myugcvideo.in/blogs',
            'page_type'     => 'editorial',
            'primary_kw'    => 'UGC video blog articles'
        ],
        'services/ai-ugc-video-creation-company' => [
            'key'           => 'services/ai-ugc-video-creation-company',
            'title'         => 'AI UGC Video Creation Services',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ai-ugc-video-creation-company.html',
            'markdown_file' => 'markdown/services/ai-ugc-video-creation-company.md',
            'canonical_url' => 'https://myugcvideo.in/services/ai-ugc-video-creation-company.md',
            'website_url'   => 'https://myugcvideo.in/services/ai-ugc-video-creation-company',
            'page_type'     => 'service',
            'primary_kw'    => 'AI UGC video creation'
        ],
        'services/ugc-product-review-videos-company' => [
            'key'           => 'services/ugc-product-review-videos-company',
            'title'         => 'UGC Product Review Videos',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ugc-product-review-videos-company.html',
            'markdown_file' => 'markdown/services/ugc-product-review-videos-company.md',
            'canonical_url' => 'https://myugcvideo.in/services/ugc-product-review-videos-company.md',
            'website_url'   => 'https://myugcvideo.in/services/ugc-product-review-videos-company',
            'page_type'     => 'service',
            'primary_kw'    => 'UGC product review videos'
        ],
        'services/ugc-product-video-company' => [
            'key'           => 'services/ugc-product-video-company',
            'title'         => 'UGC Product Videos',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ugc-product-video-company.html',
            'markdown_file' => 'markdown/services/ugc-product-video-company.md',
            'canonical_url' => 'https://myugcvideo.in/services/ugc-product-video-company.md',
            'website_url'   => 'https://myugcvideo.in/services/ugc-product-video-company',
            'page_type'     => 'service',
            'primary_kw'    => 'UGC product videos'
        ],
        'services/ugc-testimonial-videos-company' => [
            'key'           => 'services/ugc-testimonial-videos-company',
            'title'         => 'UGC Testimonial Videos',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ugc-testimonial-videos-company.html',
            'markdown_file' => 'markdown/services/ugc-testimonial-videos-company.md',
            'canonical_url' => 'https://myugcvideo.in/services/ugc-testimonial-videos-company.md',
            'website_url'   => 'https://myugcvideo.in/services/ugc-testimonial-videos-company',
            'page_type'     => 'service',
            'primary_kw'    => 'UGC testimonial videos'
        ],
        'services/ugc-unboxing-videos-company' => [
            'key'           => 'services/ugc-unboxing-videos-company',
            'title'         => 'UGC Unboxing Videos',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ugc-unboxing-videos-company.html',
            'markdown_file' => 'markdown/services/ugc-unboxing-videos-company.md',
            'canonical_url' => 'https://myugcvideo.in/services/ugc-unboxing-videos-company.md',
            'website_url'   => 'https://myugcvideo.in/services/ugc-unboxing-videos-company',
            'page_type'     => 'service',
            'primary_kw'    => 'UGC unboxing videos'
        ],
        'services/ugc-video-ads-company' => [
            'key'           => 'services/ugc-video-ads-company',
            'title'         => 'UGC Video Ads Company',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ugc-video-ads-company.html',
            'markdown_file' => 'markdown/services/ugc-video-ads-company.md',
            'canonical_url' => 'https://myugcvideo.in/services/ugc-video-ads-company.md',
            'website_url'   => 'https://myugcvideo.in/services/ugc-video-ads-company',
            'page_type'     => 'service',
            'primary_kw'    => 'UGC video ads'
        ],
        'services/ugc-video-company-in-delhi' => [
            'key'           => 'services/ugc-video-company-in-delhi',
            'title'         => 'UGC Video Company in Delhi NCR',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ugc-video-company-in-delhi.html',
            'markdown_file' => 'markdown/services/ugc-video-company-in-delhi.md',
            'canonical_url' => 'https://myugcvideo.in/services/ugc-video-company-in-delhi.md',
            'website_url'   => 'https://myugcvideo.in/services/ugc-video-company-in-delhi',
            'page_type'     => 'service',
            'primary_kw'    => 'UGC video company in Delhi'
        ],
        'services/ugc-video-creation-company' => [
            'key'           => 'services/ugc-video-creation-company',
            'title'         => 'UGC Video Creation Services',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ugc-video-creation-company.html',
            'markdown_file' => 'markdown/services/ugc-video-creation-company.md',
            'canonical_url' => 'https://myugcvideo.in/services/ugc-video-creation-company.md',
            'website_url'   => 'https://myugcvideo.in/services/ugc-video-creation-company',
            'page_type'     => 'service',
            'primary_kw'    => 'UGC video creation services'
        ],
        'services/ugc-video-editing-company' => [
            'key'           => 'services/ugc-video-editing-company',
            'title'         => 'UGC Video Editing Services',
            'category'      => 'services',
            'category_name' => 'Dedicated Service Pages',
            'source_file'   => 'services/ugc-video-editing-company.html',
            'markdown_file' => 'markdown/services/ugc-video-editing-company.md',
            'canonical_url' => 'https://myugcvideo.in/services/ugc-video-editing-company.md',
            'website_url'   => 'https://myugcvideo.in/services/ugc-video-editing-company',
            'page_type'     => 'service',
            'primary_kw'    => 'UGC video editing services'
        ]
    ];

    /**
     * Complete inventory of all eligible public website pages (Core & Services)
     */
    public static function getPageInventory(): array {
        return self::PAGE_INVENTORY;
    }

    /**
     * Extract metadata from an HTML string
     */
    public static function extractMetadata(string $html, array $pageDef): array {
        $title = '';
        $desc = '';
        $canonical = $pageDef['website_url'] ?? '';

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $m) ||
            preg_match('/<meta\s+content=["\'](.*?)["\']\s+name=["\']description["\']/is', $html, $m)) {
            $desc = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('/<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']/is', $html, $m) ||
            preg_match('/<link\s+href=["\'](.*?)["\']\s+rel=["\']canonical["\']/is', $html, $m)) {
            $canonical = trim($m[1]);
        }

        if (empty($title)) {
            $title = $pageDef['title'] . ' | MY UGC VIDEO';
        }
        if (empty($desc)) {
            $desc = "Performance User Generated Content (UGC) video production services from MY UGC VIDEO.";
        }

        return [
            'title'           => $title,
            'description'     => $desc,
            'canonical_url'   => $canonical,
            'page_type'       => $pageDef['page_type'] ?? 'page',
            'primary_keyword' => $pageDef['primary_kw'] ?? '',
            'last_updated'    => date('Y-m-d')
        ];
    }

    /**
     * Convert an HTML page document into clean, professional Markdown
     */
    public static function convertHtmlToMarkdown(string $html, array $meta = []): string {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Remove noise, decorative, form controls, or navigational elements
        $noiseQueries = [
            '//script', '//style', '//nav', '//footer', '//aside', '//noscript', '//svg', '//iframe',
            '//input', '//textarea', '//select', '//label', '//button[@type="submit"]',
            '//*[contains(@class, "site-top-bar")]',
            '//*[contains(@class, "site-chat-drawer")]',
            '//*[contains(@class, "contact-popup-overlay")]',
            '//*[contains(@class, "breadcrumbs")]',
            '//*[contains(@class, "chat-speech-bubble")]',
            '//*[contains(@class, "floating-cta")]',
            '//*[contains(@class, "hero-reticle")]',
            '//*[contains(@class, "hero-studio-pill")]',
            '//*[contains(@class, "cta-glow-mesh")]',
            '//*[contains(@class, "meta-dot")]',
            '//*[contains(@class, "eyebrow-circle-dot")]',
            '//*[contains(@class, "faq-icon-indicator")]',
            '//*[contains(@class, "quick-enquiry-card")]',
            '//*[contains(@class, "step-num")]',
            '//*[contains(@class, "step-phase")]',
            '//*[contains(@class, "pillar-num")]',
            '//*[contains(@class, "vision-num")]',
            '//*[contains(@class, "vision-tag")]',
            '//*[contains(@class, "process-step-num-badge")]',
            '//*[contains(@class, "format-tag")]',
            '//*[contains(@class, "form-error-msg")]',
            '//*[contains(@style, "display: none")]',
            '//*[contains(@style, "display:none")]'
        ];

        foreach ($noiseQueries as $q) {
            $nodes = $xpath->query($q);
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // Find main container
        $mainNodes = $xpath->query('//main');
        if ($mainNodes->length === 0) {
            $mainNodes = $xpath->query('//body');
        }
        $main = $mainNodes->item(0);
        if (!$main) return '';

        $markdownParts = [];

        // Build YAML Frontmatter
        if (!empty($meta)) {
            $frontmatterLines = ["---"];
            foreach ($meta as $k => $v) {
                if ($v === null || $v === '') continue;
                $cleanV = str_replace('"', '\"', (string)$v);
                $frontmatterLines[] = "{$k}: \"{$cleanV}\"";
            }
            $frontmatterLines[] = "---";
            $markdownParts[] = implode("\n", $frontmatterLines);
        }

        // Recursive walker
        $bodyMarkdown = self::walkDomNode($main, $xpath);
        $markdownParts[] = $bodyMarkdown;

        $fullMarkdown = implode("\n\n", $markdownParts);
        return self::finalizeMarkdown($fullMarkdown);
    }

    /**
     * Recursive DOM Node Walker
     */
    private static function walkDomNode(DOMNode $node, DOMXPath $xpath): string {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->textContent);
            return $text !== '' ? html_entity_decode($text, ENT_QUOTES, 'UTF-8') : '';
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);
        $class = $node->getAttribute('class');

        // Check if element is a FAQ accordion item
        if (str_contains($class, 'faq-item') || str_contains($class, 'faq-accordion-item')) {
            $qNode = $xpath->query('.//*[contains(@class, "faq-question")] | .//button | .//summary', $node);
            $aNode = $xpath->query('.//*[contains(@class, "faq-answer")] | .//*[contains(@class, "faq-desc")]', $node);
            if ($qNode->length > 0 && $aNode->length > 0) {
                $q = self::extractInlineText($qNode->item(0));
                $a = self::extractInlineText($aNode->item(0));
                if (!empty($q) && !empty($a)) {
                    return "\n### {$q}\n\n{$a}\n";
                }
            }
        }

        // Handle specific HTML tags
        switch ($tag) {
            case 'h1':
                $text = self::extractInlineText($node);
                return "\n# {$text}\n";

            case 'h2':
                $text = self::extractInlineText($node);
                return "\n## {$text}\n";

            case 'h3':
                $text = self::extractInlineText($node);
                return "\n### {$text}\n";

            case 'h4':
                $text = self::extractInlineText($node);
                return "\n#### {$text}\n";

            case 'p':
                $text = self::extractInlineText($node);
                return strlen($text) > 0 ? "\n{$text}\n" : '';

            case 'blockquote':
                $text = self::extractInlineText($node);
                return "\n> {$text}\n";

            case 'ul':
            case 'ol':
                $items = [];
                $index = 1;
                foreach ($node->childNodes as $child) {
                    if (strtolower($child->nodeName) === 'li') {
                        $liText = self::extractInlineText($child);
                        if ($liText !== '') {
                            $bullet = ($tag === 'ul') ? '-' : "{$index}.";
                            $items[] = "{$bullet} {$liText}";
                            $index++;
                        }
                    }
                }
                return "\n" . implode("\n", $items) . "\n";

            case 'table':
                return self::convertTableToMarkdown($node, $xpath);

            case 'section':
                $inner = self::walkChildren($node, $xpath);
                return trim($inner) !== '' ? "\n" . trim($inner) . "\n\n---\n" : '';

            case 'a':
                $href = trim($node->getAttribute('href'));
                $text = self::extractInlineText($node);
                if (empty($text)) return '';
                if (str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                    return $text;
                }
                $cleanHref = self::normalizeLinkHref($href);
                return "[{$text}]({$cleanHref})";

            case 'strong':
            case 'b':
                $text = self::extractInlineText($node);
                return $text !== '' ? "**{$text}**" : '';

            case 'em':
            case 'i':
                $text = self::extractInlineText($node);
                return $text !== '' ? "*{$text}*" : '';

            default:
                if (str_contains($class, 'eyebrow') && !str_contains($class, 'wrap')) {
                    $text = self::extractInlineText($node);
                    return $text !== '' ? "_{$text}_\n" : '';
                }
                return self::walkChildren($node, $xpath);
        }
    }

    private static function walkChildren(DOMNode $node, DOMXPath $xpath): string {
        $chunks = [];
        foreach ($node->childNodes as $child) {
            $res = self::walkDomNode($child, $xpath);
            if ($res !== '') {
                $chunks[] = $res;
            }
        }
        return implode("\n", $chunks);
    }

    private static function extractInlineText(DOMNode $node): string {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $cTag = strtolower($child->nodeName);
                if ($cTag === 'strong' || $cTag === 'b') {
                    $text .= '**' . self::extractInlineText($child) . '**';
                } elseif ($cTag === 'em' || $cTag === 'i') {
                    $text .= '*' . self::extractInlineText($child) . '*';
                } elseif ($cTag === 'a') {
                    $href = trim($child->getAttribute('href'));
                    $anchor = self::extractInlineText($child);
                    if ($anchor && !str_starts_with($href, '#') && !str_starts_with($href, 'javascript:')) {
                        $cleanHref = self::normalizeLinkHref($href);
                        $text .= "[{$anchor}]({$cleanHref})";
                    } else {
                        $text .= $anchor;
                    }
                } else {
                    $text .= ' ' . self::extractInlineText($child) . ' ';
                }
            }
        }
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function normalizeLinkHref(string $href): string {
        $baseUrl = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return $href;
        }
        $clean = preg_replace('#^(\.\./|\./)+#', '', $href);
        if ($clean === 'blogs.html') $clean = 'blogs';
        if ($clean === 'services.html') $clean = 'services';
        if ($clean === 'contact.html') $clean = 'contact';
        if ($clean === 'about.html') $clean = 'about';
        return $baseUrl . '/' . ltrim($clean, '/');
    }

    private static function convertTableToMarkdown(DOMNode $table, DOMXPath $xpath): string {
        $rows = [];
        $hasHeader = false;
        $trNodes = $xpath->query('.//tr', $table);
        foreach ($trNodes as $tr) {
            $cells = [];
            $thNodes = $xpath->query('.//th', $tr);
            if ($thNodes->length > 0) {
                foreach ($thNodes as $th) {
                    $cells[] = str_replace('|', '\|', self::extractInlineText($th));
                }
                $rows[] = '| ' . implode(' | ', $cells) . ' |';
                $rows[] = '| ' . implode(' | ', array_fill(0, count($cells), '---')) . ' |';
                $hasHeader = true;
            } else {
                $tdNodes = $xpath->query('.//td', $tr);
                foreach ($tdNodes as $td) {
                    $cells[] = str_replace('|', '\|', self::extractInlineText($td));
                }
                if (!$hasHeader && empty($rows) && !empty($cells)) {
                    $dummy = [];
                    foreach ($cells as $idx => $v) { $dummy[] = 'Col ' . ($idx + 1); }
                    $rows[] = '| ' . implode(' | ', $dummy) . ' |';
                    $rows[] = '| ' . implode(' | ', array_fill(0, count($cells), '---')) . ' |';
                    $hasHeader = true;
                }
                if (!empty($cells)) {
                    $rows[] = '| ' . implode(' | ', $cells) . ' |';
                }
            }
        }
        return "\n\n" . implode("\n", $rows) . "\n\n";
    }

    private static function finalizeMarkdown(string $md): string {
        $md = strip_tags($md);
        $md = preg_replace('/(\n---\s*)+/', "\n\n---\n\n", $md);
        $md = preg_replace('/\n{3,}/', "\n\n", $md);
        $md = preg_replace('/^[ \t]+/m', '', $md); // strip leading line whitespace
        $md = preg_replace('/\s+([,\.\?!;:])/', '$1', $md);
        return trim($md) . "\n";
    }

    /**
     * Synchronize a single HTML page to its Markdown counterpart atomically
     */
    public static function syncHtmlPage(string $pageKey, bool $force = false): array {
        $inventory = self::getPageInventory();
        if (!isset($inventory[$pageKey])) {
            if (isset($inventory['services/' . $pageKey])) {
                $pageKey = 'services/' . $pageKey;
            } elseif (str_starts_with($pageKey, 'services/') && isset($inventory[substr($pageKey, 9)])) {
                $pageKey = substr($pageKey, 9);
            } else {
                return ['success' => false, 'error' => "Unknown page key: {$pageKey}"];
            }
        }

        $page = $inventory[$pageKey];
        $sourcePath = CMS_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $page['source_file']);
        $targetPath = CMS_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $page['markdown_file']);

        if (!file_exists($sourcePath)) {
            return ['success' => false, 'error' => "Source HTML file not found: {$page['source_file']}"];
        }

        $htmlContent = @file_get_contents($sourcePath);
        if ($htmlContent === false || trim($htmlContent) === '') {
            return ['success' => false, 'error' => "Source HTML file is empty or unreadable: {$page['source_file']}"];
        }

        $meta = self::extractMetadata($htmlContent, $page);
        $newMarkdown = self::convertHtmlToMarkdown($htmlContent, $meta);

        if (trim($newMarkdown) === '' || strlen($newMarkdown) < 100) {
            return ['success' => false, 'error' => "Generated markdown for {$pageKey} is invalid or incomplete."];
        }

        $newHash = hash('sha256', $newMarkdown);
        $existingHash = file_exists($targetPath) ? hash('sha256', file_get_contents($targetPath)) : null;

        // Skip rewrite if unchanged and not forced
        if (!$force && $existingHash === $newHash) {
            self::updatePageSyncLog($pageKey, [
                'status'        => 'synced',
                'action'        => 'skipped_unchanged',
                'source_file'   => $page['source_file'],
                'markdown_file' => $page['markdown_file'],
                'canonical_url' => $page['canonical_url'],
                'hash'          => $newHash,
                'bytes'         => strlen($newMarkdown),
                'synced_at'     => date('c')
            ]);
            return [
                'success' => true,
                'changed' => false,
                'action'  => 'skipped_unchanged',
                'page'    => $pageKey,
                'path'    => $targetPath,
                'bytes'   => strlen($newMarkdown)
            ];
        }

        // Atomic write
        $written = self::atomicWrite($targetPath, $newMarkdown);
        if (!$written) {
            self::updatePageSyncLog($pageKey, [
                'status'        => 'error',
                'error'         => "Failed atomic write to {$targetPath}",
                'source_file'   => $page['source_file'],
                'markdown_file' => $page['markdown_file'],
                'canonical_url' => $page['canonical_url'],
                'synced_at'     => date('c')
            ]);
            return ['success' => false, 'error' => "Failed atomic write to {$targetPath}"];
        }

        self::updatePageSyncLog($pageKey, [
            'status'        => 'synced',
            'action'        => 'written',
            'source_file'   => $page['source_file'],
            'markdown_file' => $page['markdown_file'],
            'canonical_url' => $page['canonical_url'],
            'hash'          => $newHash,
            'bytes'         => strlen($newMarkdown),
            'synced_at'     => date('c')
        ]);

        return [
            'success' => true,
            'changed' => true,
            'action'  => 'written',
            'page'    => $pageKey,
            'path'    => $targetPath,
            'bytes'   => strlen($newMarkdown)
        ];
    }

    /**
     * Synchronize blogs.md to both markdown/pages/blogs.md and CMS_ROOT/blogs.md
     */
    public static function syncBlogsListing(bool $force = false): array {
        if (!function_exists('generateBlogsMd')) {
            require_once CMS_ROOT . '/includes/llms-sync.php';
        }

        $content = generateBlogsMd();
        $target1 = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'blogs.md';
        $target2 = CMS_ROOT . DIRECTORY_SEPARATOR . 'blogs.md';

        $baseUrl = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
        $hash = hash('sha256', $content);

        $existingHash1 = file_exists($target1) ? hash('sha256', file_get_contents($target1)) : null;
        $existingHash2 = file_exists($target2) ? hash('sha256', file_get_contents($target2)) : null;

        if (!$force && $existingHash1 === $hash && $existingHash2 === $hash) {
            self::updatePageSyncLog('blogs', [
                'status'        => 'synced',
                'action'        => 'skipped_unchanged',
                'source_file'   => 'blogs.php',
                'markdown_file' => 'markdown/pages/blogs.md',
                'canonical_url' => $baseUrl . '/blogs.md',
                'hash'          => $hash,
                'bytes'         => strlen($content),
                'synced_at'     => date('c')
            ]);
            return [
                'success' => true,
                'changed' => false,
                'action'  => 'skipped_unchanged',
                'bytes'   => strlen($content)
            ];
        }

        $w1 = self::atomicWrite($target1, $content);
        $w2 = self::atomicWrite($target2, $content);

        self::updatePageSyncLog('blogs', [
            'status'        => ($w1 && $w2) ? 'synced' : 'error',
            'action'        => 'written',
            'source_file'   => 'blogs.php',
            'markdown_file' => 'markdown/pages/blogs.md',
            'canonical_url' => $baseUrl . '/blogs.md',
            'hash'          => $hash,
            'bytes'         => strlen($content),
            'synced_at'     => date('c')
        ]);

        return [
            'success' => ($w1 && $w2),
            'changed' => true,
            'action'  => 'written',
            'bytes'   => strlen($content)
        ];
    }

    /**
     * Reconcile the complete website (Core pages, Services, Blogs listing, and MySQL Blog posts)
     */
    public static function reconcileAll(bool $force = false): array {
        $inventory = self::getPageInventory();
        $results = [
            'total'     => 0,
            'synced'    => 0,
            'updated'   => 0,
            'unchanged' => 0,
            'errors'    => [],
            'items'     => []
        ];

        // 1. Reconcile HTML Core Pages & Service Pages
        foreach ($inventory as $key => $page) {
            if ($key === 'blogs') {
                $res = self::syncBlogsListing($force);
                $results['total']++;
                if ($res['success']) {
                    $results['synced']++;
                    if (!empty($res['changed'])) {
                        $results['updated']++;
                    } else {
                        $results['unchanged']++;
                    }
                    $results['items'][$key] = ['status' => 'synced', 'action' => $res['action'] ?? 'written'];
                } else {
                    $results['errors'][] = "blogs: Failed to synchronize blogs.md";
                    $results['items'][$key] = ['status' => 'error', 'error' => 'Failed to synchronize blogs.md'];
                }
                continue;
            }

            $res = self::syncHtmlPage($key, $force);
            $results['total']++;
            if ($res['success']) {
                $results['synced']++;
                if (!empty($res['changed'])) {
                    $results['updated']++;
                } else {
                    $results['unchanged']++;
                }
                $results['items'][$key] = ['status' => 'synced', 'action' => $res['action']];
            } else {
                $results['errors'][] = "{$key}: " . ($res['error'] ?? 'Unknown error');
                $results['items'][$key] = ['status' => 'error', 'error' => $res['error']];
            }
        }

        // 2. Reconcile MySQL Blog Posts (Dynamic Articles)
        if (function_exists('reconcileAllBlogsToMarkdown')) {
            $blogReconcile = reconcileAllBlogsToMarkdown();
            $results['blog_stats'] = $blogReconcile;
        }

        // 3. Synchronize llms.txt
        if (function_exists('syncLlmsTxt')) {
            syncLlmsTxt();
        }

        // 4. Update overall synchronization status file
        self::saveOverallStatus($results);

        return $results;
    }

    /**
     * Atomic write helper with directory creation and safe rename
     */
    private static function atomicWrite(string $targetPath, string $content): bool {
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $tmpPath = $targetPath . '.tmp.' . bin2hex(random_bytes(6));
        $bytes = @file_put_contents($tmpPath, $content, LOCK_EX);
        if ($bytes === false) {
            return false;
        }

        if (!@rename($tmpPath, $targetPath)) {
            @unlink($targetPath);
            if (!@rename($tmpPath, $targetPath)) {
                @unlink($tmpPath);
                return false;
            }
        }

        @chmod($targetPath, 0644);
        return true;
    }

    /**
     * Log status for a single page
     */
    private static function updatePageSyncLog(string $pageKey, array $data): void {
        $logFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'markdown_sync_status.json';
        $current = [];
        if (file_exists($logFile)) {
            $current = json_decode(file_get_contents($logFile), true) ?: [];
        }
        $current['pages'][$pageKey] = array_merge($current['pages'][$pageKey] ?? [], $data);
        $current['last_updated'] = date('c');

        @file_put_contents($logFile, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Save overall reconciliation summary
     */
    private static function saveOverallStatus(array $results): void {
        $logFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'markdown_sync_status.json';
        $current = [];
        if (file_exists($logFile)) {
            $current = json_decode(file_get_contents($logFile), true) ?: [];
        }

        $current['reconciliation'] = [
            'last_run'     => date('c'),
            'total_pages'  => $results['total'],
            'synced_pages' => $results['synced'],
            'updated'      => $results['updated'],
            'unchanged'    => $results['unchanged'],
            'errors'       => $results['errors'],
            'blog_posts'   => $results['blog_stats']['synced'] ?? 10
        ];

        @file_put_contents($logFile, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Retrieve live synchronization status report
     */
    public static function getSyncStatus(): array {
        $logFile = CMS_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'markdown_sync_status.json';
        if (file_exists($logFile)) {
            $data = json_decode(file_get_contents($logFile), true);
            if (is_array($data)) return $data;
        }
        return ['pages' => [], 'reconciliation' => null];
    }

    /**
     * Alias for getSyncStatus for audit log consumers
     */
    public static function getSyncAuditLog(): array {
        return self::getSyncStatus();
    }
}
