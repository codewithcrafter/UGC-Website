<?php
/**
 * MY UGC VIDEO — Quick Enquiry Form API Endpoint
 * 
 * Handles submissions from the 3D Baby Monster Quick Enquiry Form on the Service Hero section.
 * 
 * Specification:
 * - Exactly 4 fields: Name (required), Email (required), Phone (required, Indian mobile), Message (optional).
 * - Lead source: strictly 'website_enquiry_form'.
 * - Default status: 'new'.
 * - CSRF verification, rate limiting, anti-spam honeypot, duplicate protection.
 * - Saves lead into MariaDB `leads` table FIRST.
 * - Dispatches email notification via MailService in safe try-catch.
 * - Returns structured JSON response.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}
require_once CMS_ROOT . '/config/config.php';
require_once CMS_ROOT . '/config/database.php';
require_once CMS_ROOT . '/includes/csrf.php';
require_once CMS_ROOT . '/includes/mail-service.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Support GET request to fetch or initialize CSRF token for static HTML pages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = generateCsrfToken();
    echo json_encode([
        'success'    => true,
        'csrf_token' => $token
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST is accepted.'
    ]);
    exit;
}

// 1. Anti-Spam Honeypot Verification
$honeypot = trim($_POST['website_hp'] ?? '');
if (!empty($honeypot)) {
    // Silently reject bot submissions with a generic success response to avoid bot retries
    echo json_encode([
        'success' => true,
        'message' => 'Enquiry received. Thank you for connecting with MY UGC VIDEO.'
    ]);
    exit;
}

// 2. CSRF Token Verification
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Security verification token invalid or expired. Please refresh the page and try again.'
    ]);
    exit;
}

// 3. Rate Limiting per IP Address (Max 5 submissions per 10 minutes)
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

$db = Database::getConnection();
try {
    $stmtRate = $db->prepare("
        SELECT COUNT(*) 
        FROM leads 
        WHERE ip_address = :ip 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmtRate->execute([':ip' => $ipAddress]);
    $recentSubmissions = (int)$stmtRate->fetchColumn();

    if ($recentSubmissions >= 5) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests from your IP. Please wait a few minutes before submitting again.'
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log("Rate limiting error in api/quick-enquiry.php: " . $e->getMessage());
}

// 4. Data Extraction & Sanitization
$name    = trim(strip_tags($_POST['name'] ?? ''));
$email   = trim(strip_tags($_POST['email'] ?? ''));
$rawPhone = trim(strip_tags($_POST['phone'] ?? ''));
$message = trim(strip_tags($_POST['message'] ?? ''));

// Source Value (defaults to 'website_enquiry_form' for backward compatibility)
$allowedSources = ['website_enquiry_form', 'blog_strategy_call'];
$requestedSource = trim($_POST['source'] ?? '');
$source = in_array($requestedSource, $allowedSources, true) ? $requestedSource : 'website_enquiry_form';

// 5. Server-Side Validation
$errors = [];

// Name: Required, 2 to 150 characters
if (empty($name)) {
    $errors['name'] = 'Please enter your full name.';
} elseif (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
    $errors['name'] = 'Name must be between 2 and 150 characters.';
}

// Email: Required, valid format
if (empty($email)) {
    $errors['email'] = 'Please enter your email address.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    $errors['email'] = 'Please enter a valid email address.';
}

// Phone Number: Required, Indian mobile validation & normalization
// Matches 10-digit Indian numbers starting with 6, 7, 8, or 9 (with optional +91, 91, or 0 prefix)
$cleanedPhone = preg_replace('/[\s\-\(\)]+/', '', $rawPhone);
$normalizedPhone = '';

if (empty($rawPhone)) {
    $errors['phone'] = 'Please enter your Indian phone number.';
} elseif (preg_match('/^(?:(?:\+|0{0,2})91)?[6789]\d{9}$/', $cleanedPhone, $matches)) {
    // Extract last 10 digits
    $last10 = substr($cleanedPhone, -10);
    $normalizedPhone = '+91 ' . $last10;
} else {
    $errors['phone'] = 'Please enter a valid 10-digit Indian mobile number (e.g. 9876543210).';
}

// Message: Optional, max 5000 characters
if (!empty($message) && mb_strlen($message) > 5000) {
    $errors['message'] = 'Message is too long (maximum 5000 characters).';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please correct the highlighted validation errors.',
        'errors'  => $errors
    ]);
    exit;
}

// 6. Duplicate Submission Protection (Same email and phone within 60 seconds)
try {
    $stmtDup = $db->prepare("
        SELECT id, created_at 
        FROM leads 
        WHERE email = :email 
          AND phone = :phone
          AND source = :source
          AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        ORDER BY id DESC LIMIT 1
    ");
    $stmtDup->execute([':email' => $email, ':phone' => $normalizedPhone, ':source' => $source]);
    $existingLead = $stmtDup->fetch();

    if ($existingLead) {
        echo json_encode([
            'success'   => true,
            'message'   => 'Enquiry received. Thank you for connecting with MY UGC VIDEO. Our team will respond shortly.',
            'lead_id'   => (int)$existingLead['id'],
            'duplicate' => true
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log("Duplicate check error: " . $e->getMessage());
}

// 7. CRITICAL SEQUENCE: Save Lead in MariaDB FIRST
$leadId = 0;
try {
    // Message can be empty string in leads table
    $storedMessage = $message !== '' ? $message : '';

    $stmtInsert = $db->prepare("
        INSERT INTO leads 
        (name, email, phone, brand, service, budget, message, source, status, email_delivery_status, ip_address, user_agent, created_at)
        VALUES 
        (:name, :email, :phone, NULL, 'General UGC Inquiry', NULL, :message, :source, 'new', 'pending', :ip, :ua, NOW())
    ");
    $stmtInsert->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':phone'   => $normalizedPhone,
        ':message' => $storedMessage,
        ':source'  => $source,
        ':ip'      => $ipAddress,
        ':ua'      => $userAgent
    ]);
    $leadId = (int)$db->lastInsertId();
} catch (Throwable $e) {
    error_log("Database lead persistence failure in api/quick-enquiry.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected database error occurred while recording your enquiry. Please try again or contact us directly.'
    ]);
    exit;
}

// 8. Attempt Email Notification (Safe try-catch: email failure never loses the lead!)
$emailResult = ['success' => false, 'status' => 'pending'];
try {
    $emailResult = MailService::sendVisitorConfirmation([
        'id'    => $leadId,
        'name'  => $name,
        'email' => $email
    ]);
} catch (Throwable $mailEx) {
    error_log("Email notification error in api/quick-enquiry.php: " . $mailEx->getMessage());
}

// 9. Respond to Client with Success Message
echo json_encode([
    'success'      => true,
    'message'      => 'Enquiry Received! Thank you for connecting with MY UGC VIDEO. Our team will review your brief and contact you within 24 hours.',
    'lead_id'      => $leadId,
    'email_sent'   => (bool)($emailResult['success'] ?? false),
    'email_status' => $emailResult['status'] ?? 'pending'
]);
exit;

