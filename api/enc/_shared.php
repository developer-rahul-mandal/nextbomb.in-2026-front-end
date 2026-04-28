<?php
declare(strict_types=1);

function call_enc_config(): array
{
    /** @var array<string, mixed> $config */
    $config = require __DIR__ . '/../../config/call-encryption.php';

    return $config;
}

function call_enc_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function call_enc_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function call_enc_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        call_enc_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function call_enc_is_same_origin(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin === '') {
        return true;
    }

    $originHost = parse_url($origin, PHP_URL_HOST);
    $requestHost = parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);

    return is_string($originHost) && hash_equals($requestHost, $originHost);
}

function call_enc_rate_limit(string $bucket, int $limit, int $windowSeconds): void
{
    $now = time();
    if (!isset($_SESSION['call_enc_rate']) || !is_array($_SESSION['call_enc_rate'])) {
        $_SESSION['call_enc_rate'] = [];
    }

    if (
        !isset($_SESSION['call_enc_rate'][$bucket]) ||
        !is_array($_SESSION['call_enc_rate'][$bucket]) ||
        ($_SESSION['call_enc_rate'][$bucket]['reset_at'] ?? 0) <= $now
    ) {
        $_SESSION['call_enc_rate'][$bucket] = [
            'count' => 0,
            'reset_at' => $now + $windowSeconds,
        ];
    }

    $_SESSION['call_enc_rate'][$bucket]['count'] += 1;

    if ($_SESSION['call_enc_rate'][$bucket]['count'] > $limit) {
        call_enc_json(['ok' => false, 'message' => 'Too many requests. Try again later.'], 429);
    }
}

function call_enc_prune_tokens(): void
{
    $now = time();
    if (!isset($_SESSION['call_enc_tokens']) || !is_array($_SESSION['call_enc_tokens'])) {
        $_SESSION['call_enc_tokens'] = [];
    }

    foreach ($_SESSION['call_enc_tokens'] as $token => $expiresAt) {
        if (!is_int($expiresAt) || $expiresAt <= $now) {
            unset($_SESSION['call_enc_tokens'][$token]);
        }
    }
}

function call_enc_create_token(int $ttl): string
{
    call_enc_prune_tokens();

    $token = bin2hex(random_bytes(16));
    $_SESSION['call_enc_tokens'][$token] = time() + $ttl;

    return $token;
}

function call_enc_consume_token(string $token): void
{
    call_enc_prune_tokens();
    $tokens = $_SESSION['call_enc_tokens'] ?? [];
    $matchedToken = null;

    foreach (array_keys($tokens) as $sessionToken) {
        if (hash_equals((string) $sessionToken, $token)) {
            $matchedToken = (string) $sessionToken;
            break;
        }
    }

    if ($matchedToken === null) {
        call_enc_json(['ok' => false, 'message' => 'Invalid or expired token.'], 403);
    }

    unset($_SESSION['call_enc_tokens'][$matchedToken]);
}

function call_enc_payload(): array
{
    $rawBody = file_get_contents('php://input');
    $payload = json_decode(is_string($rawBody) ? $rawBody : '', true);

    if (!is_array($payload)) {
        call_enc_json(['ok' => false, 'message' => 'Invalid request body.'], 400);
    }

    return $payload;
}

function call_enc_normalize_phone($value): string
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    if (!is_string($digits) || !preg_match('/^[0-9]{10}$/', $digits)) {
        call_enc_json(['ok' => false, 'message' => 'Enter a valid 10-digit number.'], 422);
    }

    return $digits;
}

function call_enc_static_encrypt(string $number, array $config): string
{
    $cipher = (string) ($config['cipher'] ?? 'AES-256-CBC');
    $ivLength = openssl_cipher_iv_length($cipher);

    if ($ivLength === false) {
        call_enc_json(['ok' => false, 'message' => 'Encryption is not configured.'], 500);
    }

    $key = hash('sha256', (string) ($config['key'] ?? ''), true);
    $iv = substr(hash('sha256', (string) ($config['iv'] ?? ''), true), 0, $ivLength);
    $ciphertext = openssl_encrypt($number, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === false) {
        call_enc_json(['ok' => false, 'message' => 'Could not encrypt number.'], 500);
    }

    return base64_encode($ciphertext);
}

function call_enc_signature(string $jobId, string $encryptedNumber, int $expiresAt, array $config): string
{
    $secret = hash('sha256', (string) ($config['key'] ?? '') . '|call-progress-signature', true);

    return hash_hmac('sha256', $jobId . '|' . $encryptedNumber . '|' . $expiresAt, $secret);
}
