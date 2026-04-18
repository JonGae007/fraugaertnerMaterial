<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
init_storage();
require_login();

$message = (string) ($_GET['msg'] ?? '');
$files = read_json_file(FILES_PATH);
usort($files, static fn(array $a, array $b): int => strcmp($b['uploaded_at'] ?? '', $a['uploaded_at'] ?? ''));
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
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1rem;
            margin-top: 1rem;
            box-shadow: 0 10px 22px rgba(20, 30, 25, 0.05);
        }
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
        .nav button {
            margin-top: 0;
            line-height: 1;
            padding: 0 .9rem;
            display: inline-flex;
            align-items: center;
        }
        h1, h2 { margin: .2rem 0 .6rem; }
        label { display: block; margin-top: .7rem; font-weight: 600; }
        input {
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
        .secondary { background: #30443f; }
        .ok { color: #0a6f0a; font-weight: 700; }
        .muted { color: #5d6b67; font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; font-size: .95rem; }
        th, td { border-bottom: 1px solid var(--border); text-align: left; padding: .55rem .35rem; vertical-align: top; }
        .actions {
            display: inline-flex;
            gap: .5rem;
            flex-wrap: nowrap;
            align-items: center;
            white-space: nowrap;
            overflow-x: auto;
        }
        .actions > * { flex: 0 0 auto; }
        .icon-col { width: 70px; text-align: center; }
        .icon-cell { text-align: center; }
        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 9px;
            background: #30443f;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            line-height: 1;
        }
        .action-link:hover { filter: brightness(1.04); }
        .danger-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            margin-top: 0;
            padding: 0;
            border: 0;
            border-radius: 9px;
            background: #9f1010;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
        }
        .inline-form { display: inline-flex; margin: 0; }
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
        .bulk-tools { display: grid; grid-template-columns: 1fr 1fr auto; gap: .7rem; align-items: start; }
        .bulk-tools > button {
            grid-column: 3;
            align-self: start;
            height: 42px;
            margin-top: 0;
            display: inline-flex;
            align-items: center;
            margin-top: 2rem;
        }
        .bulk-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .checkbox-col { width: 40px; text-align: center; }
        .checkbox-col input { width: 18px; height: 18px; margin: 0; }
        .bulk-label { font-weight: 700; }
        @media (max-width: 900px) {
            .bulk-tools { grid-template-columns: 1fr 1fr; }
            .bulk-tools > button { grid-column: 1 / -1; justify-self: start; margin-top: .25rem; }
            table { font-size: .9rem; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <div>
            <h1>Dateiansicht</h1>
        </div>
        <div class="nav">
            <a href="admin.php">Zur Link-Übersicht</a>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <button class="secondary" type="submit">Logout</button>
            </form>
        </div>
    </div>

    <?php if ($message !== ''): ?><p class="ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

    <section class="card">
        <h2>Datei hochladen</h2>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="return_to" value="files.php">
            <label for="file">Datei</label>
            <input id="file" type="file" name="file[]" multiple required>
            <button type="submit">Upload</button>
        </form>
    </section>

    <section class="card">
        <h2>Dateien</h2>
        <form id="bulk-share-form" action="create_links_bulk.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="return_to" value="files.php">
            <input type="hidden" name="expires_at_utc" id="bulk-expires-at-utc" value="">

            <div class="bulk-tools">
                <div>
                    <label class="bulk-label" for="bulk-expires-at">Ablaufdatum und Uhrzeit (für alle markierten)</label>
                    <input id="bulk-expires-at" type="datetime-local" name="expires_at_local" required>
                </div>
                <div>
                    <label class="bulk-label" for="bulk-max">Max. Downloads (pro Link)</label>
                    <input id="bulk-max" type="number" name="max_downloads" min="0" max="1000" value="0" required>
                    <p class="muted" style="margin:.35rem 0 0;">Hinweis: 0 bedeutet unbegrenzt viele Downloads.</p>
                </div>
                <button type="submit">Link erstellen</button>
            </div>
            <div class="bulk-actions" style="margin-top:.6rem;">
                <button class="secondary" type="button" onclick="toggleAll(true)">Alle markieren</button>
                <button class="secondary" type="button" onclick="toggleAll(false)">Alle abwählen</button>
            </div>
        </form>

        <?php if (empty($files)): ?>
            <p class="muted">Noch keine Dateien hochgeladen.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-col">#</th>
                        <th>Datei</th>
                        <th>Größe</th>
                        <th>Upload</th>
                        <th class="icon-col">Vorschau</th>
                        <th class="icon-col">Löschen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($files as $file): ?>
                    <?php
                        $isPdf = (string) ($file['mime'] ?? '') === 'application/pdf' || strcasecmp(pathinfo((string) ($file['original_name'] ?? ''), PATHINFO_EXTENSION), 'pdf') === 0;
                    ?>
                    <tr>
                        <td class="checkbox-col">
                            <input
                                type="checkbox"
                                class="bulk-file-check"
                                form="bulk-share-form"
                                name="file_ids[]"
                                value="<?php echo htmlspecialchars((string) ($file['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars((string) ($file['original_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted"><?php echo htmlspecialchars((string) ($file['mime'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td><?php echo number_format(((int) ($file['size'] ?? 0)) / 1024, 1); ?> KB</td>
                        <td><?php echo htmlspecialchars((string) ($file['uploaded_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="icon-cell">
                            <?php if ($isPdf): ?>
                                <a class="action-link" href="preview.php?file_id=<?php echo htmlspecialchars((string) ($file['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" title="Ansehen" aria-label="Ansehen">
                                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 5c-5.5 0-9.7 3.5-11 7 1.3 3.5 5.5 7 11 7s9.7-3.5 11-7c-1.3-3.5-5.5-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2.2A2.8 2.8 0 1 0 12 9a2.8 2.8 0 0 0 0 5.6z"/>
                                    </svg>
                                    <span class="sr-only">Ansehen</span>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="icon-cell">
                            <form class="inline-form" method="post" action="delete_file.php" onsubmit="return confirm('Datei wirklich löschen?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="file_id" value="<?php echo htmlspecialchars((string) ($file['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="return_to" value="files.php">
                                <button type="submit" class="danger-action" title="Löschen" aria-label="Löschen">
                                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M9 3h6l1 2h5v2H3V5h5l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM6 9h2v9H6V9z"/>
                                    </svg>
                                    <span class="sr-only">Löschen</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<script>
    function toUtcString(localValue) {
        if (!localValue) return '';
        const localDate = new Date(localValue);
        if (Number.isNaN(localDate.getTime())) return '';
        return localDate.toISOString().replace(/\.\d{3}Z$/, 'Z');
    }

    function toggleAll(state) {
        const checks = document.querySelectorAll('.bulk-file-check');
        checks.forEach((el) => {
            el.checked = !!state;
        });
    }

    (function initBulkExpiry() {
        const localInput = document.getElementById('bulk-expires-at');
        const hiddenUtc = document.getElementById('bulk-expires-at-utc');
        const form = document.getElementById('bulk-share-form');
        if (!localInput || !hiddenUtc || !form) return;

        const defaultDate = new Date(Date.now() + 24 * 60 * 60 * 1000);
        const pad = (value) => String(value).padStart(2, '0');
        localInput.value = [
            defaultDate.getFullYear(),
            '-',
            pad(defaultDate.getMonth() + 1),
            '-',
            pad(defaultDate.getDate()),
            'T',
            pad(defaultDate.getHours()),
            ':',
            pad(defaultDate.getMinutes())
        ].join('');

        form.addEventListener('submit', () => {
            hiddenUtc.value = toUtcString(localInput.value);
        });
    })();
</script>
</body>
</html>
