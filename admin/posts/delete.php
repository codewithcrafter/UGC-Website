<?php
/**
 * MY UGC VIDEO - Safe Post Deletion Handler
 */

define('CMS_ROOT', dirname(dirname(__DIR__)));
require_once CMS_ROOT . '/config/config.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $postId = (int) ($_POST['post_id'] ?? 0);
    if ($postId > 0) {
        $db = getDb();
        $stmt = $db->prepare("SELECT title, markdown_file, schema_filename FROM blog_posts WHERE id = :id");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();

        if ($post) {
            $del = $db->prepare("DELETE FROM blog_posts WHERE id = :id");
            $del->execute([':id' => $postId]);

            // Clean up associated markdown and schema files (public and staging)
            if (function_exists('removeBlogPostArtifacts')) {
                removeBlogPostArtifacts($postId, $post['slug'] ?? null);
            }
            if (!empty($post['markdown_file'])) {
                $mfPath = CMS_ROOT . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $post['markdown_file']);
                if (file_exists($mfPath)) @unlink($mfPath);
            }
            if (!empty($post['schema_filename'])) {
                $sfPath = CMS_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $post['schema_filename']);
                if (file_exists($sfPath)) @unlink($sfPath);
            }

            // Automatically regenerate discovery systems & notify engines
            $canonicalBase = function_exists('getLlmCanonicalBaseUrl') ? getLlmCanonicalBaseUrl() : 'https://myugcvideo.in';
            $postUrl = $canonicalBase . '/blog/' . urlencode($post['slug']);
            if (function_exists('runFullReconciliation')) {
                runFullReconciliation(['changed_urls' => [$postUrl, $canonicalBase . '/blogs.php']]);
            } else {
                syncLlmsTxt();
            }

            setFlash('success', 'Article "' . $post['title'] . '" and its documentation entries have been permanently removed.');
        } else {
            setFlash('error', 'Article not found.');
        }
    }
}

header('Location: ' . ADMIN_URL . '/posts');
exit;

