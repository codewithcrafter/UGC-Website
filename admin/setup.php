<?php
/**
 * MY UGC VIDEO - One-Time Initial Admin Account Setup
 * 
 * Secure first-run administrator account creation.
 * Automatically locks permanently as soon as the first admin account is registered.
 */

define('CMS_ROOT', dirname(__DIR__));
require_once CMS_ROOT . '/config/config.php';

$db = getDb();
$adminCount = (int) $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();

// Permanently lock setup if an administrator already exists
if ($adminCount > 0) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Setup Locked — MY UGC VIDEO CMS</title>
      <link rel="stylesheet" href="<?= SITE_URL ?>/admin/assets/admin.css">
    </head>
    <body class="login-body">
      <div class="login-card" style="text-align: center;">
        <h2 style="color: var(--accent-mint); margin-bottom: 1rem;">Setup Completed</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">The initial administrator account has already been configured. For security, this setup page has been permanently locked.</p>
        <a href="<?= ADMIN_URL ?>/login" class="btn btn-primary" style="width: 100%;">Proceed to Admin Login &rarr;</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (empty($name)) {
        $errors[] = 'Full name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO admins (name, email, password_hash, role, is_active) VALUES (:name, :email, :hash, 'admin', 1)");
        $stmt->execute([
            ':name'  => $name,
            ':email' => $email,
            ':hash'  => $passwordHash
        ]);

        setFlash('success', 'Admin account created successfully! Please sign in with your credentials.');
        header('Location: ' . ADMIN_URL . '/login');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Initial Admin Setup — MY UGC VIDEO CMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&family=Sora:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/admin/assets/admin.css">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="login-logo">
      <h1>MY UGC VIDEO</h1>
      <p>Initial Admin Setup</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger" style="display: block;">
        <?php foreach ($errors as $err): ?>
          <div>&bull; <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrfField() ?>

      <div class="form-group">
        <label for="name" class="form-label">Administrator Name</label>
        <input type="text" id="name" name="name" class="form-control" value="<?= e($name) ?>" placeholder="e.g. Studio Admin" required autofocus>
      </div>

      <div class="form-group">
        <label for="email" class="form-label">Work Email</label>
        <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="admin@myugcvideo.in" required>
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Secure Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
        <div class="form-hint">Must contain at least 8 characters.</div>
      </div>

      <div class="form-group">
        <label for="password_confirm" class="form-label">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Re-enter password" required minlength="8">
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem; margin-top: 1rem;">
        Initialize Admin Account &rarr;
      </button>
    </form>
  </div>
</body>
</html>

