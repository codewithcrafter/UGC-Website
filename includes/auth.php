<?php
/**
 * MY UGC VIDEO - Authentication & Session Security Middleware
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

function isLoggedIn(): bool {
    return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']);
}

function getCurrentAdmin(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDb();
    $stmt = $db->prepare("SELECT id, name, email, role, is_active FROM admins WHERE id = :id AND is_active = 1");
    $stmt->execute([':id' => $_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        logoutAdmin();
        return null;
    }
    return $admin;
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? (ADMIN_URL . '/dashboard.php');
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
}

function checkLoginThrottle(string $ip): bool {
    $db = getDb();
    // Allow up to 5 attempts per 15 minutes
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts 
         WHERE ip_address = :ip AND attempted_at > (NOW() - INTERVAL 15 MINUTE)"
    );
    $stmt->execute([':ip' => $ip]);
    $attempts = (int) $stmt->fetchColumn();
    return $attempts < 5;
}

function recordLoginAttempt(string $ip): void {
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, attempted_at) VALUES (:ip, NOW())");
    $stmt->execute([':ip' => $ip]);
}

function clearLoginAttempts(string $ip): void {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
    $stmt->execute([':ip' => $ip]);
}

function loginAdmin(array $admin): void {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']        = $admin['id'];
    $_SESSION['admin_name']      = $admin['name'];
    $_SESSION['admin_email']     = $admin['email'];
    $_SESSION['admin_role']      = $admin['role'];

    // Update last login
    $db = getDb();
    $stmt = $db->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $admin['id']]);
}

function logoutAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

