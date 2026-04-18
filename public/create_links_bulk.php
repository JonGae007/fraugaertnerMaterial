<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
init_storage();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: files.php');
    exit;
}

$returnTo = (string) ($_POST['return_to'] ?? 'files.php');
if ($returnTo !== 'admin.php' && $returnTo !== 'files.php') {
    $returnTo = 'files.php';
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrf)) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Ungültiger CSRF-Token.'));
    exit;
}

$expiresAtUtc = trim((string) ($_POST['expires_at_utc'] ?? ''));
$expiresAtLocal = trim((string) ($_POST['expires_at_local'] ?? ''));
$maxDownloads = (int) ($_POST['max_downloads'] ?? 1);
$fileIds = $_POST['file_ids'] ?? [];

if (!is_array($fileIds) || empty($fileIds)) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Bitte mindestens eine Datei markieren.'));
    exit;
}

if ($maxDownloads < 0 || $maxDownloads > 1000) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Ungültige Parameter für die Freigabe.'));
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

$validIds = [];
foreach ($fileIds as $id) {
    $trimmed = trim((string) $id);
    if ($trimmed !== '') {
        $validIds[$trimmed] = true;
    }
}

if (empty($validIds)) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Keine gültigen Dateiauswahlen gefunden.'));
    exit;
}

$files = read_json_file(FILES_PATH);
$fileById = [];
foreach ($files as $file) {
    $id = (string) ($file['id'] ?? '');
    if ($id !== '') {
        $fileById[$id] = $file;
    }
}

$links = read_json_file(LINKS_PATH);
$created = 0;
$lastShareUrl = '';
$baseUrl = build_base_url();

foreach (array_keys($validIds) as $fileId) {
    if (!isset($fileById[$fileId])) {
        continue;
    }

    $token = random_id(32);
    $links[] = [
        'id' => random_id(8),
        'file_id' => $fileId,
        'token_hash' => hash('sha256', $token),
        'token_plain' => $token,
        'expires_at' => gmdate('Y-m-d\\TH:i:s\\Z', $expiresTimestamp),
        'max_downloads' => $maxDownloads,
        'download_count' => 0,
        'active' => true,
        'created_at' => now_utc(),
    ];

    $created++;
    $lastShareUrl = $baseUrl . '/download.php?token=' . urlencode($token);
}

if ($created === 0) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Keine passenden Dateien gefunden.'));
    exit;
}

write_json_file(LINKS_PATH, $links);
$_SESSION['last_link'] = $lastShareUrl;

header('Location: ' . $returnTo . '?msg=' . urlencode($created . ' Freigabelinks erstellt.'));
exit;
