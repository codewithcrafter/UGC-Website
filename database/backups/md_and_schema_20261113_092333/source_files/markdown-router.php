<?php
/**
 * MY UGC VIDEO — Centralized Public Raw Markdown Router
 * Serves original Markdown source documents as plain/raw text across the entire website.
 * Emulates the exact behavior of llms.txt: displays raw Markdown syntax directly in the browser.
 *
 * Supported URL patterns:
 *   Root-level:
 *     /about.md
 *     /contact.md
 *     /services.md
 *     /blogs.md (or /blog.md)
 *     /index.md (or /home.md)
 *   Sectional:
 *     /blog/{slug}.md (or /blogs/{slug}.md)
 *     /services/{slug}.md (or /service/{slug}.md)
 *     /pages/{slug}.md (or /page/{slug}.md)
 *     /case-studies/{slug}.md
 *     /portfolio/{slug}.md
 *     /other/{slug}.md
 */

define('CMS_ROOT', __DIR__);
require_once CMS_ROOT . '/config/config.php';
require_once CMS_ROOT . '/includes/auth.php';

// 1. Retrieve & sanitize requested path
$rawPath = trim($_GET['path'] ?? '');

// Decode and normalize separators
$decodedPath = rawurldecode($rawPath);
$normalizedPath = str_replace(['\\', '/'], '/', $decodedPath);
$normalizedPath = ltrim($normalizedPath, '/');

// Security: Reject null bytes and path traversal patterns
if (str_contains($rawPath, "\0") || 
    str_contains($decodedPath, "\0") || 
    str_contains($normalizedPath, '..') || 
    str_contains($normalizedPath, '//')) {
    serveError(400, 'Bad Request: Invalid path characters detected.');
}

// Ensure request ends with .md
if (!str_ends_with(strtolower($normalizedPath), '.md')) {
    serveError(404, 'Not Found: Only .md documents can be requested.');
}

// Parse prefix and filename slug
// Supported sections: blog, blogs, pages, page, services, service, case-studies, portfolio, other, docs, or root
$prefix = null;
$slug = null;

if (preg_match('#^(blog|blogs|pages|page|services|service|case-studies|portfolio|other|docs)/([a-zA-Z0-9_\-\.]+)\.md$#i', $normalizedPath, $matches)) {
    $prefix = strtolower($matches[1]);
    $slug = $matches[2];
} elseif (preg_match('#^([a-zA-Z0-9_\-\.]+)\.md$#i', $normalizedPath, $matches)) {
    $prefix = 'root';
    $slug = strtolower($matches[1]);
} else {
    serveError(404, 'Not Found: Invalid markdown path structure.');
}

$targetFile = null;
$isAdmin = function_exists('isLoggedIn') && isLoggedIn();

