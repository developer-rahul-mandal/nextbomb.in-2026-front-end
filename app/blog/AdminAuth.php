<?php
declare(strict_types=1);

namespace Nextbomb\Blog;

function admin_config(): array
{
    static $config;

    if (is_array($config)) {
        return $config;
    }

    $config = require dirname(__DIR__, 2) . '/config/admin.php';

    return $config;
}

function admin_is_authenticated(): bool
{
    start_session();

    return ($_SESSION['blog_admin_authenticated'] ?? false) === true;
}

function admin_login(string $username, string $password): bool
{
    $config = admin_config();
    $expectedUser = (string) ($config['username'] ?? '');
    $expectedPass = (string) ($config['password'] ?? '');

    if ($username === '' || $password === '') {
        return false;
    }

    if (!hash_equals($expectedUser, $username) || !hash_equals($expectedPass, $password)) {
        return false;
    }

    start_session();
    session_regenerate_id(true);
    $_SESSION['blog_admin_authenticated'] = true;
    $_SESSION['blog_admin_user'] = $expectedUser;

    return true;
}

function admin_logout(): void
{
    start_session();
    unset($_SESSION['blog_admin_authenticated'], $_SESSION['blog_admin_user']);
    session_regenerate_id(true);
}

function admin_user(): string
{
    start_session();

    return (string) ($_SESSION['blog_admin_user'] ?? '');
}

function set_flash(string $type, string $message): void
{
    start_session();
    $_SESSION['blog_admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flash(): ?array
{
    start_session();

    $flash = $_SESSION['blog_admin_flash'] ?? null;
    unset($_SESSION['blog_admin_flash']);

    return is_array($flash) ? $flash : null;
}

function using_default_admin_password(): bool
{
    $config = admin_config();

    return ($config['username'] ?? '') === 'admin'
        && ($config['password'] ?? '') === 'change-this-password';
}
