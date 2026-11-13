<?php
/**
 * MY UGC VIDEO - Admin CMS Gateway
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

if (isLoggedIn()) {
    header('Location: ' . ADMIN_URL . '/dashboard');
} else {
    header('Location: ' . ADMIN_URL . '/login');
}
exit;

