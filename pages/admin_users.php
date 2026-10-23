<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
$message = '';
$users = $requests = [];
try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (($_POST['action'] ?? '') === 'create') {
            $accountId = trim((string)($_POST['account_id'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'faculty');
            if ($accountId === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || !in_array($role, ['faculty', 'admin'], true)) {
                throw new RuntimeException('Enter an ID, name, valid email, role, and password of at least 12 characters.');
            }
            $stmt = $pdo->prepare('INSERT INTO users (account_id,name,email,password_hash,role) VALUES (?,?,?,?,?)');
            $stmt->execute([$accountId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            log_activity($pdo, (int)$_SESSION['user_id'], 'user_created', $accountId . ' ' . $email);
            $message = 'Account created.';
        } elseif (($_POST['action'] ?? '') === 'reset') {
            $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
            $password = (string)($_POST['password'] ?? '');
            if (!$requestId || strlen($password) < 12) throw new RuntimeException('Enter a new password with at least 12 characters.');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT r.id,r.user_id,u.account_id FROM password_reset_requests r JOIN users u ON u.id=r.user_id WHERE r.id=? AND r.status='pending' FOR UPDATE");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            if (!$request) throw new RuntimeException('That request is no longer pending.');
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $request['user_id']]);
            $pdo->prepare("UPDATE password_reset_requests SET status='resolved',handled_at=UTC_TIMESTAMP(),handled_by=? WHERE id=?")->execute([$_SESSION['user_id'], $requestId]);
            log_activity($pdo, (int)$_SESSION['user_id'], 'password_reset_completed', $request['account_id']);
            $pdo->commit();
            $message = 'Password reset and request resolved.';
        }
    }
    $users = $pdo->query('SELECT id,account_id,name,email,role,is_enabled,created_at FROM users ORDER BY id')->fetchAll();
    $requests = $pdo->query("SELECT r.id,r.created_at,u.account_id,u.name,u.email FROM password_reset_requests r JOIN users u ON u.id=r.user_id WHERE r.status='pending' ORDER BY r.created_at")->fetchAll();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $message = $exception instanceof RuntimeException ? $exception->getMessage() : database_error_message();
}
layout_start('Manage users', 'admin');
?>
<?php if ($message): ?><p class="inline-notice"><?= e($message) ?></p><?php endif; ?>
<section class="content-card"><h2>Create account</h2><form class="filter-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Faculty or administrator ID<input name="account_id" required maxlength="40"></label><label>Name<input name="name" required maxlength="120"></label><label>Email<input type="email" name="email" required maxlength="190"></label><label>Temporary password<input type="password" name="password" required minlength="12"></label><label>Role<select name="role"><option value="faculty">Faculty</option><option value="admin">Administrator</option></select></label><button class="button-primary small" type="submit">Create account</button></form></section>
<section class="content-card"><h2>Accounts</h2><div class="table-scroll"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach ($users as $u): ?><tr><td><?= e($u['account_id']) ?></td><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= e(ucfirst($u['role'])) ?></td><td><?= $u['is_enabled'] ? 'Enabled' : 'Disabled' ?></td><td><?= e($u['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="content-card"><h2>Password reset requests</h2><?php if (!$requests): ?><p class="empty-state">No pending requests.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Faculty ID</th><th>Name</th><th>Email</th><th>Requested</th><th>Set a temporary password</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td><?= e($request['account_id']) ?></td><td><?= e($request['name']) ?></td><td><?= e($request['email']) ?></td><td><?= e($request['created_at']) ?></td><td><form class="filter-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><label>Temporary password<input type="password" name="password" minlength="12" required placeholder="12+ characters"></label><button class="button-primary small" type="submit">Reset password</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php layout_end(); ?>
