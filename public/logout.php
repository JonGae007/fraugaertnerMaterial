<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf($csrf)) {
        header('Location: admin.php?msg=' . urlencode('Ungültiger Logout-Versuch.'));
        exit;
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
}
session_destroy();

header('Location: admin.php?msg=' . urlencode('Abgemeldet.'));
exit;
