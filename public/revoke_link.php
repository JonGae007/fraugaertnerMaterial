<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
init_storage();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrf)) {
    header('Location: admin.php?msg=' . urlencode('Ungültiger CSRF-Token.'));
    exit;
}

$linkId = trim((string) ($_POST['link_id'] ?? ''));
if ($linkId === '') {
    header('Location: admin.php?msg=' . urlencode('Link-ID fehlt.'));
    exit;
}

$links = read_json_file(LINKS_PATH);
$found = false;
foreach ($links as $idx => $link) {
    if (($link['id'] ?? '') === $linkId) {
        unset($links[$idx]);
        $found = true;
        break;
    }
}

if ($found) {
    $links = array_values($links);
    write_json_file(LINKS_PATH, $links);
    header('Location: admin.php?msg=' . urlencode('Link wurde zurückgerufen.'));
    exit;
}

header('Location: admin.php?msg=' . urlencode('Link nicht gefunden.'));
exit;
