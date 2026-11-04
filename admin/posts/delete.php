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
        $stmt = $db->prepare("SELECT title FROM blog_posts WHERE id = :id");
        $stmt->execute([':id' => $postId]);
        $title = $stmt->fetchColumn();

        if ($title) {
            $del = $db->prepare("DELETE FROM blog_posts WHERE id = :id");
            $del->execute([':id' => $postId]);
            setFlash('success', 'Article "' . $title . '" has been permanently deleted.');
        } else {
            setFlash('error', 'Article not found.');
        }
    }
}

header('Location: ' . ADMIN_URL . '/posts/index.php');
exit;

