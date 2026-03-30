<?php
declare(strict_types=1);

return [
    'username' => getenv('BLOG_ADMIN_USER') ?: 'admin',
    'password' => getenv('BLOG_ADMIN_PASS') ?: 'change-this-password',
];
