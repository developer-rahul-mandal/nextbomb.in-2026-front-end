<?php
declare(strict_types=1);

namespace Nextbomb\Blog;

function escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function error_response(string $message, int $status = 500): never
{
    json_response([
        'success' => false,
        'message' => $message,
    ], $status);
}

function ensure_get_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method !== 'GET') {
        error_response('Method not allowed.', 405);
    }
}

function ensure_post_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method !== 'POST') {
        error_response('Method not allowed.', 405);
    }
}

function query_string_param(string $key, int $maxLength = 160): ?string
{
    $value = $_GET[$key] ?? null;

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }

    return $value;
}

function post_string(array $source, string $key, int $maxLength = 65535, string $default = ''): string
{
    $value = $source[$key] ?? $default;

    if (!is_string($value)) {
        return $default;
    }

    $value = trim(str_replace("\0", '', $value));

    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }

    return $value;
}

function post_nullable_string(array $source, string $key, int $maxLength = 65535): ?string
{
    $value = post_string($source, $key, $maxLength, '');

    return $value === '' ? null : $value;
}

function post_int(array $source, string $key, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
{
    $value = $source[$key] ?? $default;

    if (is_string($value) && trim($value) === '') {
        return $default;
    }

    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return $default;
    }

    $intValue = (int) $value;

    if ($intValue < $min) {
        return $min;
    }

    if ($intValue > $max) {
        return $max;
    }

    return $intValue;
}

function post_bool(array $source, string $key): bool
{
    $value = $source[$key] ?? null;

    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    if (is_int($value)) {
        return $value === 1;
    }

    return false;
}

function positive_int_param(string $key, int $default, int $min, int $max): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);

    if (!is_int($value)) {
        return $default;
    }

    if ($value < $min) {
        return $min;
    }

    if ($value > $max) {
        return $max;
    }

    return $value;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('NEXTBOMBSESSID');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function csrf_token(): string
{
    start_session();

    if (!isset($_SESSION['nextbomb_csrf']) || !is_string($_SESSION['nextbomb_csrf'])) {
        $_SESSION['nextbomb_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['nextbomb_csrf'];
}

function verify_csrf_token(?string $token): bool
{
    start_session();

    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = $_SESSION['nextbomb_csrf'] ?? '';

    return is_string($sessionToken) && hash_equals($sessionToken, $token);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function current_scheme(): string
{
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    return $isHttps ? 'https' : 'http';
}

function current_origin(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return current_scheme() . '://' . $host;
}

function current_url(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

    return current_origin() . $requestUri;
}

function site_url(array $settings, string $path = ''): string
{
    $baseUrl = trim((string) ($settings['site_url'] ?? ''));
    $baseUrl = $baseUrl !== '' ? rtrim($baseUrl, '/') : current_origin();

    if ($path === '') {
        return $baseUrl;
    }

    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function normalize_slug(string $value, int $maxLength = 190): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    if ($value === '') {
        return '';
    }

    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
        $value = rtrim($value, '-');
    }

    return $value;
}

function to_mysql_datetime(?string $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $normalized = str_replace('T', ' ', $value);

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized) === 1) {
        return $normalized . ':00';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $normalized) === 1) {
        return $normalized;
    }

    return null;
}

function nl2br_safe(string $value): string
{
    return nl2br(escape($value), false);
}
