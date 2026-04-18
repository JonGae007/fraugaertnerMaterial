<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
init_storage();
ensure_session_started();

if (!is_setup_complete()) {
    header('Location: setup.php');
    exit;
}

$error = '';
$message = (string) ($_GET['msg'] ?? '');
$lastLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf($token)) {
        $error = 'Ungültige Anfrage.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $admin = get_admin_credentials();

        if ($admin === null || !hash_equals($admin['username'], $username) || !password_verify($password, $admin['password_hash'])) {
            $error = 'Anmeldedaten sind ungültig.';
        } else {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            header('Location: admin.php');
            exit;
        }
    }
}

if (is_logged_in()) {
    $links = read_json_file(LINKS_PATH);

    $files = read_json_file(FILES_PATH);
    $fileNameById = [];
    foreach ($files as $file) {
        if (!empty($file['id'])) {
            $fileNameById[(string) $file['id']] = (string) ($file['original_name'] ?? 'Unbekannt');
        }
    }

    usort($links, static fn(array $a, array $b): int => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

    $baseUrl = build_base_url();
    foreach ($links as &$link) {
        $expiresTs = to_timestamp((string) ($link['expires_at'] ?? ''));
        $isExpired = $expiresTs < time();
        $downloadCount = (int) ($link['download_count'] ?? 0);
        $maxDownloads = (int) ($link['max_downloads'] ?? 0);
        $limitReached = $maxDownloads > 0 && $downloadCount >= $maxDownloads;
        $link['is_active_now'] = !empty($link['active']) && !$isExpired && !$limitReached;
        $link['is_expired_now'] = $isExpired || $limitReached || empty($link['active']);

        $tokenPlain = (string) ($link['token_plain'] ?? '');
        $link['share_url'] = $tokenPlain !== '' ? ($baseUrl . '/download.php?token=' . urlencode($tokenPlain)) : '';
    }
    unset($link);

    $lastLink = '';
    if (isset($_SESSION['last_link'])) {
        $candidateLastLink = (string) $_SESSION['last_link'];
        $candidateToken = '';
        $parsedUrl = parse_url($candidateLastLink);
        if (is_array($parsedUrl) && isset($parsedUrl['query'])) {
            parse_str((string) $parsedUrl['query'], $queryParams);
            $candidateToken = (string) ($queryParams['token'] ?? '');
        }

        if ($candidateToken !== '') {
            foreach ($links as $link) {
                if (($link['token_plain'] ?? '') === $candidateToken && !empty($link['is_active_now'])) {
                    $lastLink = $candidateLastLink;
                    break;
                }
            }
        }

        if ($lastLink === '') {
            unset($_SESSION['last_link']);
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <title>Frau Gärtners Dateiablage</title>
    <style>
        :root {
            --bg: #f7f6f2;
            --card: #ffffff;
            --accent: #005e4e;
            --text: #1d2927;
            --danger: #9f1010;
            --border: #d9dfdb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            color: var(--text);
            background: var(--bg);
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(1300px 900px at 5% 10%, #d8efe8 0%, rgba(216, 239, 232, 0) 72%),
                radial-gradient(1200px 900px at 90% 80%, #ffe5ce 0%, rgba(255, 229, 206, 0) 74%);
            background-repeat: no-repeat;
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 1.2rem; position: relative; z-index: 1; }
        .head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .nav { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
        .nav a {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: #fff;
            background: #30443f;
            border-radius: 9px;
            padding: 0 .9rem;
            font-weight: 700;
            line-height: 1;
        }
        .nav a,
        .nav button {
            height: 42px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1rem;
            margin-top: 1rem;
            box-shadow: 0 10px 22px rgba(20, 30, 25, 0.05);
        }
        h1, h2, h3 { margin: .2rem 0 .6rem; }
        label { display: block; margin-top: .7rem; font-weight: 600; }
        input, select {
            width: 100%;
            padding: .6rem;
            border: 1px solid #bcc9c3;
            border-radius: 8px;
            margin-top: .2rem;
            background: #fff;
        }
        button {
            margin-top: .9rem;
            border: 0;
            border-radius: 9px;
            padding: .65rem .9rem;
            background: var(--accent);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        button.secondary { background: #30443f; }
        .nav button {
            margin-top: 0;
            line-height: 1;
            padding: 0 .9rem;
            display: inline-flex;
            align-items: center;
        }
        .danger { color: var(--danger); font-weight: 700; }
        .danger-bg { background: #fff1f1; }
        .ok { color: #0a6f0a; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: .95rem; }
        th, td { border-bottom: 1px solid var(--border); text-align: left; padding: .55rem .35rem; vertical-align: top; }
        .muted { color: #5d6b67; font-size: .9rem; }
        .pill { display: inline-block; padding: .1rem .45rem; border-radius: 99px; background: #ecf4f1; }
        .two { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .link-box {
            word-break: break-all;
            padding: .5rem;
            border: 1px dashed #b8c9c2;
            border-radius: 8px;
            margin-top: .5rem;
            background: #f8fbfa;
        }
        .qr { width: 140px; min-height: 140px; margin-top: .5rem; }
        .actions {
            display: flex;
            gap: .4rem;
            flex-wrap: nowrap;
            align-items: center;
            white-space: nowrap;
        }
        .actions > * { flex: 0 0 auto; }
        .inline-form { display: inline-flex; margin: 0; }
        .danger-btn { background: #9f1010; }
        .icon-btn {
            width: 42px;
            height: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .icon {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        @media (max-width: 900px) {
            .two { grid-template-columns: 1fr; }
            table { font-size: .9rem; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <?php if (!is_logged_in()): ?>
        <div class="card" style="max-width: 520px; margin: 10vh auto 0;">
            <h1>SecureShare Login</h1>
            <p class="muted">Nur Admin darf hochladen und Links erzeugen.</p>

            <?php if ($error !== ''): ?><p class="danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <label for="username">Benutzername</label>
                <input id="username" name="username" required autocomplete="username">

                <label for="password">Passwort</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">

                <button type="submit">Anmelden</button>
            </form>
        </div>
    <?php else: ?>
        <div class="head">
            <div>
                <h1>Admin Panel</h1>
            </div>
            <div class="nav">
                <a href="files.php">Dateien verwalten</a>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="secondary" type="submit">Logout</button>
                </form>
            </div>
        </div>

        <?php if ($message !== ''): ?><p class="ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

        <section class="card">
            <h2>Letzter Freigabelink</h2>
            <div class="link-box" id="latest-link"><?php echo $lastLink !== '' ? htmlspecialchars($lastLink, ENT_QUOTES, 'UTF-8') : 'Noch kein aktiver letzter Link vorhanden.'; ?></div>
            <div class="qr" id="latest-qr"></div>
            <button type="button" class="secondary" onclick="copyLatest()">Link kopieren</button>
        </section>

        <section class="card">
            <h2>Links</h2>
            <?php if (empty($links)): ?>
                <p class="muted">Keine aktiven Links vorhanden.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Datei</th>
                        <th>Ablauf</th>
                        <th>Downloads</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($links as $link): ?>
                        <tr class="<?php echo !empty($link['is_expired_now']) ? 'danger-bg' : ''; ?>">
                            <td><span class="pill"><?php echo htmlspecialchars((string) ($link['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars((string) ($fileNameById[(string) ($link['file_id'] ?? '')] ?? 'Datei nicht gefunden'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($link['expires_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php
                                    $downloadCount = (int) ($link['download_count'] ?? 0);
                                    $maxDownloads = (int) ($link['max_downloads'] ?? 0);
                                    echo $downloadCount . ' / ' . ($maxDownloads === 0 ? 'unbegrenzt' : (string) $maxDownloads);
                                ?>
                            </td>
                            <td><?php echo !empty($link['is_active_now']) ? 'aktiv' : 'abgelaufen'; ?></td>
                            <td>
                                <div class="actions">
                                    <?php if (!empty($link['share_url'])): ?>
                                        <button
                                            type="button"
                                            class="secondary icon-btn"
                                            title="QR-Code anzeigen"
                                            aria-label="QR-Code anzeigen"
                                            onclick="showQr('<?php echo htmlspecialchars((string) $link['share_url'], ENT_QUOTES, 'UTF-8'); ?>')"
                                        >
                                            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm10-2h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm4 0h2v2h-2v-2zm-2 2h2v2h-2v-2z"/>
                                            </svg>
                                            <span class="sr-only">QR-Code anzeigen</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="secondary icon-btn"
                                            title="Link kopieren"
                                            aria-label="Link kopieren"
                                            onclick="copyText('<?php echo htmlspecialchars((string) $link['share_url'], ENT_QUOTES, 'UTF-8'); ?>')"
                                        >
                                            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16h-9V7h9v14z"/>
                                            </svg>
                                            <span class="sr-only">Link kopieren</span>
                                        </button>
                                    <?php else: ?>
                                        <span class="muted">Nur für neu erstellte Links verfügbar</span>
                                    <?php endif; ?>

                                    <?php if (!empty($link['is_active_now'])): ?>
                                        <form class="inline-form" method="post" action="revoke_link.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="link_id" value="<?php echo htmlspecialchars((string) ($link['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="danger-btn icon-btn" title="Zurückrufen" aria-label="Zurückrufen">
                                                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M9 3h6l1 2h5v2H3V5h5l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM6 9h2v9H6V9z"/>
                                                </svg>
                                                <span class="sr-only">Zurückrufen</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form class="inline-form" method="post" action="delete_link.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="link_id" value="<?php echo htmlspecialchars((string) ($link['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="danger-btn icon-btn" title="Löschen" aria-label="Löschen">
                                                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M9 3h6l1 2h5v2H3V5h5l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM6 9h2v9H6V9z"/>
                                                </svg>
                                                <span class="sr-only">Löschen</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    function copyLatest() {
        const el = document.getElementById('latest-link');
        if (!el) return;
        const text = (el.textContent || '').trim();
        if (!text || !/^https?:\/\//i.test(text)) return;
        navigator.clipboard.writeText(text).catch(() => {});
    }

    function copyText(text) {
        navigator.clipboard.writeText(text || '').catch(() => {});
    }

    function showQr(url) {
        const linkEl = document.getElementById('latest-link');
        const qrEl = document.getElementById('latest-qr');
        if (!linkEl || !qrEl) return;

        linkEl.textContent = url || '';
        qrEl.innerHTML = '';

        if (typeof QRCode !== 'undefined') {
            new QRCode(qrEl, {
                text: url || '',
                width: 140,
                height: 140,
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        linkEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    (function initLatestQr() {
        const linkEl = document.getElementById('latest-link');
        const qrEl = document.getElementById('latest-qr');
        if (!linkEl || !qrEl || typeof QRCode === 'undefined') return;

        const text = (linkEl.textContent || '').trim();
        if (!/^https?:\/\//i.test(text)) {
            qrEl.innerHTML = '';
            return;
        }

        new QRCode(qrEl, {
            text,
            width: 140,
            height: 140,
            correctLevel: QRCode.CorrectLevel.M
        });
    })();
</script>
</body>
</html>
