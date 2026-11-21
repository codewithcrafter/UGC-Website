<?php
/**
 * MY UGC VIDEO — Centralized Lead Management System
 * 
 * Features:
 * - KPI Metrics: Total, New, Contacted, Converted, Closed leads
 * - Filter & Search: Search by name, email, phone; filter by status, source, date
 * - Lead Management: Status transitions, internal notes, email retry
 * - Secure CSV Export with spreadsheet formula injection defense
 * - Pagination & responsive editorial layout
 * - Lead details modal with full metadata and email delivery diagnostics
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';
require_once CMS_ROOT . '/includes/csrf.php';
require_once CMS_ROOT . '/includes/mail-service.php';

requireAuth();

$db = getDb();
$activeNav = 'leads';
$pageTitle = 'Lead Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard'],
    ['name' => 'Leads', 'url' => ADMIN_URL . '/leads']
];

$errors = [];

// =========================================================================
// 1. AJAX & POST Action Handlers
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $action = trim($_POST['action'] ?? '');

    // A. Update Lead Status
    if ($action === 'update_status') {
        $leadId = (int)($_POST['lead_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowedStatuses = ['new', 'contacted', 'converted', 'closed'];

        if ($leadId > 0 && in_array($newStatus, $allowedStatuses)) {
            $stmt = $db->prepare("UPDATE leads SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $leadId]);
            setFlash('success', "Lead #{$leadId} status updated to " . ucfirst($newStatus) . ".");
        } else {
            setFlash('error', "Invalid status update request.");
        }
        header('Location: ' . ADMIN_URL . '/leads' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
        exit;
    }

    // B. Update Internal Notes
    if ($action === 'update_notes') {
        $leadId = (int)($_POST['lead_id'] ?? 0);
        $notes = trim($_POST['internal_notes'] ?? '');

        if ($leadId > 0) {
            $stmt = $db->prepare("UPDATE leads SET internal_notes = :notes WHERE id = :id");
            $stmt->execute([':notes' => $notes ?: null, ':id' => $leadId]);
            setFlash('success', "Internal notes for Lead #{$leadId} updated.");
        }
        header('Location: ' . ADMIN_URL . '/leads' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
        exit;
    }

    // C. Retry Confirmation Email (Admin-only)
    if ($action === 'retry_email') {
        $leadId = (int)($_POST['lead_id'] ?? 0);
        if ($leadId > 0) {
            $res = MailService::retryConfirmation($leadId);
            if ($res['success']) {
                setFlash('success', "Confirmation email successfully re-sent to visitor for Lead #{$leadId}.");
            } else {
                setFlash('error', "Email retry attempted but failed: " . ($res['error'] ?? 'Unknown error'));
            }
        }
        header('Location: ' . ADMIN_URL . '/leads' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
        exit;
    }

    // D. Delete Lead
    if ($action === 'delete') {
        $leadId = (int)($_POST['lead_id'] ?? 0);
        if ($leadId > 0) {
            $stmt = $db->prepare("DELETE FROM leads WHERE id = :id");
            $stmt->execute([':id' => $leadId]);
            setFlash('success', "Lead #{$leadId} permanently deleted.");
        }
        header('Location: ' . ADMIN_URL . '/leads');
        exit;
    }

    // E. AJAX Get Lead Details
    if ($action === 'get_details') {
        header('Content-Type: application/json; charset=utf-8');
        $leadId = (int)($_POST['lead_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM leads WHERE id = :id");
        $stmt->execute([':id' => $leadId]);
        $lead = $stmt->fetch();
        if ($lead) {
            echo json_encode(['success' => true, 'lead' => $lead]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lead not found.']);
        }
        exit;
    }
}

// =========================================================================
// 2. CSV Export Handler
// =========================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Re-use current filter parameters for targeted export
    $exportSearch = trim($_GET['search'] ?? '');
    $exportStatus = trim($_GET['status'] ?? '');
    $exportSource = trim($_GET['source'] ?? '');
    $exportDateFrom = trim($_GET['date_from'] ?? '');
    $exportDateTo = trim($_GET['date_to'] ?? '');

    $where = ["1=1"];
    $params = [];

    if (!empty($exportSearch)) {
        $where[] = "(name LIKE :s OR email LIKE :s OR phone LIKE :s OR brand LIKE :s)";
        $params[':s'] = "%{$exportSearch}%";
    }
    if (!empty($exportStatus) && in_array($exportStatus, ['new', 'contacted', 'converted', 'closed'])) {
        $where[] = "status = :st";
        $params[':st'] = $exportStatus;
    }
    if (!empty($exportSource)) {
        $where[] = "source = :src";
        $params[':src'] = $exportSource;
    }
    if (!empty($exportDateFrom)) {
        $where[] = "DATE(created_at) >= :df";
        $params[':df'] = $exportDateFrom;
    }
    if (!empty($exportDateTo)) {
        $where[] = "DATE(created_at) <= :dt";
        $params[':dt'] = $exportDateTo;
    }

    $whereClause = implode(' AND ', $where);
    $stmtExp = $db->prepare("SELECT * FROM leads WHERE {$whereClause} ORDER BY id DESC");
    $stmtExp->execute($params);
    $leadsExport = $stmtExp->fetchAll();

    $filename = "myugcvideo_leads_" . date('Y-m-d_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compliance
    fputs($out, "\xEF\xBB\xBF");

    // Header row
    fputcsv($out, [
        'Lead ID',
        'Name',
        'Email',
        'Phone',
        'Brand / Company',
        'Interested Service',
        'Budget Range',
        'Message',
        'Source',
        'Status',
        'Email Delivery Status',
        'Email Attempts',
        'Last Email Attempt',
        'Internal Notes',
        'IP Address',
        'Created At'
    ], ',', '"', "\\");

    // Sanitizer against Spreadsheet Formula Injection (=, +, -, @, \t, \r)
    $sanitizeCell = function($val) {
        $str = (string)$val;
        if (preg_match('/^[\=\+\-\@\t\r]/', $str)) {
            return "'" . $str;
        }
        return $str;
    };

    foreach ($leadsExport as $row) {
        fputcsv($out, [
            $row['id'],
            $sanitizeCell($row['name']),
            $sanitizeCell($row['email']),
            $sanitizeCell($row['phone']),
            $sanitizeCell($row['brand']),
            $sanitizeCell($row['service']),
            $sanitizeCell($row['budget']),
            $sanitizeCell($row['message']),
            $sanitizeCell($row['source']),
            ucfirst($row['status']),
            ucfirst($row['email_delivery_status']),
            $row['email_attempts'],
            $row['last_email_attempt_at'] ?: 'N/A',
            $sanitizeCell($row['internal_notes'] ?: ''),
            $row['ip_address'],
            $row['created_at']
        ], ',', '"', "\\");
    }

    fclose($out);
    exit;
}

// =========================================================================
// 3. KPI Metrics Aggregation
// =========================================================================
$totalLeads = (int)$db->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$newLeads = (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
$contactedLeads = (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'contacted'")->fetchColumn();
$convertedLeads = (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'converted'")->fetchColumn();
$closedLeads = (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'closed'")->fetchColumn();

// =========================================================================
// 4. Search, Filter & Pagination Logic
// =========================================================================
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sourceFilter = trim($_GET['source'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(name LIKE :search OR email LIKE :search OR phone LIKE :search OR brand LIKE :search OR message LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($statusFilter) && in_array($statusFilter, ['new', 'contacted', 'converted', 'closed'])) {
    $where[] = "status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($sourceFilter)) {
    $where[] = "source = :source";
    $params[':source'] = $sourceFilter;
}

if (!empty($dateFrom)) {
    $where[] = "DATE(created_at) >= :dateFrom";
    $params[':dateFrom'] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "DATE(created_at) <= :dateTo";
    $params[':dateTo'] = $dateTo;
}

$whereClause = implode(' AND ', $where);

// Count filtered leads
$stmtCount = $db->prepare("SELECT COUNT(*) FROM leads WHERE {$whereClause}");
$stmtCount->execute($params);
$filteredCount = (int)$stmtCount->fetchColumn();

// Pagination
$perPage = 15;
$totalPages = max(1, (int)ceil($filteredCount / $perPage));
$currentPage = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($currentPage - 1) * $perPage;

// Fetch leads
$stmtLeads = $db->prepare("
    SELECT * 
    FROM leads 
    WHERE {$whereClause} 
    ORDER BY id DESC 
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $stmtLeads->bindValue($k, $v);
}
$stmtLeads->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtLeads->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtLeads->execute();
$leads = $stmtLeads->fetchAll();

// Build query string for pagination preserving filters
$filterParams = $_GET;
unset($filterParams['page'], $filterParams['export']);
$filterQueryStr = http_build_query($filterParams);
$pageLinkBase = ADMIN_URL . '/leads?' . ($filterQueryStr ? $filterQueryStr . '&' : '') . 'page=';

include CMS_ROOT . '/admin/includes/header.php';
?>

<style>
  /* Leads Table Responsive & Layout Polish */
  .leads-table-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    position: relative;
    scrollbar-width: thin;
    scrollbar-color: var(--accent-mint) rgba(0, 18, 15, 0.4);
  }

  .leads-table-container::-webkit-scrollbar {
    height: 8px;
  }
  .leads-table-container::-webkit-scrollbar-track {
    background: rgba(0, 18, 15, 0.4);
    border-radius: 4px;
  }
  .leads-table-container::-webkit-scrollbar-thumb {
    background: var(--border-sage);
    border-radius: 4px;
  }
  .leads-table-container::-webkit-scrollbar-thumb:hover {
    background: var(--accent-mint);
  }

  .leads-table {
    width: 100%;
    min-width: 1080px; /* Guarantees table columns have ample room on all viewport sizes */
    border-collapse: collapse;
    table-layout: auto;
  }

  .leads-table th {
    padding: 1rem 1.15rem;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border-sage);
    background: rgba(0, 18, 15, 0.4);
    font-weight: 700;
    white-space: nowrap;
    text-align: left;
  }

  .leads-table td {
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border-sage-subtle);
    vertical-align: top;
    font-size: 0.9rem;
    line-height: 1.5;
  }

  .leads-table tbody tr {
    transition: background-color 0.15s ease;
  }
  .leads-table tbody tr:hover td {
    background-color: var(--bg-surface-hover);
  }

  /* Specific column widths & wrapping definitions */
  .col-id { width: 65px; min-width: 65px; }
  .col-contact { width: 230px; min-width: 210px; max-width: 260px; }
  .col-service { width: 200px; min-width: 180px; max-width: 230px; }
  .col-message { min-width: 260px; max-width: 380px; }
  .col-source { width: 140px; min-width: 130px; }
  .col-date { width: 120px; min-width: 110px; white-space: nowrap; }
  .col-status { width: 140px; min-width: 130px; }
  .col-confirmation { width: 115px; min-width: 105px; }
  .col-actions { width: 105px; min-width: 105px; text-align: right !important; }

  /* Message preview box */
  .lead-message-preview {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
    font-size: 0.85rem;
    color: #cbd5e1;
    line-height: 1.45;
    margin-bottom: 0.35rem;
  }

  .lead-modal-view-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.78rem;
    color: var(--accent-mint);
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 2px;
  }
  .lead-modal-view-link:hover {
    color: #fff;
  }

  /* Responsive tweaks for filters on small screens */
  @media (max-width: 768px) {
    .lead-toolbar-form {
      flex-direction: column;
      align-items: stretch !important;
    }
    .lead-toolbar-item {
      width: 100% !important;
      min-width: 100% !important;
    }
    .lead-date-group {
      width: 100%;
      display: flex;
      gap: 0.4rem;
    }
    .lead-date-group input {
      flex: 1;
    }
  }