// 2. Resolve target file based on section or root alias
if ($prefix === 'root') {
    if ($slug === 'index' || $slug === 'home' || $slug === 'homepage') {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'index.md';
        if (file_exists($candidate)) $targetFile = $candidate;
    } elseif ($slug === 'services') {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'services.md';
        if (file_exists($candidate)) $targetFile = $candidate;
    } elseif ($slug === 'blogs' || $slug === 'blog') {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'blogs.md';
        if (file_exists($candidate)) $targetFile = $candidate;
    } elseif (file_exists(MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $slug . '.md')) {
        $targetFile = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $slug . '.md';
    } elseif (file_exists(MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . $slug . '.md')) {
        $targetFile = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . $slug . '.md';
    } else {
        // Fallback: check if it's a blog post slug directly at root
        try {
            $db = getDb();
            $stmt = $db->prepare("SELECT id, slug, status, published_at FROM blog_posts WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($post) {
                $isPublished = ($post['status'] === 'published' && (empty($post['published_at']) || strtotime($post['published_at']) <= time()));
                if ($isPublished || $isAdmin) {
                    $publicFile = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'blogs' . DIRECTORY_SEPARATOR . 'post-' . $post['id'] . '-' . $post['slug'] . '.md';
                    if (file_exists($publicFile)) {
                        $targetFile = $publicFile;
                    }
                }
            }
        } catch (Throwable $e) {}
    }

} elseif ($prefix === 'blog' || $prefix === 'blogs') {
    if ($slug === 'index' || $slug === 'all' || $slug === 'blogs' || $slug === 'blog') {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'blogs.md';
        if (file_exists($candidate)) $targetFile = $candidate;
    } else {
        // Look up blog post by slug in MySQL
        try {
            $db = getDb();
            $stmt = $db->prepare("SELECT id, slug, status, published_at FROM blog_posts WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                serveError(404, 'Blog Article Not Found: The requested article markdown does not exist.');
            }

            // Draft and publication schedule checks
            $isPublished = ($post['status'] === 'published' && (empty($post['published_at']) || strtotime($post['published_at']) <= time()));
            if (!$isPublished && !$isAdmin) {
                serveError(404, 'Document Not Found: The requested document is not publicly available.');
            }

            // Locate physical file in markdown/blogs/ or markdown/staging/
            $publicFile = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'blogs' . DIRECTORY_SEPARATOR . 'post-' . $post['id'] . '-' . $post['slug'] . '.md';
            $stagingFile = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . 'post-' . $post['id'] . '-' . $post['slug'] . '.md';

            if (file_exists($publicFile)) {
                $targetFile = $publicFile;
            } elseif (!$isPublished && $isAdmin && file_exists($stagingFile)) {
                $targetFile = $stagingFile;
            } else {
                // Fallback search by post ID
                $globPublic = glob(MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'blogs' . DIRECTORY_SEPARATOR . 'post-' . $post['id'] . '-*.md');
                if (!empty($globPublic) && file_exists($globPublic[0])) {
                    $targetFile = $globPublic[0];
                } elseif (!$isPublished && $isAdmin) {
                    $globStaging = glob(MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . '*post-' . $post['id'] . '-*.md');
                    if (!empty($globStaging) && file_exists($globStaging[0])) {
                        $targetFile = $globStaging[0];
                    }
                }
            }
        } catch (Throwable $e) {
            serveError(500, 'Database error resolving markdown document.');
        }
    }

} elseif ($prefix === 'pages' || $prefix === 'page') {
    if ($slug === 'index' || $slug === 'home' || $slug === 'homepage') {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'index.md';
        if (file_exists($candidate)) $targetFile = $candidate;
    } elseif ($slug === 'services') {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'services.md';
        if (file_exists($candidate)) $targetFile = $candidate;
    } elseif ($slug === 'blogs' || $slug === 'blog') {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'blogs.md';
        if (file_exists($candidate)) $targetFile = $candidate;
    } else {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $slug . '.md';
        if (file_exists($candidate)) {
            $targetFile = $candidate;
        }
    }

} elseif ($prefix === 'services' || $prefix === 'service') {
    if ($slug === 'index' || $slug === 'all' || $slug === 'services') {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'services.md';
        if (file_exists($candidate)) $targetFile = $candidate;
    } else {
        $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . $slug . '.md';
        if (file_exists($candidate)) {
            $targetFile = $candidate;
        }
    }

} elseif ($prefix === 'case-studies' || $prefix === 'portfolio') {
    $candidate1 = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'case-studies' . DIRECTORY_SEPARATOR . $slug . '.md';
    $candidate2 = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'portfolio' . DIRECTORY_SEPARATOR . $slug . '.md';
    if (file_exists($candidate1)) {
        $targetFile = $candidate1;
    } elseif (file_exists($candidate2)) {
        $targetFile = $candidate2;
    }

} elseif ($prefix === 'other' || $prefix === 'docs') {
    $candidate = MARKDOWN_DIR . DIRECTORY_SEPARATOR . 'other' . DIRECTORY_SEPARATOR . $slug . '.md';
    if (file_exists($candidate)) {
        $targetFile = $candidate;
    }
}

// 3. Final Path Verification and Realpath Security Traversal Check
if (!$targetFile || !file_exists($targetFile)) {
    serveError(404, 'Markdown Document Not Found: The requested document does not exist.');
}

$realTarget = realpath($targetFile);
$realMarkdownDir = realpath(MARKDOWN_DIR);

if (!$realTarget || !$realMarkdownDir || !str_starts_with($realTarget, $realMarkdownDir)) {
    serveError(403, 'Forbidden: Access to requested filesystem resource is denied.');
}

// Prevent non-admin access to staging directory
if (str_contains($realTarget, DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR) && !$isAdmin) {
    serveError(404, 'Document Not Found: Requested document is not publicly available.');
}

// 4. Output Raw Markdown Response Headers & Stream Original Source
// Inline presentation ensures standard browsers display the plain text directly rather than prompting download
header('Content-Type: text/markdown; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . basename($realTarget) . '"');
header('Cache-Control: public, max-age=3600');

// Stream original raw file directly
readfile($realTarget);
exit;

/**
 * Standard Plain-Text HTTP Error Response
 */
function serveError(int $code, string $message): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo "HTTP {$code}: {$message}\n";
    exit;
}
