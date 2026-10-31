<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
$message = '';
$users = $requests = [];
$roleFilter = (string)($_GET['role'] ?? 'all');
if (!in_array($roleFilter, ['all', 'admin', 'faculty'], true)) $roleFilter = 'all';
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
        } elseif (in_array(($_POST['action'] ?? ''), ['reset', 'reset_user'], true)) {
            $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
            $userId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
            $password = (string)($_POST['password'] ?? '');
            if (strlen($password) < 12 || (($_POST['action'] ?? '') === 'reset' && !$requestId) || (($_POST['action'] ?? '') === 'reset_user' && !$userId)) throw new RuntimeException('Enter a new password with at least 12 characters.');
            $pdo->beginTransaction();
            if (($_POST['action'] ?? '') === 'reset') {
                $stmt = $pdo->prepare("SELECT r.id,r.user_id,u.account_id FROM password_reset_requests r JOIN users u ON u.id=r.user_id WHERE r.id=? AND r.status='pending' FOR UPDATE");
                $stmt->execute([$requestId]); $request = $stmt->fetch();
                if (!$request) throw new RuntimeException('That request is no longer pending.');
                $targetId = (int)$request['user_id']; $accountId = $request['account_id'];
                $pdo->prepare("UPDATE password_reset_requests SET status='resolved',handled_at=UTC_TIMESTAMP(),handled_by=? WHERE id=?")->execute([$_SESSION['user_id'], $requestId]);
            } else {
                $stmt = $pdo->prepare('SELECT id,account_id FROM users WHERE id=? FOR UPDATE'); $stmt->execute([$userId]); $request = $stmt->fetch();
                if (!$request) throw new RuntimeException('That account no longer exists.');
                $targetId = (int)$request['id']; $accountId = $request['account_id'];
            }
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $targetId]);
            log_activity($pdo, (int)$_SESSION['user_id'], 'password_reset_completed', $accountId);
            $pdo->commit();
            $message = 'Password reset' . (($_POST['action'] ?? '') === 'reset' ? ' and request resolved.' : '.');
        } elseif (($_POST['action'] ?? '') === 'toggle') {
            $targetId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
            if (!$targetId || $targetId === (int)$_SESSION['user_id']) throw new RuntimeException('You cannot disable your own account.');
            $stmt = $pdo->prepare('SELECT role,is_enabled,account_id FROM users WHERE id=?'); $stmt->execute([$targetId]); $target = $stmt->fetch();
            if (!$target) throw new RuntimeException('That account no longer exists.');
            $next = (int)!$target['is_enabled'];
            if (!$next && $target['role'] === 'admin' && (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_enabled=1")->fetchColumn() <= 1) throw new RuntimeException('Keep at least one enabled administrator.');
            $pdo->prepare('UPDATE users SET is_enabled=? WHERE id=?')->execute([$next,$targetId]);
            log_activity($pdo,(int)$_SESSION['user_id'],$next?'user_enabled':'user_disabled',$target['account_id']);
            $message = $next ? 'Account enabled.' : 'Account disabled.';
        }
    }
    $users = $pdo->query('SELECT id,account_id,name,email,role,is_enabled,created_at,last_login_at FROM users ORDER BY id')->fetchAll();
    $requests = $pdo->query("SELECT r.id,r.created_at,u.account_id,u.name,u.email FROM password_reset_requests r JOIN users u ON u.id=r.user_id WHERE r.status='pending' ORDER BY r.created_at")->fetchAll();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $message = $exception instanceof RuntimeException ? $exception->getMessage() : database_error_message();
}
$roleCounts = ['admin'=>0,'faculty'=>0];
foreach ($users as $user) if (isset($roleCounts[$user['role']])) $roleCounts[$user['role']]++;
$visibleUsers = $roleFilter === 'all' ? $users : array_values(array_filter($users, static fn($user) => $user['role'] === $roleFilter));
layout_start('Users & roles', 'admin_users');
?>
<?php if ($message): ?><p class="inline-notice"><?= e($message) ?></p><?php endif; ?>
<section class="content-card"><h2>Create account</h2><form class="filter-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Faculty or administrator ID<input name="account_id" required maxlength="40"></label><label>Name<input name="name" required maxlength="120"></label><label>Email<input type="email" name="email" required maxlength="190"></label><label>Temporary password<input type="password" name="password" required minlength="12"></label><label>Role<select name="role"><option value="faculty">Faculty</option><option value="admin">Administrator</option></select></label><button class="button-primary small" type="submit">Create account</button></form></section>
<section class="content-card"><div class="section-heading"><div><h2>Accounts</h2><p><?= count($visibleUsers) ?> shown · separate faculty and administrator accounts</p></div></div><nav class="segmented admin-filter-tabs" aria-label="Filter accounts"><a class="<?= $roleFilter==='all'?'selected':'' ?>" href="<?= e(app_url('pages/admin_users.php?role=all')) ?>">All <?= count($users) ?></a><a class="<?= $roleFilter==='faculty'?'selected':'' ?>" href="<?= e(app_url('pages/admin_users.php?role=faculty')) ?>">Faculty <?= $roleCounts['faculty'] ?></a><a class="<?= $roleFilter==='admin'?'selected':'' ?>" href="<?= e(app_url('pages/admin_users.php?role=admin')) ?>">Administrators <?= $roleCounts['admin'] ?></a></nav><div class="table-scroll"><table class="admin-users-table"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead><tbody><?php foreach ($visibleUsers as $u): ?><tr><td><?= e($u['account_id']) ?></td><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= e(ucfirst($u['role'])) ?></td><td><?= $u['is_enabled'] ? 'Enabled' : 'Disabled' ?></td><td><?= e($u['last_login_at'] ?: 'Never') ?></td><td class="admin-user-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>"><button class="button-link small" type="submit" <?= (int)$u['id'] === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>><?= $u['is_enabled'] ? 'Disable' : 'Enable' ?></button></form><details><summary>Reset password</summary><form class="filter-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset_user"><input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>"><label>New password<input type="password" name="password" minlength="12" required></label><button class="button-primary small" type="submit">Set password</button></form></details></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="content-card"><h2>Password reset requests</h2><?php if (!$requests): ?><p class="empty-state">No pending requests.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Faculty ID</th><th>Name</th><th>Email</th><th>Requested</th><th>Set a temporary password</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td><?= e($request['account_id']) ?></td><td><?= e($request['name']) ?></td><td><?= e($request['email']) ?></td><td><?= e($request['created_at']) ?></td><td><form class="filter-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><label>Temporary password<input type="password" name="password" minlength="12" required placeholder="12+ characters"></label><button class="button-primary small" type="submit">Reset password</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php layout_end(); ?>
