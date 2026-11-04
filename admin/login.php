<?php
/**
 * MY UGC VIDEO — Admin CMS Login Page
 * Dark Editorial CMS Authentication
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . ADMIN_URL . '/dashboard.php');
    exit;
}

// If no admin exists yet, redirect to setup
$db = getDb();
$adminCount = (int) $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ($adminCount === 0) {
    header('Location: ' . ADMIN_URL . '/setup.php');
    exit;
}

$error = '';
$email = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    if (!checkLoginThrottle($ip)) {
        $error = 'Too many failed login attempts from your IP. Please wait 15 minutes before trying again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            $stmt = $db->prepare("SELECT * FROM admins WHERE email = :email AND is_active = 1");
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                clearLoginAttempts($ip);
                loginAdmin($admin);
                
                $redirect = $_SESSION['redirect_after_login'] ?? (ADMIN_URL . '/dashboard.php');
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            } else {
                recordLoginAttempt($ip);
                $error = 'Invalid email address or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Sign In — MY UGC VIDEO Editorial CMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/admin/assets/admin.css">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="login-logo">
      <div class="brand-mark-lg">UGC</div>
      <h1>MY UGC VIDEO</h1>
      <p>Editorial CMS</p>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
        <span><?= e($flash['message']) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrfField() ?>

      <div class="form-group">
        <label for="email" class="form-label">Admin Email Address <span class="required">*</span></label>
        <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="admin@myugcvideo.example.com" required autofocus autocomplete="username">
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Password <span class="required">*</span></label>
        <div class="password-input-group">
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required autocomplete="current-password">
          <button type="button" id="togglePasswordBtn" class="password-toggle-btn" aria-label="Toggle password visibility">
            <svg id="eyeIcon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.9rem; font-size: 0.98rem; margin-top: 1.25rem;">
        <span>Sign In to Editorial Studio</span>
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" style="width: 18px; height: 18px;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
      </button>
    </form>

    <div style="text-align: center; margin-top: 2.25rem; padding-top: 1.5rem; border-top: 1px solid var(--border-sage-subtle);">
      <a href="<?= SITE_URL ?>/index.html" style="font-size: 0.85rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 0.4rem;">
        <span>&larr; Return to Public Website</span>
      </a>
    </div>
  </div>

  <script>
  const toggleBtn = document.getElementById('togglePasswordBtn');
  const passwordInput = document.getElementById('password');
  const eyeIcon = document.getElementById('eyeIcon');

  if (toggleBtn && passwordInput) {
    toggleBtn.addEventListener('click', function() {
      const isPassword = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
      eyeIcon.innerHTML = isPassword 
        ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
    });
  }
  </script>
</body>
</html>
