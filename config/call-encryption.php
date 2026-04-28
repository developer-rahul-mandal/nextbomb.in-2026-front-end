<?php
declare(strict_types=1);

return [
    'cipher' => 'AES-256-CBC',
    'key' => getenv('NEXTBOMB_CALL_ENC_KEY') ?: 'nextbomb-static-call-key-change-this-2026',
    'iv' => getenv('NEXTBOMB_CALL_ENC_IV') ?: 'nextbomb-static-call-iv-2026',
    'token_ttl' => 300,
    'job_ttl' => 300,
    'token_rate_limit' => 30,
    'submit_rate_limit' => 12,
    'rate_window' => 3600,
];