</style>

<div class="page-header-bar">
  <div class="page-header-title-wrap">
    <h1>Lead Management System</h1>
    <p class="page-header-subtitle">
      Track, filter, convert, and respond to incoming creative production enquiries across Homepage and Contact channels.
    </p>
  </div>
  <div class="page-header-actions">
    <a href="<?= ADMIN_URL ?>/leads?export=csv<?= $filterQueryStr ? '&' . htmlspecialchars($filterQueryStr) : '' ?>" class="btn btn-secondary" title="Export Current View to CSV">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
      </svg>
      <span>Export CSV</span>
    </a>
  </div>
</div>

<!-- KPI Metrics Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Total Leads</div>
      <div class="stat-value"><?= number_format($totalLeads) ?></div>
    </div>
    <div class="stat-icon">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">New Enquiries</div>
      <div class="stat-value" style="color: var(--accent-mint);"><?= number_format($newLeads) ?></div>
    </div>
    <div class="stat-icon" style="background: rgba(54, 217, 176, 0.1); color: var(--accent-mint); border-color: rgba(54, 217, 176, 0.3);">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Contacted</div>
      <div class="stat-value" style="color: #60a5fa;"><?= number_format($contactedLeads) ?></div>
    </div>
    <div class="stat-icon" style="background: rgba(96, 165, 250, 0.1); color: #60a5fa; border-color: rgba(96, 165, 250, 0.3);">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Converted Clients</div>
      <div class="stat-value" style="color: #34d399;"><?= number_format($convertedLeads) ?></div>
    </div>
    <div class="stat-icon" style="background: rgba(52, 211, 153, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.3);">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <div class="stat-label">Closed / Archived</div>
      <div class="stat-value" style="color: var(--text-muted);"><?= number_format($closedLeads) ?></div>
    </div>
    <div class="stat-icon" style="background: rgba(255, 255, 255, 0.05); color: var(--text-muted); border-color: rgba(255, 255, 255, 0.1);">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
    </div>
  </div>
