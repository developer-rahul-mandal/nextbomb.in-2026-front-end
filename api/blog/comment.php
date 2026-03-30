<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/blog/bootstrap.php';

use Nextbomb\Blog\BlogRepository;
use Nextbomb\Blog\Database;
use function Nextbomb\Blog\ensure_post_request;
use function Nextbomb\Blog\error_response;
use function Nextbomb\Blog\json_response;
use function Nextbomb\Blog\post_string;
use function Nextbomb\Blog\verify_csrf_token;

ensure_post_request();

$slug = post_string($_POST, 'slug', 180);
$authorName = post_string($_POST, 'author_name', 120);
$authorEmail = post_string($_POST, 'author_email', 180);
$authorWebsite = post_string($_POST, 'author_website', 255);
$body = post_string($_POST, 'body', 4000);
$honeypot = post_string($_POST, 'company', 120);
$csrfToken = post_string($_POST, 'csrf_token', 255);

if (!verify_csrf_token($csrfToken)) {
    error_response('Your session expired. Refresh the page and try again.', 419);
}

if ($honeypot !== '') {
    json_response([
        'success' => true,
        'message' => 'Comment received and queued for review.',
    ]);
}

if ($slug === '' || $authorName === '' || $authorEmail === '' || $body === '') {
    error_response('Name, email, and comment are required.', 422);
}

if (filter_var($authorEmail, FILTER_VALIDATE_EMAIL) === false) {
    error_response('Please enter a valid email address.', 422);
}

if ($authorWebsite !== '' && filter_var($authorWebsite, FILTER_VALIDATE_URL) === false) {
    error_response('Website must be a valid URL if provided.', 422);
}

try {
    $repository = new BlogRepository(Database::connect());
    $repository->createComment($slug, [
        'author_name' => $authorName,
        'author_email' => $authorEmail,
        'author_website' => $authorWebsite !== '' ? $authorWebsite : null,
        'body' => $body,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
    ]);

    json_response([
        'success' => true,
        'message' => 'Comment received and queued for review.',
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    error_response('Unable to save your comment right now.', 500);
}
