<?php
declare(strict_types=1);

/**
 * Shared bootstrap: loads config, starts the session, exposes helpers.
 * Every entry point includes this file.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}

$configPath = CMS_ROOT . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo 'config.php is missing. Copy config.example.php to config.php and fill in your settings.';
    exit;
}

$config = require $configPath;

// Session — name it explicitly so it doesn't collide with anything else on the host.
session_name('katcms');
session_set_cookie_params([
    'lifetime' => $config['auth']['session_lifetime'],
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/schemas.php';
require_once __DIR__ . '/render.php';

/**
 * Tiny HTML escape helper.
 */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Send a JSON response and exit.
 */
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Read JSON body of a POST/PUT request.
 */
function json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * The currently-requested path (without query string), normalised.
 */
function current_path(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    // Strip the CMS's base path if the app is mounted in a subdirectory.
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = rtrim(dirname($script), '/');
    if ($base !== '' && $base !== '/' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }

    return '/' . ltrim($path, '/');
}

/**
 * Build a URL to a path within the CMS, respecting whatever subdirectory
 * it's mounted under (e.g. /cms/).
 */
function url(string $path = '/'): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = rtrim(dirname($script), '/');
    return ($base === '/' ? '' : $base) . '/' . ltrim($path, '/');
}