</div>

<!-- Search, Filter & Bulk Toolbar -->
<div class="panel" style="margin-bottom: 1.5rem;">
  <div style="padding: 1.25rem 1.5rem; background: rgba(0, 18, 15, 0.4);">
    <form method="GET" action="<?= ADMIN_URL ?>/leads" class="lead-toolbar-form" style="display: flex; flex-wrap: wrap; gap: 0.85rem; align-items: center;">
      <!-- Search Input -->
      <div class="lead-toolbar-item" style="flex-grow: 1; min-width: 220px;">
        <input 
          type="text" 
          name="search" 
          value="<?= htmlspecialchars($search) ?>" 
          placeholder="Search by name, email, phone, brand..." 
          class="form-control"
          style="width: 100%;"
        >
      </div>

      <!-- Status Filter -->
      <div class="lead-toolbar-item" style="min-width: 140px;">
        <select name="status" class="form-control" style="width: 100%;">
          <option value="">All Statuses</option>
          <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>New</option>
          <option value="contacted" <?= $statusFilter === 'contacted' ? 'selected' : '' ?>>Contacted</option>
          <option value="converted" <?= $statusFilter === 'converted' ? 'selected' : '' ?>>Converted</option>
          <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
      </div>

      <!-- Source Filter -->
      <div class="lead-toolbar-item" style="min-width: 170px;">
        <select name="source" class="form-control" style="width: 100%;">
          <option value="">All Sources</option>
          <option value="website_enquiry_form" <?= $sourceFilter === 'website_enquiry_form' ? 'selected' : '' ?>>Website Enquiry Form</option>
          <option value="Homepage Inquiry Form" <?= $sourceFilter === 'Homepage Inquiry Form' ? 'selected' : '' ?>>Homepage Inquiry Form</option>
          <option value="Contact Page Form" <?= $sourceFilter === 'Contact Page Form' ? 'selected' : '' ?>>Contact Page Form</option>
        </select>
      </div>

      <!-- Date Range -->
      <div class="lead-toolbar-item lead-date-group" style="display: flex; gap: 0.4rem; align-items: center;">
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control" title="From Date">
        <span style="color: var(--text-muted);">&ndash;</span>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control" title="To Date">
      </div>

      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <?php if (!empty($search) || !empty($statusFilter) || !empty($sourceFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
        <a href="<?= ADMIN_URL ?>/leads" class="btn btn-secondary btn-sm" style="text-decoration: none;">Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Leads Data Table -->
<div class="panel">
  <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h2 class="panel-title">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
      </svg>
      <span>Enquiries &amp; Submissions (<?= number_format($filteredCount) ?> total)</span>
    </h2>
  </div>

  <div class="table-responsive leads-table-container">
    <table class="table-editorial leads-table">
      <thead>
        <tr>
          <th class="col-id">ID</th>
          <th class="col-contact">Lead / Contact</th>
          <th class="col-service">Service &amp; Brand</th>
          <th class="col-message">Message Brief</th>
          <th class="col-source">Source</th>
          <th class="col-date">Date</th>
          <th class="col-status">Lead Status</th>
          <th class="col-confirmation">Confirmation</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($leads)): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
              <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="opacity: 0.4; margin-bottom: 0.75rem;"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
              <div>No leads found matching your search and filter criteria.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($leads as $lead): ?>
            <tr>
              <td class="col-id"><span style="font-family: monospace; color: var(--text-muted); font-size: 0.85rem;">#<?= $lead['id'] ?></span></td>
              
              <!-- Lead / Contact Column -->
              <td class="col-contact">
                <div style="font-weight: 600; color: #fff; font-size: 0.95rem; word-break: break-word; line-height: 1.35; margin-bottom: 0.25rem;">
                  <?= htmlspecialchars($lead['name']) ?>
                </div>
                <div style="font-size: 0.84rem; color: var(--accent-mint); word-break: break-all; line-height: 1.3;">
                  <a href="mailto:<?= htmlspecialchars($lead['email']) ?>" style="color: inherit; text-decoration: none;" title="Email <?= htmlspecialchars($lead['name']) ?>">
                    <?= htmlspecialchars($lead['email']) ?>
                  </a>
                </div>
                <?php if (!empty($lead['phone'])): ?>
                  <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem; word-break: break-word;">
                    <a href="tel:<?= htmlspecialchars($lead['phone']) ?>" style="color: inherit; text-decoration: none;">
                      <?= htmlspecialchars($lead['phone']) ?>
                    </a>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Service & Brand Column -->
              <td class="col-service">
                <div style="font-weight: 600; font-size: 0.88rem; color: #f1f5f9; line-height: 1.35; margin-bottom: 0.25rem; word-break: break-word;">
                  <?= htmlspecialchars($lead['service'] ?: 'General Creative') ?>
                </div>
                <?php if (!empty($lead['brand'])): ?>
                  <div style="font-size: 0.8rem; color: #94a3b8; line-height: 1.3; margin-bottom: 0.15rem; word-break: break-word;">
                    <strong style="color: var(--text-muted);">Brand:</strong> <?= htmlspecialchars($lead['brand']) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($lead['budget'])): ?>
                  <div style="font-size: 0.78rem; color: var(--text-dim); line-height: 1.25;">
                    <strong style="color: var(--text-muted);">Budget:</strong> <?= htmlspecialchars($lead['budget']) ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Message Brief Column -->
              <td class="col-message">
                <div class="lead-message-preview" title="<?= htmlspecialchars($lead['message']) ?>">
                  <?= htmlspecialchars($lead['message']) ?>
                </div>
                <button type="button" class="lead-modal-view-link" onclick="openLeadModal(<?= htmlspecialchars(json_encode($lead)) ?>)">
                  <span>Read full brief &rarr;</span>
                </button>
                <?php if (!empty($lead['internal_notes'])): ?>
                  <div style="font-size: 0.76rem; color: #fbbf24; margin-top: 0.4rem; padding: 0.3rem 0.5rem; background: rgba(251, 191, 36, 0.08); border-left: 2px solid #fbbf24; border-radius: 2px; line-height: 1.35; word-break: break-word;">
                    <strong>Note:</strong> <?= htmlspecialchars(substr($lead['internal_notes'], 0, 70)) ?><?= strlen($lead['internal_notes']) > 70 ? '...' : '' ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Source Column -->
              <td class="col-source">
                <?php if ($lead['source'] === 'website_enquiry_form' || $lead['source'] === 'Website Enquiry Form'): ?>
                  <span class="badge" style="background: rgba(54, 217, 176, 0.15); color: #36d9b0; border: 1px solid rgba(54, 217, 176, 0.4); display: inline-block; white-space: nowrap; font-weight: 600;">Website Enquiry Form</span>
                <?php elseif ($lead['source'] === 'Contact Page Form'): ?>
                  <span class="badge" style="background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); display: inline-block; white-space: nowrap;">Contact Page</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); display: inline-block; white-space: nowrap;">Homepage</span>
                <?php endif; ?>
              </td>

              <!-- Date Column -->
              <td class="col-date" style="font-size: 0.85rem; color: var(--text-muted);">
                <div style="color: #e2e8f0; font-weight: 500;"><?= date('M j, Y', strtotime($lead['created_at'])) ?></div>
                <div style="font-size: 0.78rem; opacity: 0.75;"><?= date('g:i A', strtotime($lead['created_at'])) ?></div>
              </td>

              <!-- Status Selector Column -->
              <td class="col-status">
                <form method="POST" action="<?= ADMIN_URL ?>/leads<?= $filterQueryStr ? '?' . htmlspecialchars($filterQueryStr) : '' ?>" style="margin: 0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                  <select name="status" onchange="this.form.submit()" class="form-control" style="font-size: 0.8rem; padding: 0.3rem 0.5rem; height: auto; width: 100%;
                    <?php if ($lead['status'] === 'new'): ?>
                      background: rgba(54, 217, 176, 0.15); color: var(--accent-mint); border-color: rgba(54, 217, 176, 0.4);
                    <?php elseif ($lead['status'] === 'contacted'): ?>
                      background: rgba(96, 165, 250, 0.15); color: #60a5fa; border-color: rgba(96, 165, 250, 0.4);
                    <?php elseif ($lead['status'] === 'converted'): ?>
                      background: rgba(52, 211, 153, 0.15); color: #34d399; border-color: rgba(52, 211, 153, 0.4);
                    <?php else: ?>
                      background: rgba(255, 255, 255, 0.08); color: var(--text-muted); border-color: rgba(255, 255, 255, 0.2);
                    <?php endif; ?>
                  ">
                    <option value="new" <?= $lead['status'] === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="contacted" <?= $lead['status'] === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                    <option value="converted" <?= $lead['status'] === 'converted' ? 'selected' : '' ?>>Converted</option>
                    <option value="closed" <?= $lead['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                  </select>
                </form>
              </td>

              <!-- Confirmation Status Column -->
              <td class="col-confirmation">
                <?php if ($lead['email_delivery_status'] === 'sent'): ?>
                  <span class="badge" style="background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); display: inline-block; white-space: nowrap;" title="Delivered successfully">Sent</span>
                <?php elseif ($lead['email_delivery_status'] === 'failed'): ?>
                  <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); display: inline-block; white-space: nowrap;" title="<?= htmlspecialchars($lead['email_error_message'] ?: 'Delivery failed') ?>">Failed</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(234, 179, 8, 0.15); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3); display: inline-block; white-space: nowrap;">Pending</span>
                <?php endif; ?>
              </td>

              <!-- Actions Column -->
              <td class="col-actions" style="white-space: nowrap;">
                <div style="display: inline-flex; gap: 0.35rem; align-items: center; justify-content: flex-end;">
                  <!-- View Details Button -->
                  <button type="button" class="btn btn-secondary btn-sm" onclick="openLeadModal(<?= htmlspecialchars(json_encode($lead)) ?>)" title="View Full Details" style="padding: 0.4rem 0.6rem;">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>

                  <!-- Delete Lead Form -->
                  <form method="POST" action="<?= ADMIN_URL ?>/leads" style="display: inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to permanently delete Lead #<?= $lead['id'] ?> (<?= htmlspecialchars(addslashes($lead['name'])) ?>)? This cannot be undone.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Lead" style="padding: 0.4rem 0.6rem;">
                      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination Bar -->
  <?php if ($totalPages > 1): ?>
    <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-sage); background: rgba(0, 18, 15, 0.2);">
      <div style="font-size: 0.85rem; color: var(--text-muted);">
        Showing Page <?= $currentPage ?> of <?= $totalPages ?> (<?= number_format($filteredCount) ?> total leads)
      </div>
      <div style="display: flex; gap: 0.35rem;">
        <?php if ($currentPage > 1): ?>
          <a href="<?= $pageLinkBase . ($currentPage - 1) ?>" class="btn btn-secondary btn-sm">&larr; Prev</a>
        <?php endif; ?>
        
        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
          <a href="<?= $pageLinkBase . $p ?>" class="btn btn-sm <?= $p === $currentPage ? 'btn-primary' : 'btn-secondary' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
          <a href="<?= $pageLinkBase . ($currentPage + 1) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- =========================================================================
     LEAD DETAILS & NOTES MODAL
     ========================================================================= -->
