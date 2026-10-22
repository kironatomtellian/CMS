<?php
declare(strict_types=1);

/**
 * Tiny single-user auth.
 *
 * Stores the password hash in config.php. On login, compares the submitted
 * password against the hash, then flips a session flag. require_auth() bounces
 * unauthenticated requests to /login (HTML) or 401 JSON (API).
 */

/**
 * Login throttle: after 5 wrong passwords from one address, sign-in is
 * refused for 15 minutes. State lives in a small JSON file in the system
 * temp dir, so it needs no database.
 */
const AUTH_MAX_FAILS = 5;
const AUTH_LOCK_SECONDS = 15 * 60;

function auth_throttle_file(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return sys_get_temp_dir() . '/katcms-login-' . hash('sha256', $ip) . '.json';
}

function auth_throttle_state(): array {
    $f = auth_throttle_file();
    $s = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    if (!is_array($s) || ($s['first'] ?? 0) < time() - AUTH_LOCK_SECONDS) {
        return ['fails' => 0, 'first' => time()];
    }
    return $s;
}

function auth_locked_for(): int {
    $s = auth_throttle_state();
    if (($s['fails'] ?? 0) < AUTH_MAX_FAILS) {
        return 0;
    }
    return max(0, (int)$s['first'] + AUTH_LOCK_SECONDS - time());
}

function auth_record_failure(): void {
    $s = auth_throttle_state();
    $s['fails'] = ($s['fails'] ?? 0) + 1;
    @file_put_contents(auth_throttle_file(), json_encode($s), LOCK_EX);
}

function auth_clear_failures(): void {
    @unlink(auth_throttle_file());
}

function auth_attempt(string $password): bool {
    global $config;
    if (auth_locked_for() > 0) {
        return false;
    }
    $hash = $config['auth']['password_hash'] ?? '';
    if ($hash === '' || strpos($hash, 'REPLACE_ME') !== false) {
        return false;
    }
    if (!password_verify($password, $hash)) {
        auth_record_failure();
        return false;
    }
    auth_clear_failures();
    session_regenerate_id(true);
    $_SESSION['authed'] = true;
    $_SESSION['authed_at'] = time();
    return true;
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_authed(): bool {
    return !empty($_SESSION['authed']);
}

function require_auth_html(): void {
    if (!is_authed()) {
        header('Location: ' . url('/login'));
        exit;
    }
}

function require_auth_api(): void {
    if (!is_authed()) {
        json_response(['ok' => false, 'error' => 'Not authenticated'], 401);
    }
}
