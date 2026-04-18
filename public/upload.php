<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
init_storage();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$returnTo = (string) ($_POST['return_to'] ?? 'admin.php');
if ($returnTo !== 'admin.php' && $returnTo !== 'files.php') {
    $returnTo = 'admin.php';
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrf)) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Ungültiger CSRF-Token.'));
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Keine Datei empfangen.'));
    exit;
}

$incoming = $_FILES['file'];
$names = $incoming['name'] ?? [];
$tmpNames = $incoming['tmp_name'] ?? [];
$sizes = $incoming['size'] ?? [];
$errors = $incoming['error'] ?? [];

if (!is_array($names)) {
    $names = [(string) $names];
    $tmpNames = [(string) ($tmpNames ?? '')];
    $sizes = [(int) ($sizes ?? 0)];
    $errors = [(int) ($errors ?? UPLOAD_ERR_NO_FILE)];
}

$files = read_json_file(FILES_PATH);
$uploadedCount = 0;
$skippedCount = 0;

for ($i = 0; $i < count($names); $i++) {
    $error = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $skippedCount++;
        continue;
    }

    $size = (int) ($sizes[$i] ?? 0);
    if ($size <= 0) {
        $skippedCount++;
        continue;
    }

    $tmpName = (string) ($tmpNames[$i] ?? '');
    if ($tmpName === '') {
        $skippedCount++;
        continue;
    }

    $originalName = basename((string) ($names[$i] ?? 'file.bin'));
    $mime = mime_content_type($tmpName);
    if ($mime === false) {
        $mime = 'application/octet-stream';
    }

    $storedName = random_id(24);
    $targetPath = UPLOAD_PATH . '/' . $storedName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        $skippedCount++;
        continue;
    }

    chmod($targetPath, 0600);

    $files[] = [
        'id' => random_id(8),
        'stored_name' => $storedName,
        'original_name' => $originalName,
        'mime' => $mime,
        'size' => $size,
        'uploaded_at' => now_utc(),
    ];
    $uploadedCount++;
}

if ($uploadedCount === 0) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Es konnte keine Datei hochgeladen werden.'));
    exit;
}

write_json_file(FILES_PATH, $files);

$msg = $uploadedCount . ' Datei(en) erfolgreich hochgeladen.';
if ($skippedCount > 0) {
    $msg .= ' ' . $skippedCount . ' Datei(en) konnten nicht verarbeitet werden.';
}

header('Location: ' . $returnTo . '?msg=' . urlencode($msg));
exit;
