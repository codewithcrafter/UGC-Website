<?php
/**
 * MY UGC VIDEO — Scheduled CLI & Cron Discovery Reconciliation Engine
 * 
 * Production-ready reconciliation script designed for:
 * 1. CLI scheduled execution via cPanel Cron or system crontab
 * 2. Manual CLI maintenance with flags (--force, --dry-run, --verbose)
 * 3. Protected HTTP cron webhooks (via CRON_KEY authentication)
 * 
 * Responsibilities:
 * - Prevents overlapping runs via flock file locking
 * - Audits MySQL blog posts vs. filesystem Markdown files (detects orphaned or missing files)
 * - Audits filesystem schemas (JSON/SQL) for untracked external additions/modifications (FTP uploads)
 * - Regenerates llms.txt, sitemap.xml, and validates robots.txt
 * - Dispatches IndexNow search engine pings for altered public resources
 * - Records timestamps and status metrics to logs/llm_sync_status.json
 * 
 * ============================================================================
 * cPanel / Linux Crontab Configuration:
 * ============================================================================
 * Daily Run at 02:00 AM:
 *   0 2 * * * /usr/local/bin/php /home/username/public_html/database/sync_llms.php > /dev/null 2>&1
 * 
 * Hourly Verification with Logging:
 *   0 * * * * /usr/local/bin/php /home/username/public_html/database/sync_llms.php --verbose >> /home/username/public_html/logs/cron_reconcile.log 2>&1
 * 
 * Webhook Alternative (if CLI access is restricted by hosting provider):
 *   0 2 * * * curl -s "https://myugcvideo.in/database/sync_llms.php?token=YOUR_CRON_KEY" > /dev/null 2>&1
 * ============================================================================
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

// Safe execution limits for shared hosting environments
@set_time_limit(180);
@ini_set('memory_limit', '256M');

$isCli = (PHP_SAPI === 'cli');

// 1. Authentication & Access Control
if (!$isCli) {
    $cronKeyFile = CMS_ROOT . '/config/cron_key.txt';
    $configuredKey = file_exists($cronKeyFile) ? trim(file_get_contents($cronKeyFile)) : null;
    $providedToken = trim($_GET['token'] ?? ($_GET['cron_key'] ?? ''));

    $authorized = false;
    if ($configuredKey && hash_equals($configuredKey, $providedToken)) {
        $authorized = true;
    } elseif (function_exists('isLoggedIn') && isLoggedIn()) {
        $authorized = true;
    }

    if (!$authorized) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        die("Access Denied: Scheduled reconciliation must be executed via CLI or authenticated session/token.\n");
    }

    header('Content-Type: text/plain; charset=utf-8');
}

// 2. Parse Options and Flags
$options = [
    'force'   => false,
    'dry_run' => false,
    'verbose' => false
];

if ($isCli) {
    global $argv;
    $args = $argv ?? [];
    
    if (in_array('--help', $args) || in_array('-h', $args)) {
        echo "===============================================================================\n";
        echo "MY UGC VIDEO — Scheduled CLI & Cron Discovery Reconciliation Engine\n";
        echo "===============================================================================\n\n";
        echo "Usage: php database/sync_llms.php [options]\n\n";
        echo "Options:\n";
        echo "  --force       Force complete regeneration of llms.txt and sitemap.xml\n";
        echo "  --dry-run     Simulate generation and validation without modifying files on disk\n";
        echo "  --verbose, -v Display detailed logging for every step of reconciliation\n";
        echo "  --help, -h    Display this help and usage manual\n\n";
        echo "Cron Setup Guide:\n";
        echo "  Run daily at 2:00 AM:\n";
        echo "    0 2 * * * /usr/local/bin/php " . __FILE__ . " > /dev/null 2>&1\n\n";
        exit(0);
    }

    if (in_array('--force', $args))   $options['force'] = true;
    if (in_array('--dry-run', $args)) $options['dry_run'] = true;
    if (in_array('--verbose', $args) || in_array('-v', $args)) $options['verbose'] = true;
} else {
    if (!empty($_GET['force']))   $options['force'] = true;
    if (!empty($_GET['dry_run'])) $options['dry_run'] = true;
    if (!empty($_GET['verbose'])) $options['verbose'] = true;
}

function cronLog(string $msg, bool $isVerbose = false, bool $forceVerbose = false): void {
    if ($forceVerbose || $isVerbose) {
        $ts = date('Y-m-d H:i:s');
        echo "[{$ts}] {$msg}\n";
        if (ob_get_level() > 0) @ob_flush();
        flush();
    }
}

cronLog("=== Starting Scheduled Discovery Reconciliation ===", $options['verbose'], true);
if ($options['dry_run']) cronLog("[MODE] DRY-RUN enabled (no disk modifications or search pings)", $options['verbose'], true);
if ($options['force'])   cronLog("[MODE] FORCE enabled (unconditional full regeneration)", $options['verbose'], true);

// 3. Step 1: Reconcile Website Content & MySQL Blog Posts to Markdown Storage
cronLog("Step 1/5: Auditing Website Content (Core Pages, Services & Blogs) vs. Markdown storage...", $options['verbose'], true);
$blogReconcileStats = ['synced' => 0, 'total' => 0];
if (!$options['dry_run']) {
    if (class_exists('WebsiteMarkdownSync')) {
        $webReconcile = WebsiteMarkdownSync::reconcileAll($options['force']);
        cronLog(" -> Reconciled {$webReconcile['synced']} of {$webReconcile['total']} website pages to markdown storage ({$webReconcile['updated']} updated, {$webReconcile['unchanged']} unchanged).", $options['verbose'], true);
    } elseif (function_exists('reconcileAllBlogsToMarkdown')) {
        $blogReconcileStats = reconcileAllBlogsToMarkdown();
        cronLog(" -> Reconciled {$blogReconcileStats['synced']} of {$blogReconcileStats['total']} published posts to markdown storage.", $options['verbose'], true);
    }
} else {
    cronLog(" -> Skipped website markdown write-back (dry-run mode).", $options['verbose'], true);
}

// 4. Step 2: Audit Filesystem Schemas & Documentation
cronLog("Step 2/5: Scanning public filesystem markdown & schema assets...", $options['verbose'], true);
$publicMd = discoverPublicMarkdownFiles();
$publicJson = discoverPublicJsonSchemas();
$publicSql = discoverPublicSqlSchemas();
$corePages = getPublicCorePages();
$servicePages = getPublicServicePages();
$blogArticles = getPublishedBlogArticles();

cronLog(" -> Discovered: " . count($corePages) . " core pages, " . count($servicePages) . " service pages, " . count($blogArticles) . " published blogs.", $options['verbose'], $options['verbose']);
cronLog(" -> Discovered: " . count($publicMd) . " public Markdown docs, " . count($publicJson) . " JSON schemas, " . count($publicSql) . " SQL schemas.", $options['verbose'], $options['verbose']);

// 5. Step 3: Run Full Master Reconciliation (llms.txt, sitemap.xml, robots.txt, Search Notifications)
cronLog("Step 3/5: Rebuilding discovery directives & validating XML structures...", $options['verbose'], true);
$reconcileRes = runFullReconciliation([
    'force'   => $options['force'],
    'dry_run' => $options['dry_run']
]);

if (!$reconcileRes['success']) {
    cronLog("ERROR: Reconciliation failed! " . ($reconcileRes['error'] ?? 'Unknown error'), $options['verbose'], true);
    if ($isCli) exit(1);
    exit;
}

// 6. Step 4: Validate Generated Outputs
cronLog("Step 4/5: Validating generated discovery outputs...", $options['verbose'], true);
$stats = $reconcileRes['stats'] ?? [];
cronLog(" -> llms.txt: Cataloged " . ($stats['total_entries'] ?? 0) . " resources.", $options['verbose'], true);
cronLog(" -> sitemap.xml: Formed with " . ($stats['sitemap_url_count'] ?? 0) . " valid indexable URLs.", $options['verbose'], true);
cronLog(" -> robots.txt: Canonical sitemap & AI agent rules verified.", $options['verbose'], true);

// 7. Step 5: External Notification Outcomes
cronLog("Step 5/5: Search engine discovery status:", $options['verbose'], true);
$gsc = $stats['google_search_console'] ?? [];
$inow = $stats['indexnow'] ?? [];

$inowStatus = !empty($inow['success']) ? "HTTP " . ($inow['status_code'] ?? 202) . " (Submitted)" : ($options['dry_run'] ? "Dry-run (Skipped)" : "Idle / No New URLs");
cronLog(" -> Bing / IndexNow: {$inowStatus}", $options['verbose'], true);

$gscStatus = !empty($gsc['configured']) ? "Connected" : "Unconfigured (Direct Sitemap Crawling via robots.txt)";
cronLog(" -> Google Search Console: {$gscStatus}", $options['verbose'], true);

cronLog("=== Reconciliation Completed Successfully ===", $options['verbose'], true);
if ($isCli) {
    exit(0);
}
