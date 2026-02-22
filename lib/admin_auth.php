<?php

define('APIZM_ADMIN_USER', 'admin');
define('APIZM_ADMIN_PASS_HASH', '$2y$12$QZUsKhHKUGewrRwFiKdb5uL29OUbLS8Alc75u/NmlVjN8s9UwcNNS');
define('APIZM_ADMIN_SESSION_KEY', 'apizm_admin_logged_in');
define('APIZM_ADMIN_CSRF_KEY', 'apizm_admin_login_csrf');

function apizm_admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function is_admin_logged_in(): bool
{
    apizm_admin_session_start();

    return isset($_SESSION[APIZM_ADMIN_SESSION_KEY]) && $_SESSION[APIZM_ADMIN_SESSION_KEY] === true;
}

function require_admin_login(): void
{
    if (is_admin_logged_in()) {
        return;
    }

    header('Location: /admin/login0929.php');
    exit;
}

function admin_login(string $username, string $password): bool
{
    apizm_admin_session_start();

    if ($username !== APIZM_ADMIN_USER) {
        return false;
    }

    if (!password_verify($password, APIZM_ADMIN_PASS_HASH)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION[APIZM_ADMIN_SESSION_KEY] = true;

    return true;
}

function admin_logout(): void
{
    apizm_admin_session_start();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function admin_login_csrf_token(): string
{
    apizm_admin_session_start();

    if (!isset($_SESSION[APIZM_ADMIN_CSRF_KEY]) || !is_string($_SESSION[APIZM_ADMIN_CSRF_KEY])) {
        $_SESSION[APIZM_ADMIN_CSRF_KEY] = bin2hex(random_bytes(32));
    }

    return $_SESSION[APIZM_ADMIN_CSRF_KEY];
}

function verify_admin_login_csrf_token(string $token): bool
{
    apizm_admin_session_start();

    if (!isset($_SESSION[APIZM_ADMIN_CSRF_KEY]) || !is_string($_SESSION[APIZM_ADMIN_CSRF_KEY])) {
        return false;
    }

    return hash_equals($_SESSION[APIZM_ADMIN_CSRF_KEY], $token);
}
