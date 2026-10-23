<?php
require_once __DIR__ . '/assets/php/bootstrap.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . app_url('pages/summary.php'));
    exit;
}
$error = '';
$message = '';
$selectedRole = (string)($_POST['role'] ?? 'faculty');
if (!in_array($selectedRole, ['faculty', 'admin'], true)) $selectedRole = 'faculty';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accountId = trim((string)($_POST['account_id'] ?? ''));
    try {
        $pdo = db();
        if (($_POST['action'] ?? 'login') === 'reset') {
            $facultyId = trim((string)($_POST['faculty_id'] ?? ''));
            $stmt = $pdo->prepare("SELECT id FROM users WHERE account_id=? AND role='faculty' AND is_enabled=1 LIMIT 1");
            $stmt->execute([$facultyId]);
            $userId = $stmt->fetchColumn();
            if ($userId) {
                $stmt = $pdo->prepare("SELECT id FROM password_reset_requests WHERE user_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 15 MINUTE LIMIT 1");
                $stmt->execute([$userId]);
                if (!$stmt->fetchColumn()) {
                    $pdo->prepare('INSERT INTO password_reset_requests (user_id) VALUES (?)')->execute([$userId]);
                    log_activity($pdo, (int)$userId, 'password_reset_requested');
                }
            }
            $message = 'If the Faculty ID matches an active account, the administrator has been notified.';
        } else {
            $stmt = $pdo->prepare('SELECT id,name,password_hash,is_enabled FROM users WHERE account_id=? AND role=? LIMIT 1');
            $stmt->execute([$accountId, $selectedRole]);
            $user = $stmt->fetch();
            if ($user && (int)$user['is_enabled'] === 1 && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $selectedRole;
                $pdo->prepare('UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=?')->execute([$user['id']]);
                log_activity($pdo, (int)$user['id'], 'login');
                header('Location: ' . app_url('pages/summary.php'));
                exit;
            }
            $error = 'ID or password is incorrect.';
        }
    } catch (Throwable $exception) {
        $error = database_error_message();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · Environment Monitor</title>
  <script>try{const r=document.documentElement,s=localStorage.getItem('enviro-font-size');r.dataset.theme=localStorage.getItem('enviro-theme')==='dark'?'dark':'light';r.dataset.fontSize=['small','default','large'].includes(s)?s:'default';r.dataset.motion=localStorage.getItem('enviro-motion')==='off'?'off':'on'}catch{}</script>
  <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css?v=20261022-4')) ?>">
  <script src="<?= e(app_url('assets/js/login.js')) ?>" defer></script>
</head>
<body class="auth-page">
<main class="auth-card">
  <p class="eyebrow">Classroom Environment Monitor</p>
  <h1>Sign in</h1>
  <p>Choose your account type and sign in with your ID.</p>
  <?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($message): ?><p class="form-success" role="status"><?= e($message) ?></p><?php endif; ?>
  <form method="post" id="login-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Sign in as<select name="role" id="role-select"><option value="faculty" <?= $selectedRole === 'faculty' ? 'selected' : '' ?>>Faculty</option><option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option></select></label>
    <label><span id="account-id-label"><?= $selectedRole === 'admin' ? 'Admin ID' : 'Faculty ID' ?></span><input id="account-id" name="account_id" required maxlength="40" autocomplete="username" value="<?= e($accountId ?? '') ?>"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="button-primary" type="submit">Sign in</button>
  </form>
  <details class="faculty-recovery" id="faculty-recovery" <?= $selectedRole === 'admin' ? 'hidden' : '' ?>>
    <summary>Forgot your password?</summary>
    <p>Enter your Faculty ID below. An administrator will receive the request and set a temporary password.</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="reset">
      <label>Faculty ID<input name="faculty_id" required maxlength="40" autocomplete="username"></label>
      <button class="button-primary" type="submit">Request password reset</button>
    </form>
  </details>
</main>
</body>
</html>
