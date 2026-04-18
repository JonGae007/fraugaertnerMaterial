<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
init_storage();
ensure_session_started();

$error = '';
$success = '';

if (is_setup_complete()) {
    $success = 'Setup ist bereits abgeschlossen. Du kannst dich im Admin-Panel anmelden.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    if ($username === '' || strlen($username) < 3) {
        $error = 'Benutzername muss mindestens 3 Zeichen haben.';
    } elseif (strlen($password) < 8) {
        $error = 'Passwort muss mindestens 8 Zeichen haben.';
    } elseif ($password !== $password2) {
        $error = 'Passwörter stimmen nicht überein.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        write_json_file(ADMIN_PATH, [
            'username' => $username,
            'password_hash' => $hash,
            'created_at' => now_utc(),
        ]);
        $success = 'Setup abgeschlossen. Gehe jetzt zum Admin-Panel.';
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
        body { font-family: sans-serif; max-width: 760px; margin: 3rem auto; padding: 0 1rem; }
        .card { border: 1px solid #ddd; border-radius: 12px; padding: 1rem; }
        label { display: block; margin-top: .8rem; font-weight: 600; }
        input { width: 100%; padding: .6rem; margin-top: .2rem; }
        button { margin-top: 1rem; padding: .7rem 1rem; }
        .error { color: #a10000; font-weight: 600; }
        .ok { color: #0a610a; font-weight: 600; }
    </style>
</head>
<body>
    <h1>Setup</h1>
    <p>Einmalige Einrichtung des Admin-Kontos.</p>

    <div class="card">
        <?php if ($error !== ''): ?>
            <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <p class="ok"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><a href="admin.php">Zum Admin-Panel</a></p>
        <?php endif; ?>

        <?php if (!is_setup_complete()): ?>
            <form method="post">
                <label for="username">Benutzername</label>
                <input id="username" name="username" required minlength="3" maxlength="64">

                <label for="password">Passwort</label>
                <input id="password" type="password" name="password" required minlength="8">

                <label for="password2">Passwort wiederholen</label>
                <input id="password2" type="password" name="password2" required minlength="8">

                <button type="submit">Setup abschließen</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
