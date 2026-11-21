<?php
/**
 * MY UGC VIDEO — Admin CMS Announcement Bar Management
 * Manages promotional banner text, background/text colors, destination link, and enable/disable toggle.
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';
requireAuth();

$activeNav = 'announcement';
$pageTitle = 'Announcement Bar Settings';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => ADMIN_URL . '/dashboard'],
    ['name' => 'Announcement Bar', 'url' => ADMIN_URL . '/announcement']
];

$db = getDb();
$successMsg = '';
$errorMsg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Security validation failed (CSRF token invalid). Please refresh and try again.';
    } else {
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        $brandText = trim($_POST['brand_text'] ?? '');
        $promoMessage = trim($_POST['promotional_message'] ?? '');
        $bgColor = trim($_POST['bg_color'] ?? '#00221E');
        $textColor = trim($_POST['text_color'] ?? '#36D9B0');
        $redirectUrl = trim($_POST['redirect_url'] ?? 'contact');

        // Validation
        if ($brandText === '') {
            $errorMsg = 'Brand text cannot be empty.';
        } elseif ($promoMessage === '') {
            $errorMsg = 'Promotional message cannot be empty.';
        } elseif (!preg_match('/^#([a-f0-9]{3}){1,2}$/i', $bgColor) && !preg_match('/^[a-z0-9_-]+$/i', $bgColor)) {
            $errorMsg = 'Background color must be a valid HEX color code (e.g. #00221E).';
        } elseif (!preg_match('/^#([a-f0-9]{3}){1,2}$/i', $textColor) && !preg_match('/^[a-z0-9_-]+$/i', $textColor)) {
            $errorMsg = 'Text color must be a valid HEX color code (e.g. #36D9B0).';
        } else {
            // Update settings
            try {
                $stmt = $db->prepare("UPDATE announcement_settings SET 
                    is_enabled = ?, 
                    brand_text = ?, 
                    promotional_message = ?, 
                    bg_color = ?, 
                    text_color = ?, 
                    redirect_url = ?, 
                    updated_at = NOW() 
                    WHERE id = 1");
                $stmt->execute([$isEnabled, $brandText, $promoMessage, $bgColor, $textColor, $redirectUrl]);
                $successMsg = 'Announcement bar settings updated successfully! Changes reflect on the live website.';
            } catch (Throwable $e) {
                $errorMsg = 'Database update error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch current settings with automatic table creation safeguard for production
try {
    $settings = $db->query("SELECT * FROM announcement_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $tableEx) {
    $db->exec("CREATE TABLE IF NOT EXISTS announcement_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        brand_text VARCHAR(100) NOT NULL DEFAULT 'MY UGC VIDEO',
        promotional_message VARCHAR(255) NOT NULL DEFAULT 'Let\'s Create Videos That Make Your Brand Stand Out',
        bg_color VARCHAR(30) NOT NULL DEFAULT '#00221E',
        text_color VARCHAR(30) NOT NULL DEFAULT '#36D9B0',
        redirect_url VARCHAR(255) NOT NULL DEFAULT 'contact',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("INSERT IGNORE INTO announcement_settings (id, is_enabled, brand_text, promotional_message, bg_color, text_color, redirect_url) VALUES (1, 1, 'MY UGC VIDEO', 'Let\'s Create Videos That Make Your Brand Stand Out', '#00221E', '#36D9B0', 'contact')");

    $settings = $db->query("SELECT * FROM announcement_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
if (!$settings) {
    $settings = [
        'is_enabled' => 1,
        'brand_text' => 'MY UGC VIDEO',
        'promotional_message' => "Let's Create Videos That Make Your Brand Stand Out",
        'bg_color' => '#00221E',
        'text_color' => '#36D9B0',
        'redirect_url' => 'contact'
    ];
}

include CMS_ROOT . '/admin/includes/header.php';
?>

<div class="page-header-bar">
  <div class="page-header-title-wrap">
    <h1>Announcement Bar Settings</h1>
    <p class="page-header-subtitle">
      Control the dynamic promotional bar displayed directly below the top contact bar across the website.
    </p>
  </div>
  <div class="page-header-actions">
    <a href="<?= SITE_URL ?>" target="_blank" class="btn btn-secondary">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
      <span>View Live Website</span>
    </a>
  </div>
</div>

<?php if (!empty($successMsg)): ?>
  <div style="
    display: flex;
    align-items: center;
    gap: 0 !important;
    margin-bottom: 1.5rem;
    background: rgba(54, 217, 176, 0.15);
    border: 1px solid #36d9b0;
    color: #36d9b0;
    padding: 1rem 1.25rem;
    border-radius: 8px;
  ">
    <strong style="display:inline; margin:0 !important; padding:0 !important;">Success:</strong><span style="display:inline; margin:0 !important; padding:0 !important;"> <?= e(trim($successMsg)) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
  <div style="
    display: flex;
    align-items: center;
    gap: 0 !important;
    margin-bottom: 1.5rem;
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid #ef4444;
    color: #ef4444;
    padding: 1rem 1.25rem;
    border-radius: 8px;
  ">
    <strong style="display:inline; margin:0 !important; padding:0 !important;">Error:</strong><span style="display:inline; margin:0 !important; padding:0 !important;"> <?= e(trim($errorMsg)) ?></span>
  </div>
<?php endif; ?>

<!-- Live Preview Box -->
<div class="admin-card" style="margin-bottom: 2rem;">
  <div class="admin-card-header">
    <h2 class="card-title">Live Preview</h2>
    <span class="preview-badge" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent-mint);">Simulated Display</span>
  </div>
  <div class="admin-card-body" style="padding: 1.5rem;">
    <div id="liveAnnouncementPreview" style="background: <?= e($settings['bg_color']) ?>; color: <?= e($settings['text_color']) ?>; padding: 0.75rem 1.5rem; border-radius: 8px; display: <?= $settings['is_enabled'] ? 'flex' : 'none' ?>; align-items: center; justify-content: center; gap: 0.85rem; font-family: var(--font-body); font-size: 0.9rem; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 14px rgba(0,0,0,0.3);">
      <span id="previewBrandText" style="font-weight: 800; font-family: var(--font-heading); background: rgba(54, 217, 176, 0.2); padding: 0.2rem 0.6rem; border-radius: 4px; letter-spacing: 0.05em; font-size: 0.75rem;"><?= e($settings['brand_text']) ?></span>
      <span id="previewPromoMessage"><?= e($settings['promotional_message']) ?></span>
      <span style="font-size: 1rem;">&rarr;</span>
    </div>
    <div id="liveAnnouncementDisabledMsg" style="display: <?= $settings['is_enabled'] ? 'none' : 'block' ?>; text-align: center; color: var(--text-muted); font-style: italic; padding: 1rem;">
      (Announcement bar is currently disabled and hidden from website visitors)
    </div>
  </div>
</div>

<!-- Settings Form -->
<div class="admin-card">
  <div class="admin-card-header">
    <h2 class="card-title">Configure Announcement</h2>
  </div>
  <div class="admin-card-body" style="padding: 1.75rem;">
    <form method="POST" action="<?= ADMIN_URL ?>/announcement" id="announcementForm">
      <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

      <!-- Enable / Disable Toggle -->
      <div class="form-group" style="margin-bottom: 1.75rem;">
        <label class="form-label" style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
          <input type="checkbox" name="is_enabled" id="isEnabledToggle" value="1" <?= $settings['is_enabled'] ? 'checked' : '' ?> style="width: 20px; height: 20px; accent-color: var(--accent-mint); cursor: pointer;">
          <span style="font-size: 1rem; font-weight: 700; color: #fff;">Enable Announcement Bar on Website</span>
        </label>
        <p class="form-hint" style="color: var(--text-muted); font-size: 0.82rem; margin-top: 0.35rem;">
          When enabled, the bar appears directly below the top contact bar on the homepage and shared templates.
        </p>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Brand Badge Text -->
        <div class="form-group">
          <label for="brandText" class="form-label">Brand Badge Text *</label>
          <input type="text" id="brandText" name="brand_text" class="form-control" value="<?= e($settings['brand_text']) ?>" required placeholder="e.g. MY UGC VIDEO">
          <p class="form-hint" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.3rem;">Prominent pill tag shown at the beginning of the bar.</p>
        </div>

        <!-- Destination URL -->
        <div class="form-group">
          <label for="redirectUrl" class="form-label">Destination URL *</label>
          <input type="text" id="redirectUrl" name="redirect_url" class="form-control" value="<?= e($settings['redirect_url']) ?>" required placeholder="e.g. contact or #project-inquiry">
          <p class="form-hint" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.3rem;">Where visitors are redirected when clicking the announcement.</p>
        </div>
      </div>

      <!-- Promotional Message -->
      <div class="form-group" style="margin-bottom: 1.5rem;">
        <label for="promoMessage" class="form-label">Promotional Message *</label>
        <input type="text" id="promoMessage" name="promotional_message" class="form-control" value="<?= e($settings['promotional_message']) ?>" required placeholder="e.g. Let's Create Videos That Make Your Brand Stand Out">
        <p class="form-hint" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.3rem;">The main announcement or promotional callout message.</p>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Background Color -->
        <div class="form-group">
          <label for="bgColor" class="form-label">Background Color *</label>
          <div style="display: flex; gap: 0.75rem; align-items: center;">
            <input type="color" id="bgColorPicker" value="<?= e($settings['bg_color']) ?>" style="width: 44px; height: 44px; border: none; border-radius: 6px; cursor: pointer; background: transparent; padding: 0;">
            <input type="text" id="bgColor" name="bg_color" class="form-control" value="<?= e($settings['bg_color']) ?>" required style="flex: 1;">
          </div>
          <p class="form-hint" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.3rem;">Default brand teal is #00221E.</p>
        </div>

        <!-- Text Color -->
        <div class="form-group">
          <label for="textColor" class="form-label">Text &amp; Accent Color *</label>
          <div style="display: flex; gap: 0.75rem; align-items: center;">
            <input type="color" id="textColorPicker" value="<?= e($settings['text_color']) ?>" style="width: 44px; height: 44px; border: none; border-radius: 6px; cursor: pointer; background: transparent; padding: 0;">
            <input type="text" id="textColor" name="text_color" class="form-control" value="<?= e($settings['text_color']) ?>" required style="flex: 1;">
          </div>
          <p class="form-hint" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.3rem;">Default brand mint is #36D9B0.</p>
        </div>
      </div>

      <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 1rem;">
        <button type="submit" class="btn btn-primary btn-lg">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
          <span>Save Changes</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Interactive Live Preview Synchronization
document.addEventListener('DOMContentLoaded', () => {
  const isEnabledToggle = document.getElementById('isEnabledToggle');
  const brandTextInput = document.getElementById('brandText');
  const promoMessageInput = document.getElementById('promoMessage');
  const bgColorInput = document.getElementById('bgColor');
  const bgColorPicker = document.getElementById('bgColorPicker');
  const textColorInput = document.getElementById('textColor');
  const textColorPicker = document.getElementById('textColorPicker');

  const previewBox = document.getElementById('liveAnnouncementPreview');
  const previewDisabledMsg = document.getElementById('liveAnnouncementDisabledMsg');
  const previewBrand = document.getElementById('previewBrandText');
  const previewPromo = document.getElementById('previewPromoMessage');

  const updatePreview = () => {
    if (isEnabledToggle.checked) {
      previewBox.style.display = 'flex';
      previewDisabledMsg.style.display = 'none';
    } else {
      previewBox.style.display = 'none';
      previewDisabledMsg.style.display = 'block';
    }

    previewBrand.textContent = brandTextInput.value || 'MY UGC VIDEO';
    previewPromo.textContent = promoMessageInput.value || '';
    previewBox.style.backgroundColor = bgColorInput.value;
    previewBox.style.color = textColorInput.value;
  };

  isEnabledToggle.addEventListener('change', updatePreview);
  brandTextInput.addEventListener('input', updatePreview);
  promoMessageInput.addEventListener('input', updatePreview);

  bgColorPicker.addEventListener('input', (e) => {
    bgColorInput.value = e.target.value;
    updatePreview();
  });
  bgColorInput.addEventListener('input', (e) => {
    bgColorPicker.value = e.target.value;
    updatePreview();
  });

  textColorPicker.addEventListener('input', (e) => {
    textColorInput.value = e.target.value;
    updatePreview();
  });
  textColorInput.addEventListener('input', (e) => {
    textColorPicker.value = e.target.value;
    updatePreview();
  });
});
</script>

<?php include CMS_ROOT . '/admin/includes/footer.php'; ?>
