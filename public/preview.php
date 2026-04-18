<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
init_storage();
require_login();

$fileId = trim((string) ($_GET['file_id'] ?? ''));
if ($fileId === '') {
    http_response_code(404);
    echo 'Datei nicht gefunden.';
    exit;
}

$files = read_json_file(FILES_PATH);
$file = null;
foreach ($files as $candidate) {
    if (($candidate['id'] ?? '') === $fileId) {
        $file = $candidate;
        break;
    }
}

if ($file === null) {
    http_response_code(404);
    echo 'Datei nicht gefunden.';
    exit;
}

$mime = (string) ($file['mime'] ?? 'application/octet-stream');
$isPdf = $mime === 'application/pdf' || strcasecmp(pathinfo((string) ($file['original_name'] ?? ''), PATHINFO_EXTENSION), 'pdf') === 0;
if (!$isPdf) {
    http_response_code(403);
    echo 'Nur PDF-Dateien können direkt angezeigt werden.';
    exit;
}

$path = UPLOAD_PATH . '/' . (string) ($file['stored_name'] ?? '');
if (!is_file($path)) {
    http_response_code(404);
    echo 'Datei ist nicht mehr vorhanden.';
    exit;
}

$filename = (string) ($file['original_name'] ?? 'preview.pdf');
$size = (int) filesize($path);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'' . rawurlencode($filename));
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

readfile($path);
exit;
