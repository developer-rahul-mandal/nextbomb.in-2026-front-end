<?php
declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

call_enc_start_session();
call_enc_require_method('GET');

$config = call_enc_config();
call_enc_rate_limit(
    'token',
    (int) ($config['token_rate_limit'] ?? 30),
    (int) ($config['rate_window'] ?? 3600)
);

$ttl = (int) ($config['token_ttl'] ?? 300);
$token = call_enc_create_token($ttl);

call_enc_json([
    'ok' => true,
    'token' => $token,
    'expiresIn' => $ttl,
]);
