<?php
declare(strict_types=1);

return [
    'driver' => 'mysql',
    'host' => getenv('BLOG_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('BLOG_DB_PORT') ?: 3306),
    'database' => getenv('BLOG_DB_NAME') ?: 'nextbomb_blog',
    'username' => getenv('BLOG_DB_USER') ?: 'root',
    'password' => getenv('BLOG_DB_PASS') ?: '',
    'charset' => getenv('BLOG_DB_CHARSET') ?: 'utf8mb4',
];