<div id="leadModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;">
  <div class="panel" style="max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7); border: 1px solid var(--accent-mint);">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-sage);">
      <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff;">
        Lead Details <span id="modalLeadId" style="color: var(--accent-mint); font-family: monospace;"></span>
      </h3>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeLeadModal()" style="font-size: 1.25rem; line-height: 1; padding: 0.2rem 0.6rem;">&times;</button>
    </div>

    <div style="padding: 1.5rem;">
      <!-- Contact & Submission Meta Grid -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px;">
        <div>
          <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Full Name</label>
          <div id="modalLeadName" style="font-size: 1.05rem; font-weight: 600; color: #fff;"></div>
        </div>
        <div>
          <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Email Address</label>
          <div id="modalLeadEmail" style="font-size: 0.95rem; color: var(--accent-mint);"></div>
        </div>
        <div>
          <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Phone Number</label>
          <div id="modalLeadPhone" style="font-size: 0.95rem; color: #cbd5e1;"></div>
        </div>
        <div>
          <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Brand / Company</label>
          <div id="modalLeadBrand" style="font-size: 0.95rem; color: #cbd5e1;"></div>
        </div>
        <div>
          <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Interested Service</label>
          <div id="modalLeadService" style="font-size: 0.95rem; color: #fff;"></div>
        </div>
        <div>
          <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Budget Range</label>
          <div id="modalLeadBudget" style="font-size: 0.95rem; color: #cbd5e1;"></div>
        </div>
        <div>
          <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Channel Source</label>
          <div id="modalLeadSource" style="font-size: 0.9rem; color: #38bdf8;"></div>
        </div>
        <div>
          <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Submitted Date</label>
          <div id="modalLeadDate" style="font-size: 0.9rem; color: var(--text-muted);"></div>
        </div>
      </div>

      <!-- Project Message -->
      <div style="margin-bottom: 1.5rem;">
        <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 0.4rem;">Project Message Brief</label>
        <div id="modalLeadMessage" style="background: rgba(0,0,0,0.3); border: 1px solid var(--border-sage); padding: 1rem; border-radius: 8px; font-size: 0.9rem; line-height: 1.6; color: #e2e8f0; white-space: pre-wrap; word-break: break-word;"></div>
      </div>

      <!-- Email Confirmation Status Card -->
      <div style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(0,18,15,0.4); border: 1px solid var(--border-sage); border-radius: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
          <span style="font-size: 0.85rem; font-weight: 600; color: #fff;">Automatic Confirmation Email:</span>
          <span id="modalLeadEmailBadge"></span>
        </div>
        <div id="modalLeadEmailDetails" style="font-size: 0.8rem; color: var(--text-muted);"></div>

        <form method="POST" action="<?= ADMIN_URL ?>/leads" style="margin-top: 0.75rem;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="retry_email">
          <input type="hidden" id="modalRetryLeadId" name="lead_id" value="">
          <button type="submit" class="btn btn-secondary btn-sm" title="Re-dispatch confirmation email to visitor">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            <span>Retry Visitor Confirmation Email</span>
          </button>
        </form>
      </div>

      <!-- Internal Admin Notes Editor -->
      <form method="POST" action="<?= ADMIN_URL ?>/leads">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_notes">
        <input type="hidden" id="modalNotesLeadId" name="lead_id" value="">
        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="modalInternalNotes" class="form-label" style="font-size: 0.85rem; font-weight: 600;">Internal Admin Notes</label>
          <textarea id="modalInternalNotes" name="internal_notes" class="form-control" rows="3" placeholder="Add private notes on client communication, quote amounts, calls, etc..."></textarea>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
          <button type="button" class="btn btn-secondary btn-sm" onclick="closeLeadModal()">Close</button>
          <button type="submit" class="btn btn-primary btn-sm">Save Notes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openLeadModal(lead) {
  document.getElementById('modalLeadId').textContent = '#' + lead.id;
  document.getElementById('modalNotesLeadId').value = lead.id;
  document.getElementById('modalRetryLeadId').value = lead.id;

  document.getElementById('modalLeadName').textContent = lead.name || 'N/A';
  document.getElementById('modalLeadEmail').textContent = lead.email || 'N/A';
  document.getElementById('modalLeadPhone').textContent = lead.phone || 'N/A';
  document.getElementById('modalLeadBrand').textContent = lead.brand || 'N/A';
  document.getElementById('modalLeadService').textContent = lead.service || 'General Creative Discussion';
  document.getElementById('modalLeadBudget').textContent = lead.budget || 'Not specified';
  const displaySource = (lead.source === 'website_enquiry_form' || lead.source === 'Website Enquiry Form') ? 'Website Enquiry Form' : (lead.source || 'Website Form');
  document.getElementById('modalLeadSource').textContent = displaySource;
  document.getElementById('modalLeadDate').textContent = lead.created_at || 'N/A';
  const displayMsg = (lead.message && lead.message.trim() !== '') ? lead.message : 'No message provided';
  document.getElementById('modalLeadMessage').textContent = displayMsg;
  document.getElementById('modalInternalNotes').value = lead.internal_notes || '';

  // Email status badge
  const badgeEl = document.getElementById('modalLeadEmailBadge');
  const detailsEl = document.getElementById('modalLeadEmailDetails');
  
  if (lead.email_delivery_status === 'sent') {
    badgeEl.innerHTML = '<span class="badge" style="background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3);">Sent</span>';
    detailsEl.textContent = 'Delivered at: ' + (lead.last_email_attempt_at || 'N/A') + ' (Attempts: ' + lead.email_attempts + ')';
  } else if (lead.email_delivery_status === 'failed') {
    badgeEl.innerHTML = '<span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);">Failed</span>';
    detailsEl.textContent = 'Last Attempt: ' + (lead.last_email_attempt_at || 'N/A') + ' | Attempts: ' + lead.email_attempts + ' | Error: ' + (lead.email_error_message || 'Delivery error');
  } else {
    badgeEl.innerHTML = '<span class="badge" style="background: rgba(234, 179, 8, 0.15); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3);">Pending</span>';
    detailsEl.textContent = 'Email delivery has not yet been processed.';
  }

  const modal = document.getElementById('leadModal');
  modal.style.display = 'flex';
}

function closeLeadModal() {
  document.getElementById('leadModal').style.display = 'none';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeLeadModal();
});
</script>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>
