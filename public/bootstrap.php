<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const STORAGE_PATH = APP_ROOT . '/storage';
const UPLOAD_PATH = STORAGE_PATH . '/uploads';
const DATA_PATH = STORAGE_PATH . '/data';
const ADMIN_PATH = DATA_PATH . '/admin.json';
const FILES_PATH = DATA_PATH . '/files.json';
const LINKS_PATH = DATA_PATH . '/links.json';

function init_storage(): void
{
    foreach ([STORAGE_PATH, UPLOAD_PATH, DATA_PATH] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
    }

    foreach ([FILES_PATH, LINKS_PATH] as $jsonFile) {
        if (!is_file($jsonFile)) {
            file_put_contents($jsonFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}

function read_json_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function write_json_file(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $tempPath = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON encoding failed.');
    }

    file_put_contents($tempPath, $json, LOCK_EX);
    rename($tempPath, $path);
}

function get_admin_credentials(): ?array
{
    if (!is_file(ADMIN_PATH)) {
        return null;
    }

    $data = read_json_file(ADMIN_PATH);
    if (!isset($data['username'], $data['password_hash'])) {
        return null;
    }

    return [
        'username' => (string) $data['username'],
        'password_hash' => (string) $data['password_hash'],
    ];
}

function is_setup_complete(): bool
{
    return get_admin_credentials() !== null;
}

function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => !empty($_SERVER['HTTPS']),
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }
}

function is_logged_in(): bool
{
    ensure_session_started();
    return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: admin.php');
        exit;
    }
}

function csrf_token(): string
{
    ensure_session_started();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    ensure_session_started();
    return isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function random_id(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function now_utc(): string
{
    return gmdate('Y-m-d\\TH:i:s\\Z');
}

function to_timestamp(string $isoUtc): int
{
    $ts = strtotime($isoUtc);
    return $ts === false ? 0 : $ts;
}

function build_base_url(): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/public/admin.php';
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    return $scheme . '://' . $host . ($basePath === '' ? '' : $basePath);
}
