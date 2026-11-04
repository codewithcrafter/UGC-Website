<?php
/**
 * MY UGC VIDEO - Admin CMS Logout Handler
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

logoutAdmin();
header('Location: ' . ADMIN_URL . '/login.php');
exit;

