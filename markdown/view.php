<?php
/**
 * MY UGC VIDEO — Public & Secure Markdown Viewer
 * Serves managed markdown files and llms.txt with directory traversal protection
 * and strict Draft article privacy enforcement.
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

$requestedFile = trim($_GET['file'] ?? '');
$format = trim($_GET['format'] ?? 'raw'); // 'raw' (text/markdown) or 'html'

if (empty($requestedFile)) {
    http_response_code(400);
    echo "Bad Request: No markdown file specified.";
    exit;
}

// Normalize path separators
$requestedFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $requestedFile);

// Check if requested file is root llms.txt
$isLlmsTxt = ($requestedFile === 'llms.txt' || basename($requestedFile) === 'llms.txt' && dirname($requestedFile) === '.');
$targetFullPath = $isLlmsTxt ? CMS_ROOT . DIRECTORY_SEPARATOR . 'llms.txt' : MARKDOWN_DIR . DIRECTORY_SEPARATOR . ltrim($requestedFile, DIRECTORY_SEPARATOR);

// Resolve realpath
$realTarget = realpath($targetFullPath);
$realMarkdownDir = realpath(MARKDOWN_DIR);
$realRoot = realpath(CMS_ROOT);

// Traversal Security Check
if (!$realTarget || !file_exists($realTarget)) {
    http_response_code(404);
    echo "404 Not Found: Requested markdown file does not exist.";
    exit;
}

$withinMarkdownDir = ($realMarkdownDir && str_starts_with($realTarget, $realMarkdownDir));
$isRootLlms = ($realTarget === realpath($realRoot . DIRECTORY_SEPARATOR . 'llms.txt'));

if (!$withinMarkdownDir && !$isRootLlms) {
    http_response_code(403);
    echo "403 Forbidden: Access denied.";
    exit;
}

// File extension check
$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
if ($ext !== 'md' && $ext !== 'txt') {
    http_response_code(403);
    echo "403 Forbidden: Only markdown (.md) and directive (.txt) files can be viewed.";
    exit;
}

// Draft Security Enforcement for Blog Markdown
$filename = basename($realTarget);
if (preg_match('/^post-(\d+)-/i', $filename, $matches)) {
    $postId = (int) $matches[1];
    $db = getDb();
    $stmt = $db->prepare("SELECT id, status, title FROM blog_posts WHERE id = :id");
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch();

    if ($post && $post['status'] === 'draft') {
        // Only authenticated admins may view draft markdown
        if (!isLoggedIn()) {
            http_response_code(404); // Obscure existence of draft
            echo "404 Not Found: The requested article is not publicly available.";
            exit;
        }
        header('X-Robots-Tag: noindex, nofollow');
    }
}

$content = file_get_contents($realTarget);

if ($format === 'html') {
    $parsedHtml = markdownToHtml($content);
    $pageTitle = htmlspecialchars(pathinfo($filename, PATHINFO_FILENAME));
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — MY UGC VIDEO Markdown</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-dark: #001411;
      --card-bg: #001f1b;
      --accent-mint: #36D9B0;
      --text-white: #FFFFFF;
      --text-muted: #8EAAA0;
      --text-secondary: #C0D3CC;
      --border-color: #31534D;
    }
    * { box-sizing: border-box; }
    body {
      background-color: var(--bg-dark);
      color: var(--text-secondary);
      font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
      margin: 0;
      padding: 2.5rem 1.5rem;
      line-height: 1.7;
    }
    .container {
      max-width: 820px;
      margin: 0 auto;
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 12px;
      padding: 3rem 2.5rem;
    }
    .doc-header {
      border-bottom: 1px solid var(--border-color);
      padding-bottom: 1.5rem;
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .badge {
      display: inline-block;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.75rem;
      padding: 0.25rem 0.6rem;
      border-radius: 4px;
      background: rgba(54, 217, 176, 0.15);
      color: var(--accent-mint);
      border: 1px solid rgba(54, 217, 176, 0.3);
    }
    .raw-link {
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.8rem;
      color: var(--accent-mint);
      text-decoration: none;
    }
    .raw-link:hover { text-decoration: underline; }
    h1, h2, h3, h4, h5, h6 {
      color: var(--text-white);
      font-family: 'Space Grotesk', sans-serif;
      line-height: 1.3;
      margin-top: 1.8rem;
      margin-bottom: 0.8rem;
    }
    a { color: var(--accent-mint); text-decoration: underline; }
    code {
      font-family: 'JetBrains Mono', monospace;
      background: rgba(0, 0, 0, 0.35);
      color: var(--accent-mint);
      padding: 0.15rem 0.35rem;
      border-radius: 4px;
      font-size: 0.88em;
    }
    pre {
      background: #000c0a;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 1.25rem;
      overflow-x: auto;
    }
    pre code { background: none; padding: 0; }
    blockquote {
      border-left: 3px solid var(--accent-mint);
      margin: 1.5rem 0;
      padding: 0.5rem 1.25rem;
      background: rgba(54, 217, 176, 0.05);
      border-radius: 0 6px 6px 0;
    }
    ul, ol { padding-left: 1.5rem; }
    li { margin-bottom: 0.4rem; }
  </style>
</head>
<body>
  <div class="container">
    <div class="doc-header">
      <span class="badge"><?= htmlspecialchars($filename) ?></span>
      <a href="?file=<?= urlencode($requestedFile) ?>&format=raw" class="raw-link">&larr; View Raw Markdown</a>
    </div>
    <div class="prose">
      <?= $parsedHtml ?>
    </div>
  </div>
</body>
</html>
    <?php
    exit;
}

// Raw view
header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: inline; filename="' . $filename . '"');
echo $content;
exit;

