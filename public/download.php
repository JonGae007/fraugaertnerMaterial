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

$fileById = [];
foreach ($files as $file) {
    $fileId = (string) ($file['id'] ?? '');
    if ($fileId !== '') {
        $fileById[$fileId] = $file;
    }
}

$linkedFileIds = [];
if (!empty($link['file_ids']) && is_array($link['file_ids'])) {
    foreach ($link['file_ids'] as $fileId) {
        $id = trim((string) $fileId);
        if ($id !== '') {
            $linkedFileIds[$id] = true;
        }
    }
} elseif (!empty($link['file_id'])) {
    $linkedFileIds[(string) $link['file_id']] = true;
}
$linkedFileIds = array_values(array_keys($linkedFileIds));

if (empty($linkedFileIds)) {
    http_response_code(404);
    echo 'Keine Dateien für diesen Link gefunden.';
    exit;
}

$linkedFiles = [];
foreach ($linkedFileIds as $fileId) {
    if (!isset($fileById[$fileId])) {
        continue;
    }

    $candidate = $fileById[$fileId];
    $path = UPLOAD_PATH . '/' . (string) ($candidate['stored_name'] ?? '');
    if (!is_file($path)) {
        continue;
    }

    $linkedFiles[$fileId] = [
        'id' => $fileId,
        'path' => $path,
        'name' => (string) ($candidate['original_name'] ?? 'download.bin'),
        'mime' => (string) ($candidate['mime'] ?? 'application/octet-stream'),
        'size' => (int) filesize($path),
    ];
}

if (empty($linkedFiles)) {
    http_response_code(404);
    echo 'Datei ist nicht mehr vorhanden.';
    exit;
}

$tokenEsc = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
$requestFileId = trim((string) ($_GET['file_id'] ?? ''));
$downloadMode = trim((string) ($_GET['download'] ?? ''));
$isBundleLink = count($linkedFileIds) > 1 || !empty($link['file_ids']);

$consumeDownload = static function () use (&$links, $linkIndex, $count, $max): void {
    $links[$linkIndex]['download_count'] = $count + 1;
    if ($max > 0 && $links[$linkIndex]['download_count'] >= $max) {
        $links[$linkIndex]['active'] = false;
    }
    write_json_file(LINKS_PATH, $links);
};

if ($isBundleLink && $requestFileId === '' && $downloadMode !== 'zip') {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Dateiübersicht</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; background: #f5f7f6; color: #1d2927; }
        .card { background: #fff; border: 1px solid #d9dfdb; border-radius: 12px; padding: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: .6rem; }
        th, td { text-align: left; border-bottom: 1px solid #d9dfdb; padding: .55rem .35rem; }
        .actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1rem; }
        .btn { display: inline-block; background: #005e4e; color: #fff; text-decoration: none; padding: .65rem .9rem; border-radius: 8px; font-weight: 700; }
        .btn.secondary { background: #30443f; }
        .muted { color: #5d6b67; font-size: .9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Dateiübersicht</h1>
        <p class="muted">Du kannst einzelne Dateien herunterladen oder alles als ZIP laden.</p>

        <table>
            <thead>
                <tr>
                    <th>Datei</th>
                    <th>Größe</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linkedFiles as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format(((int) $file['size']) / 1024, 1); ?> KB</td>
                        <td>
                            <a class="btn secondary" href="download.php?token=<?php echo $tokenEsc; ?>&amp;file_id=<?php echo htmlspecialchars((string) $file['id'], ENT_QUOTES, 'UTF-8'); ?>">Herunterladen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="actions">
            <a class="btn" href="download.php?token=<?php echo $tokenEsc; ?>&amp;download=zip">Alles als ZIP herunterladen</a>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

if ($downloadMode === 'zip') {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'ZIP-Erstellung ist auf diesem Server nicht verfügbar.';
        exit;
    }

    $tmpZip = tempnam(sys_get_temp_dir(), 'share_zip_');
    if ($tmpZip === false) {
        http_response_code(500);
        echo 'ZIP-Datei konnte nicht erstellt werden.';
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpZip);
        http_response_code(500);
        echo 'ZIP-Datei konnte nicht geöffnet werden.';
        exit;
    }

    $nameCounter = [];
    foreach ($linkedFiles as $file) {
        $baseName = $file['name'];
        if (!isset($nameCounter[$baseName])) {
            $nameCounter[$baseName] = 0;
            $entryName = $baseName;
        } else {
            $nameCounter[$baseName]++;
            $entryName = $nameCounter[$baseName] . '_' . $baseName;
        }
        $zip->addFile($file['path'], $entryName);
    }
    $zip->close();

    $consumeDownload();

    $zipFilename = 'material-share-' . gmdate('Ymd-His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . (int) filesize($tmpZip));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
}

$selectedFile = null;
if ($requestFileId !== '') {
    if (!isset($linkedFiles[$requestFileId])) {
        http_response_code(404);
        echo 'Datei wurde nicht gefunden.';
        exit;
    }
    $selectedFile = $linkedFiles[$requestFileId];
} else {
    $selectedFile = reset($linkedFiles);
    if ($selectedFile === false) {
        http_response_code(404);
        echo 'Datei wurde nicht gefunden.';
        exit;
    }
}

$consumeDownload();

$filename = $selectedFile['name'];
$mime = $selectedFile['mime'];
$size = (int) $selectedFile['size'];
$path = $selectedFile['path'];

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$isPdf  = $mime === 'application/pdf' || $ext === 'pdf';
$isHtml = $mime === 'text/html' || in_array($ext, ['html', 'htm'], true);
$disposition = ($isPdf || $isHtml) ? 'inline' : 'attachment';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . $size);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

readfile($path);
exit;
