<?php
declare(strict_types=1);

namespace Nextbomb\Blog;

use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    public static function connect(): PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/blog.php';

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? 'nextbomb_blog');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to connect to the blog database.', 0, $exception);
        }
    }
}
