<?php
require_once __DIR__ . '/assets/php/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!empty($_SESSION['user_id'])) {
        try { log_activity(db(), (int)$_SESSION['user_id'], 'logout'); } catch (Throwable $error) {}
    }
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ' . app_url('login.php'));
exit;

