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

$fileId = trim((string) ($_POST['file_id'] ?? ''));
if ($fileId === '') {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Datei-ID fehlt.'));
    exit;
}

$files = read_json_file(FILES_PATH);
$fileIndex = null;
$file = null;
foreach ($files as $index => $candidate) {
    if (($candidate['id'] ?? '') === $fileId) {
        $fileIndex = $index;
        $file = $candidate;
        break;
    }
}

if ($fileIndex === null || $file === null) {
    header('Location: ' . $returnTo . '?msg=' . urlencode('Datei nicht gefunden.'));
    exit;
}

unset($files[$fileIndex]);
$files = array_values($files);
write_json_file(FILES_PATH, $files);

$storedName = (string) ($file['stored_name'] ?? '');
if ($storedName !== '') {
    $path = UPLOAD_PATH . '/' . $storedName;
    if (is_file($path)) {
        @unlink($path);
    }
}

$links = read_json_file(LINKS_PATH);
$updatedLinks = [];
foreach ($links as $link) {
    if (!empty($link['file_ids']) && is_array($link['file_ids'])) {
        $remaining = [];
        foreach ($link['file_ids'] as $id) {
            $idStr = (string) $id;
            if ($idStr !== $fileId && $idStr !== '') {
                $remaining[] = $idStr;
            }
        }

        if (empty($remaining)) {
            continue;
        }

        $link['file_ids'] = array_values(array_unique($remaining));
        $updatedLinks[] = $link;
        continue;
    }

    if ((string) ($link['file_id'] ?? '') === $fileId) {
        continue;
    }

    $updatedLinks[] = $link;
}

$links = array_values($updatedLinks);
write_json_file(LINKS_PATH, $links);

if (isset($_SESSION['last_link'])) {
    $candidateLastLink = (string) $_SESSION['last_link'];
    $candidateToken = '';
    $parsedUrl = parse_url($candidateLastLink);
    if (is_array($parsedUrl) && isset($parsedUrl['query'])) {
        parse_str((string) $parsedUrl['query'], $queryParams);
        $candidateToken = (string) ($queryParams['token'] ?? '');
    }

    if ($candidateToken !== '') {
        $stillExists = false;
        foreach ($links as $link) {
            if (($link['token_plain'] ?? '') === $candidateToken) {
                $stillExists = true;
                break;
            }
        }

        if (!$stillExists) {
            unset($_SESSION['last_link']);
        }
    }
}

header('Location: ' . $returnTo . '?msg=' . urlencode('Datei wurde gelöscht.'));
exit;
