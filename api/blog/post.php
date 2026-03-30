<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/blog/bootstrap.php';

use Nextbomb\Blog\BlogRepository;
use Nextbomb\Blog\Database;
use function Nextbomb\Blog\ensure_get_request;
use function Nextbomb\Blog\error_response;
use function Nextbomb\Blog\json_response;
use function Nextbomb\Blog\query_string_param;

ensure_get_request();

$slug = query_string_param('slug', 180);

if ($slug === null) {
    error_response('A blog post slug is required.', 400);
}

try {
    $repository = new BlogRepository(Database::connect());
    $post = $repository->getPostBySlug($slug, true);

    if ($post === null) {
        error_response('We could not find that story.', 404);
    }

    json_response([
        'success' => true,
        'generatedAt' => gmdate(DATE_ATOM),
        'post' => $post,
        'comments' => $repository->listApprovedCommentsForPost((int) $post['id']),
        'relatedPosts' => $repository->getRelatedPosts(
            (int) $post['id'],
            (int) $post['category']['id'],
            3
        ),
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    error_response('Unable to load that story. Check config/blog.php and import database/blog_schema.sql.', 500);
}
