<?php
declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

call_enc_start_session();
call_enc_require_method('POST');

if (!call_enc_is_same_origin()) {
    call_enc_json(['ok' => false, 'message' => 'Origin not allowed.'], 403);
}

$config = call_enc_config();
call_enc_rate_limit(
    'submit',
    (int) ($config['submit_rate_limit'] ?? 12),
    (int) ($config['rate_window'] ?? 3600)
);

$token = (string) ($_GET['token'] ?? '');
call_enc_consume_token($token);

$payload = call_enc_payload();
$number = call_enc_normalize_phone($payload['number'] ?? '');
$encryptedNumber = call_enc_static_encrypt($number, $config);
$jobId = bin2hex(random_bytes(12));
$expiresAt = time() + (int) ($config['job_ttl'] ?? 300);

if (!isset($_SESSION['call_enc_jobs']) || !is_array($_SESSION['call_enc_jobs'])) {
    $_SESSION['call_enc_jobs'] = [];
}
$_SESSION['call_enc_jobs'][$jobId] = [
    'encrypted_number' => $encryptedNumber,
    'expires_at' => $expiresAt,
];

call_enc_json([
    'ok' => true,
    'jobId' => $jobId,
    'encryptedNumber' => $encryptedNumber,
    'expiresAt' => $expiresAt,
    'signature' => call_enc_signature($jobId, $encryptedNumber, $expiresAt, $config),
]);
