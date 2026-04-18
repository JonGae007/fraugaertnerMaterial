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

$fileId = trim((string) ($_POST['file_id'] ?? ''));
$returnTo = (string) ($_POST['return_to'] ?? 'admin.php');
$expiresAtUtc = trim((string) ($_POST['expires_at_utc'] ?? ''));
$expiresAtLocal = trim((string) ($_POST['expires_at_local'] ?? ''));
$maxDownloads = (int) ($_POST['max_downloads'] ?? 0);

if ($returnTo !== 'admin.php' && $returnTo !== 'files.php') {
    $returnTo = 'admin.php';
}

if ($fileId === '' || $maxDownloads < 0 || $maxDownloads > 1000) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Ungültige Parameter für den Link.'));
    exit;
}

if ($expiresAtUtc === '') {
    if ($expiresAtLocal === '') {
        header('Location: ' . $returnTo . '?msg=' . urlencode('Bitte ein Ablaufdatum und eine Uhrzeit angeben.'));
        exit;
    }

    $localDate = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $expiresAtLocal);
    if ($localDate === false) {
        header('Location: ' . $returnTo . '?msg=' . urlencode('Ungültiges Ablaufdatum.'));
        exit;
    }

    $expiresAtUtc = $localDate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

$expiresTimestamp = strtotime($expiresAtUtc);
if ($expiresTimestamp === false || $expiresTimestamp <= time()) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Das Ablaufdatum muss in der Zukunft liegen.'));
    exit;
}

$files = read_json_file(FILES_PATH);
$file = null;
foreach ($files as $f) {
    if (($f['id'] ?? '') === $fileId) {
        $file = $f;
        break;
    }
}

if ($file === null) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Datei nicht gefunden.'));
    exit;
}

$token = random_id(32);
$tokenHash = hash('sha256', $token);
$links = read_json_file(LINKS_PATH);
$links[] = [
    'id' => random_id(8),
    'file_id' => $fileId,
    'token_hash' => $tokenHash,
    'token_plain' => $token,
    'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresTimestamp),
    'max_downloads' => $maxDownloads,
    'download_count' => 0,
    'active' => true,
    'created_at' => now_utc(),
];
write_json_file(LINKS_PATH, $links);

$shareUrl = build_base_url() . '/download.php?token=' . urlencode($token);
$_SESSION['last_link'] = $shareUrl;

header('Location: ' . $returnTo . '?msg=' . urlencode('Freigabelink erstellt.'));
exit;
