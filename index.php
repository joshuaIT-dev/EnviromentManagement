<?php
require_once __DIR__ . '/assets/php/bootstrap.php';
if (empty($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
} else {
    header('Location: ' . app_url('pages/summary.php'));
}
exit;

