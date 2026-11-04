<?php
/**
 * MY UGC VIDEO - XML Sitemap Generator / Sync
 * 
 * Synchronizes sitemap.xml with all published blog posts in the MySQL database.
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

$db = getDb();
$posts = $db->query(
    "SELECT slug, updated_at, published_at 
     FROM blog_posts 
     WHERE status = 'published' AND published_at <= NOW() 
     ORDER BY published_at DESC"
)->fetchAll();

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Core Main Pages
$corePages = [
    ['loc' => 'https://myugcvideo.example.com/', 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => 'https://myugcvideo.example.com/about.html', 'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => 'https://myugcvideo.example.com/services.html', 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => 'https://myugcvideo.example.com/blogs.php', 'changefreq' => 'daily', 'priority' => '0.9'],
    ['loc' => 'https://myugcvideo.example.com/contact.html', 'changefreq' => 'monthly', 'priority' => '0.8'],
];

$xml .= "  <!-- Core Main Pages -->\n";
foreach ($corePages as $p) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>{$p['loc']}</loc>\n";
    $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    $xml .= "    <changefreq>{$p['changefreq']}</changefreq>\n";
    $xml .= "    <priority>{$p['priority']}</priority>\n";
    $xml .= "  </url>\n";
}

// 9 Service Pages
$servicePages = [
    'services/ugc-video-creation-company.html',
    'services/ugc-video-ads-company.html',
    'services/ai-ugc-videos-company.html',
    'services/ugc-product-videos-company.html',
    'services/ugc-product-reviews-videos-company.html',
    'services/ugc-video-service-in-delhi-company.html',
    'services/ugc-unboxing-videos-company.html',
    'services/ugc-testimonial-videos-company.html',
    'services/ugc-video-editing-service-company.html'
];

$xml .= "\n  <!-- Dedicated Nine Core UGC Service Pages -->\n";
foreach ($servicePages as $sp) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>https://myugcvideo.example.com/{$sp}</loc>\n";
    $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    $xml .= "    <changefreq>weekly</changefreq>\n";
    $xml .= "    <priority>0.9</priority>\n";
    $xml .= "  </url>\n";
}

// Dynamic Published Blogs from MySQL
$xml .= "\n  <!-- Dynamic Published Blog Articles -->\n";
foreach ($posts as $post) {
    $lastmod = date('Y-m-d', strtotime($post['updated_at'] ?? $post['published_at']));
    $xml .= "  <url>\n";
    $xml .= "    <loc>https://myugcvideo.example.com/blog/{$post['slug']}/</loc>\n";
    $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
    $xml .= "    <changefreq>monthly</changefreq>\n";
    $xml .= "    <priority>0.8</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= "</urlset>\n";

file_put_contents(CMS_ROOT . '/sitemap.xml', $xml);
echo "Sitemap updated successfully with " . count($posts) . " published blog articles.\n";

