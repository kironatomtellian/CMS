<?php
declare(strict_types=1);

/**
 * Tiny single-user auth.
 *
 * Stores the password hash in config.php. On login, compares the submitted
 * password against the hash, then flips a session flag. require_auth() bounces
 * unauthenticated requests to /login (HTML) or 401 JSON (API).
 */

function auth_attempt(string $password): bool {
    global $config;
    $hash = $config['auth']['password_hash'] ?? '';
    if ($hash === '' || strpos($hash, 'REPLACE_ME') !== false) {
        return false;
    }
    if (!password_verify($password, $hash)) {
        return false;
    }
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
