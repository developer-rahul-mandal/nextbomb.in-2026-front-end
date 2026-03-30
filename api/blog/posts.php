<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/blog/bootstrap.php';

use Nextbomb\Blog\BlogRepository;
use Nextbomb\Blog\Database;
use function Nextbomb\Blog\ensure_get_request;
use function Nextbomb\Blog\error_response;
use function Nextbomb\Blog\json_response;
use function Nextbomb\Blog\positive_int_param;
use function Nextbomb\Blog\query_string_param;

ensure_get_request();

$search = query_string_param('search', 80) ?? '';
$category = query_string_param('category', 80);
$page = positive_int_param('page', 1, 1, 1000);
$limit = positive_int_param('limit', 6, 1, 12);

try {
    $repository = new BlogRepository(Database::connect());
    $featured = null;
    $excludePostId = null;

    if ($category === null && $search === '') {
        $featured = $repository->getFeaturedPost();
        $excludePostId = is_array($featured) && isset($featured['id'])
            ? (int) $featured['id']
            : null;

        if ($page !== 1) {
            $featured = null;
        }
    }

    $listing = $repository->listPosts($category, $search, $page, $limit, $excludePostId);
    $totalPages = max(1, (int) ceil($listing['total'] / $limit));

    if ($page > $totalPages) {
        $page = $totalPages;

        if ($page === 1 && $category === null && $search === '') {
            $featured = $repository->getFeaturedPost();
            $excludePostId = is_array($featured) && isset($featured['id'])
                ? (int) $featured['id']
                : null;
        }

        $listing = $repository->listPosts($category, $search, $page, $limit, $excludePostId);
    }

    json_response([
        'success' => true,
        'generatedAt' => gmdate(DATE_ATOM),
        'featured' => $featured,
        'categories' => $repository->getCategories(),
        'posts' => $listing['posts'],
        'filters' => [
            'search' => $search,
            'category' => $category,
        ],
        'totals' => [
            'storyCount' => $listing['total'] + ($excludePostId !== null ? 1 : 0),
        ],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $listing['total'],
            'totalPages' => $totalPages,
            'hasPrevious' => $page > 1,
            'hasNext' => $page < $totalPages,
        ],
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    error_response('Unable to load blog stories. Check config/blog.php and import database/blog_schema.sql.', 500);
}
