<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
init_storage();

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Referrer-Policy: no-referrer', true);
header('X-Content-Type-Options: nosniff', true);

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !ctype_xdigit($token)) {
    http_response_code(404);
    echo 'Ungültiger Link.';
    exit;
}

$tokenHash = hash('sha256', $token);
$links = read_json_file(LINKS_PATH);
$files = read_json_file(FILES_PATH);

$linkIndex = null;
$link = null;
foreach ($links as $index => $candidate) {
    if (!empty($candidate['token_hash']) && hash_equals((string) $candidate['token_hash'], $tokenHash)) {
        $linkIndex = $index;
        $link = $candidate;
        break;
    }
}

if ($linkIndex === null || $link === null) {
    http_response_code(404);
    echo 'Link nicht gefunden.';
    exit;
}

$expired = to_timestamp((string) ($link['expires_at'] ?? '')) < time();
$active = !empty($link['active']);
$count = (int) ($link['download_count'] ?? 0);
$max = (int) ($link['max_downloads'] ?? 0);
$limitReached = $max > 0 && $count >= $max;

if (!$active || $expired || $limitReached) {
    http_response_code(410);
    echo 'Link ist abgelaufen oder wurde bereits verbraucht.';
    exit;
}

$file = null;
foreach ($files as $candidate) {
    if (($candidate['id'] ?? '') === ($link['file_id'] ?? '')) {
        $file = $candidate;
        break;
    }
}

if ($file === null) {
    http_response_code(404);
    echo 'Datei wurde nicht gefunden.';
    exit;
}

$path = UPLOAD_PATH . '/' . (string) ($file['stored_name'] ?? '');
if (!is_file($path)) {
    http_response_code(404);
    echo 'Datei ist nicht mehr vorhanden.';
    exit;
}

$links[$linkIndex]['download_count'] = $count + 1;
if ($max > 0 && $links[$linkIndex]['download_count'] >= $max) {
    $links[$linkIndex]['active'] = false;
}
write_json_file(LINKS_PATH, $links);

$filename = (string) ($file['original_name'] ?? 'download.bin');
$mime = (string) ($file['mime'] ?? 'application/octet-stream');
$size = (int) filesize($path);

$isPdf = $mime === 'application/pdf' || strcasecmp(pathinfo($filename, PATHINFO_EXTENSION), 'pdf') === 0;
$disposition = $isPdf ? 'inline' : 'attachment';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . $size);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

readfile($path);
exit;
