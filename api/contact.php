<?php
/**
 * MY UGC VIDEO — Centralized Lead Submission API Endpoint
 * 
 * Handles form submissions from Homepage (#projectInquiryForm) and Contact Page (#contactForm).
 * 
 * Security & Flow:
 * 1. Checks CSRF token (or generates one on GET request).
 * 2. Enforces anti-spam honeypot and IP rate limiting.
 * 3. Sanitizes and validates all incoming data server-side.
 * 4. Checks duplicate prevention within 60-second window.
 * 5. Saves lead into MariaDB `leads` table FIRST.
 * 6. Dispatches automatic visitor confirmation email ONLY to the visitor.
 * 7. Records email delivery status in `leads` table.
 * 8. Returns structured JSON response.
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';
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
        'message' => 'Inquiry received. Thank you for connecting with MY UGC VIDEO.'
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

$db = getDb();
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
    error_log("Rate limiting error in api/contact.php: " . $e->getMessage());
}

// 4. Data Extraction & Sanitization
$name = trim(strip_tags($_POST['name'] ?? $_POST['fullName'] ?? $_POST['detailedName'] ?? ''));
$email = trim(strip_tags($_POST['email'] ?? $_POST['workEmail'] ?? $_POST['detailedEmail'] ?? ''));
$phone = trim(strip_tags($_POST['phone'] ?? $_POST['phoneNumber'] ?? $_POST['detailedPhone'] ?? ''));
$brand = trim(strip_tags($_POST['brand'] ?? $_POST['companyName'] ?? $_POST['detailedBrand'] ?? ''));
$service = trim(strip_tags($_POST['service'] ?? $_POST['inquiryRole'] ?? $_POST['detailedService'] ?? ''));
$budget = trim(strip_tags($_POST['budget'] ?? $_POST['approxBudget'] ?? ''));
$message = trim(strip_tags($_POST['message'] ?? $_POST['details'] ?? $_POST['messageInput'] ?? ''));
$source = trim(strip_tags($_POST['source'] ?? ''));

// Validate and normalize Source
$allowedSources = ['Homepage Hero Form', 'Homepage Inquiry Form', 'Contact Page Form', 'Contact Popup Form'];
if (!in_array($source, $allowedSources)) {
    // Auto-detect based on referer if not explicitly set
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (str_contains($referer, 'contact')) {
        $source = 'Contact Page Form';
    } else {
        $source = 'Homepage Hero Form';
    }
}

// 5. Server-Side Validation
$errors = [];

if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
    $errors['name'] = 'Please provide your full name (2–150 characters).';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    $errors['email'] = 'Please provide a valid email address.';
}

if ($source === 'Contact Page Form' || $source === 'Contact Popup Form') {
    // Simplified Quick Inquiry requirements: Full Name, Email, Phone required; Message optional
    if (empty($phone) || mb_strlen($phone) < 7 || mb_strlen($phone) > 50) {
        $errors['phone'] = 'Please provide a valid phone number (at least 7 digits).';
    }
    if (!empty($message) && mb_strlen($message) > 5000) {
        $errors['message'] = 'Message is too long (maximum 5000 characters).';
    }
} else {
    // Standard validations for Homepage and other forms
    if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
        $errors['message'] = 'Please provide at least 10 characters of project context.';
    }

    if (!empty($phone) && mb_strlen($phone) > 50) {
        $errors['phone'] = 'Phone number is too long (maximum 50 characters).';
    }

    if (!empty($brand) && mb_strlen($brand) > 150) {
        $errors['brand'] = 'Brand name is too long (maximum 150 characters).';
    }

    if ($source === 'Homepage Hero Form' || $source === 'Homepage Inquiry Form') {
        if (empty($brand) || mb_strlen($brand) < 2) {
            $errors['brand'] = 'Please provide your brand name or website.';
        }
        if (empty($service)) {
            $errors['service'] = 'Please select your primary content need.';
        }
    }
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

// 6. Duplicate Submission Protection (Same email within 60 seconds)
try {
    $checkMsg = !empty($message) ? $message : 'Quick inquiry via Contact Page';
    $stmtDup = $db->prepare("
        SELECT id, created_at 
        FROM leads 
        WHERE email = :email 
          AND (message = :msg OR message = 'Quick inquiry via Contact Page') 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        ORDER BY id DESC LIMIT 1
    ");
    $stmtDup->execute([':email' => $email, ':msg' => $checkMsg]);
    $existingLead = $stmtDup->fetch();

    if ($existingLead) {
        // Return existing lead confirmation cleanly without creating duplicates or spamming emails
        echo json_encode([
            'success'      => true,
            'message'      => 'Inquiry received. Thank you for connecting with MY UGC VIDEO. Our team will respond shortly.',
            'lead_id'      => (int)$existingLead['id'],
            'duplicate'    => true
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log("Duplicate check error: " . $e->getMessage());
}

// 7. CRITICAL SEQUENCE: Save Lead in MariaDB FIRST
$leadId = 0;
try {
    $stmtInsert = $db->prepare("
        INSERT INTO leads 
        (name, email, phone, brand, service, budget, message, source, status, email_delivery_status, ip_address, user_agent, created_at)
        VALUES 
        (:name, :email, :phone, :brand, :service, :budget, :message, :source, 'new', 'pending', :ip, :ua, NOW())
    ");
    $stmtInsert->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':phone'   => !empty($phone) ? $phone : null,
        ':brand'   => !empty($brand) ? $brand : null,
        ':service' => !empty($service) ? $service : null,
        ':budget'  => !empty($budget) ? $budget : null,
        ':message' => !empty($message) ? $message : 'Quick inquiry via Contact Page',
        ':source'  => $source,
        ':ip'      => $ipAddress,
        ':ua'      => $userAgent
    ]);
    $leadId = (int)$db->lastInsertId();
} catch (Throwable $e) {
    error_log("Database lead persistence failure: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected database error occurred while recording your inquiry. Please try again or contact us directly.'
    ]);
    exit;
}

// 8. CRITICAL SEQUENCE: Attempt Automatic Visitor Confirmation Email ONLY
$emailResult = MailService::sendVisitorConfirmation([
    'id'    => $leadId,
    'name'  => $name,
    'email' => $email
]);

// 9. Respond to Client with Success Message
// Note: Even if email delivery fails, the lead is preserved, and the visitor sees success!
echo json_encode([
    'success'      => true,
    'message'      => 'Inquiry Received. Thank you for connecting with MY UGC VIDEO. Our creative directors review every brief and will respond within 24 business hours.',
    'lead_id'      => $leadId,
    'email_sent'   => (bool)$emailResult['success'],
    'email_status' => $emailResult['status'] ?? 'pending'
]);
exit;

