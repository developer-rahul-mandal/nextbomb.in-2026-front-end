<?php
declare(strict_types=1);

namespace Nextbomb\Blog;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use RuntimeException;

final class BlogRepository
{
    private const POST_STATUSES = ['draft', 'published'];
    private const COMMENT_STATUSES = ['pending', 'approved', 'spam', 'trash'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getCategories(): array
    {
        $sql = <<<'SQL'
        SELECT
            c.id,
            c.name,
            c.slug,
            c.description,
            c.accent_color,
            COUNT(p.id) AS post_count
        FROM blog_categories c
        LEFT JOIN blog_posts p
            ON p.category_id = c.id
           AND p.status = 'published'
           AND p.published_at IS NOT NULL
           AND p.published_at <= NOW()
        GROUP BY c.id, c.name, c.slug, c.description, c.accent_color
        HAVING COUNT(p.id) > 0
        ORDER BY c.name ASC
        SQL;

        $statement = $this->pdo->query($sql);
        $categories = [];

        foreach ($statement->fetchAll() as $row) {
            $categories[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'description' => (string) ($row['description'] ?? ''),
                'color' => (string) ($row['accent_color'] ?: '#b5512c'),
                'postCount' => (int) $row['post_count'],
            ];
        }

        return $categories;
    }

    public function getAllCategories(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, slug, description, accent_color FROM blog_categories ORDER BY name ASC'
        );

        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'description' => (string) ($row['description'] ?? ''),
                'accent_color' => (string) ($row['accent_color'] ?: '#b5512c'),
            ];
        }, $statement->fetchAll());
    }

    public function getFeaturedPost(): ?array
    {
        $filters = $this->buildPublishedFilters();

        $sql = sprintf(
            'SELECT %s %s WHERE %s AND p.is_featured = 1 ORDER BY p.published_at DESC LIMIT 1',
            $this->selectColumns(),
            $this->publicFromClause(),
            $filters['where']
        );

        $featured = $this->prepareStatement($sql, $filters['params'])->fetch();

        if (is_array($featured)) {
            return $this->mapPost($featured);
        }

        $fallbackSql = sprintf(
            'SELECT %s %s WHERE %s ORDER BY p.published_at DESC LIMIT 1',
            $this->selectColumns(),
            $this->publicFromClause(),
            $filters['where']
        );

        $fallback = $this->prepareStatement($fallbackSql, $filters['params'])->fetch();

        return is_array($fallback) ? $this->mapPost($fallback) : null;
    }

    public function listPosts(?string $categorySlug, string $search, int $page, int $limit, ?int $excludePostId = null): array
    {
        $filters = $this->buildPublishedFilters($categorySlug, $search, $excludePostId);

        $countSql = sprintf(
            'SELECT COUNT(*) FROM blog_posts p INNER JOIN blog_categories c ON c.id = p.category_id WHERE %s',
            $filters['where']
        );

        $countStatement = $this->prepareStatement($countSql, $filters['params']);
        $total = (int) $countStatement->fetchColumn();
        $offset = max(0, ($page - 1) * $limit);

        $sql = sprintf(
            'SELECT %s %s WHERE %s ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset',
            $this->selectColumns(),
            $this->publicFromClause(),
            $filters['where']
        );

        $statement = $this->prepareStatement($sql, array_merge($filters['params'], [
            'limit' => $limit,
            'offset' => $offset,
        ]));

        $posts = [];

        foreach ($statement->fetchAll() as $row) {
            $posts[] = $this->mapPost($row);
        }

        return [
            'posts' => $posts,
            'total' => $total,
        ];
    }

    public function getPostBySlug(string $slug, bool $trackView = false): ?array
    {
        if ($trackView) {
            $this->prepareStatement(
                "UPDATE blog_posts SET view_count = view_count + 1 WHERE slug = :slug AND status = 'published' AND published_at IS NOT NULL AND published_at <= NOW()",
                ['slug' => $slug]
            );
        }

        $filters = $this->buildPublishedFilters();
        $filters['where'] .= ' AND p.slug = :slug';
        $filters['params']['slug'] = $slug;

        $sql = sprintf(
            'SELECT %s %s WHERE %s LIMIT 1',
            $this->selectColumns(true),
            $this->publicFromClause(),
            $filters['where']
        );

        $row = $this->prepareStatement($sql, $filters['params'])->fetch();

        return is_array($row) ? $this->mapPost($row, true) : null;
    }

    public function getRelatedPosts(int $excludePostId, int $categoryId, int $limit = 3): array
    {
        $filters = $this->buildPublishedFilters(null, '', $excludePostId);
        $filters['where'] .= ' AND p.category_id = :category_id';
        $filters['params']['category_id'] = $categoryId;

        $sql = sprintf(
            'SELECT %s %s WHERE %s ORDER BY p.published_at DESC LIMIT :limit',
            $this->selectColumns(),
            $this->publicFromClause(),
            $filters['where']
        );

        $statement = $this->prepareStatement($sql, array_merge($filters['params'], [
            'limit' => $limit,
        ]));

        $related = [];

        foreach ($statement->fetchAll() as $row) {
            $related[] = $this->mapPost($row);
        }

        if (count($related) >= $limit) {
            return $related;
        }

        $fallbackPosts = $this->listPosts(null, '', 1, max($limit * 2, 6), $excludePostId)['posts'];
        $seenIds = [$excludePostId => true];

        foreach ($related as $post) {
            $seenIds[$post['id']] = true;
        }

        foreach ($fallbackPosts as $post) {
            if (isset($seenIds[$post['id']])) {
                continue;
            }

            $related[] = $post;
            $seenIds[$post['id']] = true;

            if (count($related) >= $limit) {
                break;
            }
        }

        return $related;
    }

    public function listApprovedCommentsForPost(int $postId): array
    {
        $statement = $this->prepareStatement(
            <<<'SQL'
            SELECT
                id,
                post_id,
                author_name,
                author_email,
                author_website,
                body,
                status,
                created_at,
                approved_at
            FROM blog_comments
            WHERE post_id = :post_id
              AND status = 'approved'
            ORDER BY approved_at DESC, created_at DESC
            SQL,
            ['post_id' => $postId]
        );

        $comments = [];

        foreach ($statement->fetchAll() as $row) {
            $comments[] = $this->mapComment($row);
        }

        return $comments;
    }

    public function createComment(string $slug, array $data): array
    {
        $postStatement = $this->prepareStatement(
            "SELECT id FROM blog_posts WHERE slug = :slug AND status = 'published' AND published_at IS NOT NULL AND published_at <= NOW() LIMIT 1",
            ['slug' => $slug]
        );

        $postId = (int) $postStatement->fetchColumn();

        if ($postId <= 0) {
            throw new RuntimeException('Unable to comment on a post that does not exist.');
        }

        $this->prepareStatement(
            <<<'SQL'
            INSERT INTO blog_comments (
                post_id,
                author_name,
                author_email,
                author_website,
                body,
                status,
                ip_address,
                user_agent
            ) VALUES (
                :post_id,
                :author_name,
                :author_email,
                :author_website,
                :body,
                'pending',
                :ip_address,
                :user_agent
            )
            SQL,
            [
                'post_id' => $postId,
                'author_name' => $data['author_name'],
                'author_email' => $data['author_email'],
                'author_website' => $data['author_website'],
                'body' => $data['body'],
                'ip_address' => $data['ip_address'],
                'user_agent' => $data['user_agent'],
            ]
        );

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'status' => 'pending',
            'postId' => $postId,
        ];
    }

    public function getDashboardStats(): array
    {
        $sql = <<<'SQL'
        SELECT
            (SELECT COUNT(*) FROM blog_posts) AS total_posts,
            (SELECT COUNT(*) FROM blog_posts WHERE status = 'published') AS published_posts,
            (SELECT COUNT(*) FROM blog_posts WHERE status = 'draft') AS draft_posts,
            (SELECT COUNT(*) FROM blog_categories) AS total_categories,
            (SELECT COUNT(*) FROM blog_comments) AS total_comments,
            (SELECT COUNT(*) FROM blog_comments WHERE status = 'approved') AS approved_comments,
            (SELECT COUNT(*) FROM blog_comments WHERE status = 'pending') AS pending_comments,
            (SELECT COUNT(*) FROM blog_comments WHERE status = 'spam') AS spam_comments,
            (SELECT COUNT(*) FROM blog_posts WHERE is_featured = 1) AS featured_posts,
            (SELECT COALESCE(SUM(view_count), 0) FROM blog_posts) AS total_views
        SQL;

        $row = $this->pdo->query($sql)->fetch();

        return [
            'totalPosts' => (int) ($row['total_posts'] ?? 0),
            'publishedPosts' => (int) ($row['published_posts'] ?? 0),
            'draftPosts' => (int) ($row['draft_posts'] ?? 0),
            'totalCategories' => (int) ($row['total_categories'] ?? 0),
            'totalComments' => (int) ($row['total_comments'] ?? 0),
            'approvedComments' => (int) ($row['approved_comments'] ?? 0),
            'pendingComments' => (int) ($row['pending_comments'] ?? 0),
            'spamComments' => (int) ($row['spam_comments'] ?? 0),
            'featuredPosts' => (int) ($row['featured_posts'] ?? 0),
            'totalViews' => (int) ($row['total_views'] ?? 0),
        ];
    }

    public function listAdminPosts(int $limit = 80): array
    {
        $statement = $this->prepareStatement(
            sprintf(
                'SELECT
                    p.id,
                    p.category_id,
                    p.title,
                    p.slug,
                    p.status,
                    p.is_featured,
                    p.view_count,
                    p.reading_time,
                    p.author_name,
                    p.published_at,
                    p.created_at,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    COALESCE(comment_summary.total_comment_count, 0) AS total_comment_count,
                    COALESCE(comment_summary.approved_comment_count, 0) AS approved_comment_count,
                    COALESCE(comment_summary.pending_comment_count, 0) AS pending_comment_count
                 %s
                 ORDER BY
                    CASE WHEN p.published_at IS NULL THEN 1 ELSE 0 END,
                    p.published_at DESC,
                    p.created_at DESC
                 LIMIT :limit',
                $this->adminFromClause()
            ),
            ['limit' => $limit]
        );

        return array_map(fn (array $row): array => $this->mapAdminPost($row), $statement->fetchAll());
    }

    public function getAdminPostById(?int $id): array
    {
        if ($id === null || $id <= 0) {
            return $this->defaultAdminPost();
        }

        $statement = $this->prepareStatement(
            'SELECT * FROM blog_posts WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        $row = $statement->fetch();

        if (!is_array($row)) {
            return $this->defaultAdminPost();
        }

        return [
            'id' => (int) $row['id'],
            'category_id' => (int) $row['category_id'],
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'excerpt' => (string) $row['excerpt'],
            'cover_image' => (string) $row['cover_image'],
            'cover_alt' => (string) ($row['cover_alt'] ?? ''),
            'author_name' => (string) $row['author_name'],
            'reading_time' => (int) $row['reading_time'],
            'status' => (string) $row['status'],
            'is_featured' => (int) $row['is_featured'],
            'tags' => (string) ($row['tags'] ?? ''),
            'body_html' => (string) $row['body_html'],
            'view_count' => (int) ($row['view_count'] ?? 0),
            'focus_keyword' => (string) ($row['focus_keyword'] ?? ''),
            'seo_title' => (string) ($row['seo_title'] ?? ''),
            'seo_description' => (string) ($row['seo_description'] ?? ''),
            'canonical_url' => (string) ($row['canonical_url'] ?? ''),
            'meta_robots' => (string) ($row['meta_robots'] ?? 'index,follow'),
            'og_title' => (string) ($row['og_title'] ?? ''),
            'og_description' => (string) ($row['og_description'] ?? ''),
            'og_image' => (string) ($row['og_image'] ?? ''),
            'og_image_alt' => (string) ($row['og_image_alt'] ?? ''),
            'twitter_title' => (string) ($row['twitter_title'] ?? ''),
            'twitter_description' => (string) ($row['twitter_description'] ?? ''),
            'twitter_image' => (string) ($row['twitter_image'] ?? ''),
            'twitter_card' => (string) ($row['twitter_card'] ?? 'summary_large_image'),
            'schema_type' => (string) ($row['schema_type'] ?? 'Article'),
            'schema_json' => (string) ($row['schema_json'] ?? ''),
            'published_at' => (string) ($row['published_at'] ?? ''),
        ];
    }

    public function savePost(array $data): int
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $payload = [
            'category_id' => (int) $data['category_id'],
            'title' => (string) $data['title'],
            'slug' => (string) $data['slug'],
            'excerpt' => (string) $data['excerpt'],
            'cover_image' => (string) $data['cover_image'],
            'cover_alt' => $data['cover_alt'],
            'author_name' => (string) $data['author_name'],
            'reading_time' => (int) $data['reading_time'],
            'status' => $this->normalizePostStatus((string) $data['status']),
            'is_featured' => !empty($data['is_featured']) ? 1 : 0,
            'tags' => $data['tags'],
            'body_html' => (string) $data['body_html'],
            'focus_keyword' => $data['focus_keyword'],
            'seo_title' => $data['seo_title'],
            'seo_description' => $data['seo_description'],
            'canonical_url' => $data['canonical_url'],
            'meta_robots' => $data['meta_robots'],
            'og_title' => $data['og_title'],
            'og_description' => $data['og_description'],
            'og_image' => $data['og_image'],
            'og_image_alt' => $data['og_image_alt'],
            'twitter_title' => $data['twitter_title'],
            'twitter_description' => $data['twitter_description'],
            'twitter_image' => $data['twitter_image'],
            'twitter_card' => $data['twitter_card'],
            'schema_type' => $data['schema_type'],
            'schema_json' => $data['schema_json'],
            'published_at' => $data['published_at'],
        ];

        if ($payload['status'] === 'published' && $payload['published_at'] === null) {
            $payload['published_at'] = gmdate('Y-m-d H:i:s');
        }

        if ($payload['status'] !== 'published') {
            $payload['is_featured'] = 0;
        }

        $this->pdo->beginTransaction();

        try {
            if ($payload['is_featured'] === 1) {
                $clearParams = [];
                $clearSql = 'UPDATE blog_posts SET is_featured = 0';

                if ($id > 0) {
                    $clearSql .= ' WHERE id != :id';
                    $clearParams['id'] = $id;
                }

                $this->prepareStatement($clearSql, $clearParams);
            }

            if ($id > 0) {
                $payload['id'] = $id;

                $this->prepareStatement(
                    <<<'SQL'
                    UPDATE blog_posts
                    SET
                        category_id = :category_id,
                        title = :title,
                        slug = :slug,
                        excerpt = :excerpt,
                        cover_image = :cover_image,
                        cover_alt = :cover_alt,
                        author_name = :author_name,
                        reading_time = :reading_time,
                        status = :status,
                        is_featured = :is_featured,
                        tags = :tags,
                        body_html = :body_html,
                        focus_keyword = :focus_keyword,
                        seo_title = :seo_title,
                        seo_description = :seo_description,
                        canonical_url = :canonical_url,
                        meta_robots = :meta_robots,
                        og_title = :og_title,
                        og_description = :og_description,
                        og_image = :og_image,
                        og_image_alt = :og_image_alt,
                        twitter_title = :twitter_title,
                        twitter_description = :twitter_description,
                        twitter_image = :twitter_image,
                        twitter_card = :twitter_card,
                        schema_type = :schema_type,
                        schema_json = :schema_json,
                        published_at = :published_at
                    WHERE id = :id
                    SQL,
                    $payload
                );
            } else {
                $this->prepareStatement(
                    <<<'SQL'
                    INSERT INTO blog_posts (
                        category_id,
                        title,
                        slug,
                        excerpt,
                        cover_image,
                        cover_alt,
                        author_name,
                        reading_time,
                        status,
                        is_featured,
                        tags,
                        body_html,
                        focus_keyword,
                        seo_title,
                        seo_description,
                        canonical_url,
                        meta_robots,
                        og_title,
                        og_description,
                        og_image,
                        og_image_alt,
                        twitter_title,
                        twitter_description,
                        twitter_image,
                        twitter_card,
                        schema_type,
                        schema_json,
                        published_at
                    ) VALUES (
                        :category_id,
                        :title,
                        :slug,
                        :excerpt,
                        :cover_image,
                        :cover_alt,
                        :author_name,
                        :reading_time,
                        :status,
                        :is_featured,
                        :tags,
                        :body_html,
                        :focus_keyword,
                        :seo_title,
                        :seo_description,
                        :canonical_url,
                        :meta_robots,
                        :og_title,
                        :og_description,
                        :og_image,
                        :og_image_alt,
                        :twitter_title,
                        :twitter_description,
                        :twitter_image,
                        :twitter_card,
                        :schema_type,
                        :schema_json,
                        :published_at
                    )
                    SQL,
                    $payload
                );

                $id = (int) $this->pdo->lastInsertId();
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $id;
    }

    public function getAdminCategoryById(?int $id): array
    {
        if ($id === null || $id <= 0) {
            return $this->defaultAdminCategory();
        }

        $statement = $this->prepareStatement(
            'SELECT id, name, slug, description, accent_color FROM blog_categories WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        $row = $statement->fetch();

        if (!is_array($row)) {
            return $this->defaultAdminCategory();
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'description' => (string) ($row['description'] ?? ''),
            'accent_color' => (string) ($row['accent_color'] ?: '#b5512c'),
        ];
    }

    public function saveCategory(array $data): int
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $payload = [
            'name' => (string) $data['name'],
            'slug' => (string) $data['slug'],
            'description' => $data['description'],
            'accent_color' => $data['accent_color'],
        ];

        if ($id > 0) {
            $payload['id'] = $id;
            $this->prepareStatement(
                'UPDATE blog_categories SET name = :name, slug = :slug, description = :description, accent_color = :accent_color WHERE id = :id',
                $payload
            );

            return $id;
        }

        $this->prepareStatement(
            'INSERT INTO blog_categories (name, slug, description, accent_color) VALUES (:name, :slug, :description, :accent_color)',
            $payload
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function listAdminComments(?string $status = null, int $limit = 120): array
    {
        $sql = <<<'SQL'
        SELECT
            cm.id,
            cm.post_id,
            cm.author_name,
            cm.author_email,
            cm.author_website,
            cm.body,
            cm.status,
            cm.created_at,
            cm.approved_at,
            p.title AS post_title,
            p.slug AS post_slug
        FROM blog_comments cm
        INNER JOIN blog_posts p ON p.id = cm.post_id
        SQL;
        $params = ['limit' => $limit];

        if ($status !== null && in_array($status, self::COMMENT_STATUSES, true)) {
            $sql .= ' WHERE cm.status = :status';
            $params['status'] = $status;
        }

        $sql .= " ORDER BY CASE WHEN cm.status = 'pending' THEN 0 ELSE 1 END, cm.created_at DESC LIMIT :limit";

        $statement = $this->prepareStatement($sql, $params);

        return array_map(fn (array $row): array => $this->mapComment($row, true), $statement->fetchAll());
    }

    public function updateCommentStatus(int $commentId, string $status): void
    {
        $status = $this->normalizeCommentStatus($status);
        $approvedAt = $status === 'approved' ? gmdate('Y-m-d H:i:s') : null;

        $this->prepareStatement(
            'UPDATE blog_comments SET status = :status, approved_at = :approved_at WHERE id = :id',
            [
                'status' => $status,
                'approved_at' => $approvedAt,
                'id' => $commentId,
            ]
        );
    }

    public function getSiteSettings(): array
    {
        $defaults = $this->defaultSettings();
        $statement = $this->pdo->query('SELECT * FROM blog_settings WHERE id = 1 LIMIT 1');
        $row = $statement->fetch();

        if (!is_array($row)) {
            return $defaults;
        }

        return array_merge($defaults, [
            'site_title' => (string) ($row['site_title'] ?? $defaults['site_title']),
            'site_description' => (string) ($row['site_description'] ?? $defaults['site_description']),
            'site_url' => (string) ($row['site_url'] ?? $defaults['site_url']),
            'meta_robots' => (string) ($row['meta_robots'] ?? $defaults['meta_robots']),
            'default_og_image' => (string) ($row['default_og_image'] ?? $defaults['default_og_image']),
            'default_og_image_alt' => (string) ($row['default_og_image_alt'] ?? $defaults['default_og_image_alt']),
            'default_twitter_card' => (string) ($row['default_twitter_card'] ?? $defaults['default_twitter_card']),
            'twitter_site' => (string) ($row['twitter_site'] ?? $defaults['twitter_site']),
            'twitter_creator' => (string) ($row['twitter_creator'] ?? $defaults['twitter_creator']),
            'facebook_app_id' => (string) ($row['facebook_app_id'] ?? $defaults['facebook_app_id']),
            'organization_name' => (string) ($row['organization_name'] ?? $defaults['organization_name']),
            'organization_url' => (string) ($row['organization_url'] ?? $defaults['organization_url']),
            'organization_logo' => (string) ($row['organization_logo'] ?? $defaults['organization_logo']),
            'organization_description' => (string) ($row['organization_description'] ?? $defaults['organization_description']),
            'default_schema_json' => (string) ($row['default_schema_json'] ?? $defaults['default_schema_json']),
        ]);
    }

    public function saveSiteSettings(array $data): void
    {
        $payload = array_merge(['id' => 1], $this->defaultSettings(), $data);

        $this->prepareStatement(
            <<<'SQL'
            INSERT INTO blog_settings (
                id,
                site_title,
                site_description,
                site_url,
                meta_robots,
                default_og_image,
                default_og_image_alt,
                default_twitter_card,
                twitter_site,
                twitter_creator,
                facebook_app_id,
                organization_name,
                organization_url,
                organization_logo,
                organization_description,
                default_schema_json
            ) VALUES (
                :id,
                :site_title,
                :site_description,
                :site_url,
                :meta_robots,
                :default_og_image,
                :default_og_image_alt,
                :default_twitter_card,
                :twitter_site,
                :twitter_creator,
                :facebook_app_id,
                :organization_name,
                :organization_url,
                :organization_logo,
                :organization_description,
                :default_schema_json
            )
            ON DUPLICATE KEY UPDATE
                site_title = VALUES(site_title),
                site_description = VALUES(site_description),
                site_url = VALUES(site_url),
                meta_robots = VALUES(meta_robots),
                default_og_image = VALUES(default_og_image),
                default_og_image_alt = VALUES(default_og_image_alt),
                default_twitter_card = VALUES(default_twitter_card),
                twitter_site = VALUES(twitter_site),
                twitter_creator = VALUES(twitter_creator),
                facebook_app_id = VALUES(facebook_app_id),
                organization_name = VALUES(organization_name),
                organization_url = VALUES(organization_url),
                organization_logo = VALUES(organization_logo),
                organization_description = VALUES(organization_description),
                default_schema_json = VALUES(default_schema_json)
            SQL,
            $payload
        );
    }

    private function selectColumns(bool $includeBody = false): string
    {
        $columns = [
            'p.id',
            'p.category_id',
            'p.title',
            'p.slug',
            'p.excerpt',
            'p.cover_image',
            'p.cover_alt',
            'p.author_name',
            'p.reading_time',
            'p.status',
            'p.is_featured',
            'p.tags',
            'p.view_count',
            'p.focus_keyword',
            'p.seo_title',
            'p.seo_description',
            'p.canonical_url',
            'p.meta_robots',
            'p.og_title',
            'p.og_description',
            'p.og_image',
            'p.og_image_alt',
            'p.twitter_title',
            'p.twitter_description',
            'p.twitter_image',
            'p.twitter_card',
            'p.schema_type',
            'p.schema_json',
            'p.published_at',
            'c.name AS category_name',
            'c.slug AS category_slug',
            'c.accent_color AS category_color',
            'COALESCE(comment_summary.approved_comment_count, 0) AS approved_comment_count',
        ];

        if ($includeBody) {
            $columns[] = 'p.body_html';
        }

        return implode(",\n                ", $columns);
    }

    private function publicFromClause(): string
    {
        return <<<'SQL'
        FROM blog_posts p
        INNER JOIN blog_categories c ON c.id = p.category_id
        LEFT JOIN (
            SELECT
                post_id,
                COUNT(*) AS approved_comment_count
            FROM blog_comments
            WHERE status = 'approved'
            GROUP BY post_id
        ) comment_summary ON comment_summary.post_id = p.id
        SQL;
    }

    private function adminFromClause(): string
    {
        return <<<'SQL'
        FROM blog_posts p
        INNER JOIN blog_categories c ON c.id = p.category_id
        LEFT JOIN (
            SELECT
                post_id,
                COUNT(*) AS total_comment_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_comment_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_comment_count
            FROM blog_comments
            GROUP BY post_id
        ) comment_summary ON comment_summary.post_id = p.id
        SQL;
    }

    private function buildPublishedFilters(?string $categorySlug = null, string $search = '', ?int $excludePostId = null): array
    {
        $clauses = [
            "p.status = 'published'",
            'p.published_at IS NOT NULL',
            'p.published_at <= NOW()',
        ];
        $params = [];

        if ($categorySlug !== null && $categorySlug !== '') {
            $clauses[] = 'c.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        }

        if ($search !== '') {
            $clauses[] = '(p.title LIKE :search OR p.excerpt LIKE :search OR c.name LIKE :search OR p.tags LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($excludePostId !== null) {
            $clauses[] = 'p.id != :exclude_post_id';
            $params['exclude_post_id'] = $excludePostId;
        }

        return [
            'where' => implode(' AND ', $clauses),
            'params' => $params,
        ];
    }

    private function prepareStatement(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $parameter = str_starts_with((string) $name, ':') ? (string) $name : ':' . $name;

            if ($value === null) {
                $statement->bindValue($parameter, null, PDO::PARAM_NULL);
                continue;
            }

            if (is_bool($value)) {
                $statement->bindValue($parameter, $value, PDO::PARAM_BOOL);
                continue;
            }

            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($parameter, $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    private function mapPost(array $row, bool $includeBody = false): array
    {
        $post = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'excerpt' => (string) $row['excerpt'],
            'coverImage' => (string) $row['cover_image'],
            'coverAlt' => (string) ($row['cover_alt'] ?: $row['title']),
            'author' => (string) $row['author_name'],
            'readingTime' => (int) $row['reading_time'],
            'publishedAt' => $this->toIsoDate($row['published_at'] ?? null),
            'isFeatured' => (bool) $row['is_featured'],
            'viewCount' => (int) ($row['view_count'] ?? 0),
            'commentCount' => (int) ($row['approved_comment_count'] ?? 0),
            'tags' => $this->parseTags((string) ($row['tags'] ?? '')),
            'seoTitle' => trim((string) ($row['seo_title'] ?? '')) ?: (string) $row['title'],
            'seoDescription' => trim((string) ($row['seo_description'] ?? '')) ?: (string) $row['excerpt'],
            'category' => [
                'id' => (int) $row['category_id'],
                'name' => (string) $row['category_name'],
                'slug' => (string) $row['category_slug'],
                'color' => (string) ($row['category_color'] ?: '#b5512c'),
            ],
            'seo' => [
                'focusKeyword' => (string) ($row['focus_keyword'] ?? ''),
                'canonicalUrl' => (string) ($row['canonical_url'] ?? ''),
                'metaRobots' => (string) ($row['meta_robots'] ?? 'index,follow'),
                'schemaType' => (string) ($row['schema_type'] ?? 'Article'),
                'schemaJson' => (string) ($row['schema_json'] ?? ''),
                'openGraph' => [
                    'title' => (string) ($row['og_title'] ?? ''),
                    'description' => (string) ($row['og_description'] ?? ''),
                    'image' => (string) ($row['og_image'] ?? ''),
                    'imageAlt' => (string) ($row['og_image_alt'] ?? ''),
                ],
                'twitter' => [
                    'title' => (string) ($row['twitter_title'] ?? ''),
                    'description' => (string) ($row['twitter_description'] ?? ''),
                    'image' => (string) ($row['twitter_image'] ?? ''),
                    'card' => (string) ($row['twitter_card'] ?? 'summary_large_image'),
                ],
            ],
        ];

        if ($includeBody) {
            $post['bodyHtml'] = (string) $row['body_html'];
        }

        return $post;
    }

    private function mapAdminPost(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'status' => (string) $row['status'],
            'is_featured' => (bool) $row['is_featured'],
            'views' => (int) ($row['view_count'] ?? 0),
            'reading_time' => (int) ($row['reading_time'] ?? 0),
            'author_name' => (string) ($row['author_name'] ?? ''),
            'published_at' => (string) ($row['published_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'category' => [
                'id' => (int) $row['category_id'],
                'name' => (string) ($row['category_name'] ?? ''),
                'slug' => (string) ($row['category_slug'] ?? ''),
            ],
            'comments' => [
                'total' => (int) ($row['total_comment_count'] ?? 0),
                'approved' => (int) ($row['approved_comment_count'] ?? 0),
                'pending' => (int) ($row['pending_comment_count'] ?? 0),
            ],
        ];
    }

    private function mapComment(array $row, bool $forAdmin = false): array
    {
        $comment = [
            'id' => (int) $row['id'],
            'postId' => (int) $row['post_id'],
            'authorName' => (string) $row['author_name'],
            'authorEmail' => (string) ($row['author_email'] ?? ''),
            'authorWebsite' => (string) ($row['author_website'] ?? ''),
            'body' => (string) $row['body'],
            'status' => (string) $row['status'],
            'createdAt' => $this->toIsoDate($row['created_at'] ?? null),
            'approvedAt' => $this->toIsoDate($row['approved_at'] ?? null),
        ];

        if ($forAdmin) {
            $comment['postTitle'] = (string) ($row['post_title'] ?? '');
            $comment['postSlug'] = (string) ($row['post_slug'] ?? '');
        }

        return $comment;
    }

    private function parseTags(string $tags): array
    {
        if ($tags === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $tags));
        $items = array_filter($items, static fn (string $tag): bool => $tag !== '');

        return array_values($items);
    }

    private function toIsoDate(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        if ($date instanceof DateTimeImmutable) {
            return $date->format(DATE_ATOM);
        }

        return (string) $value;
    }

    private function normalizePostStatus(string $status): string
    {
        return in_array($status, self::POST_STATUSES, true) ? $status : 'draft';
    }

    private function normalizeCommentStatus(string $status): string
    {
        return in_array($status, self::COMMENT_STATUSES, true) ? $status : 'pending';
    }

    private function defaultAdminPost(): array
    {
        return [
            'id' => 0,
            'category_id' => 0,
            'title' => '',
            'slug' => '',
            'excerpt' => '',
            'cover_image' => '',
            'cover_alt' => '',
            'author_name' => 'NEXTBOMB Team',
            'reading_time' => 4,
            'status' => 'draft',
            'is_featured' => 0,
            'tags' => '',
            'body_html' => '',
            'view_count' => 0,
            'focus_keyword' => '',
            'seo_title' => '',
            'seo_description' => '',
            'canonical_url' => '',
            'meta_robots' => 'index,follow',
            'og_title' => '',
            'og_description' => '',
            'og_image' => '',
            'og_image_alt' => '',
            'twitter_title' => '',
            'twitter_description' => '',
            'twitter_image' => '',
            'twitter_card' => 'summary_large_image',
            'schema_type' => 'Article',
            'schema_json' => '',
            'published_at' => '',
        ];
    }

    private function defaultAdminCategory(): array
    {
        return [
            'id' => 0,
            'name' => '',
            'slug' => '',
            'description' => '',
            'accent_color' => '#b5512c',
        ];
    }

    private function defaultSettings(): array
    {
        return [
            'id' => 1,
            'site_title' => 'NEXTBOMB Blog',
            'site_description' => 'Updates, stories, support explainers, and release notes from NEXTBOMB.',
            'site_url' => '',
            'meta_robots' => 'index,follow',
            'default_og_image' => '/assets/img/static/photo-1694119243549-35ab499dee3d.avif',
            'default_og_image_alt' => 'NEXTBOMB blog cover',
            'default_twitter_card' => 'summary_large_image',
            'twitter_site' => '',
            'twitter_creator' => '',
            'facebook_app_id' => '',
            'organization_name' => 'NEXTBOMB',
            'organization_url' => '',
            'organization_logo' => '/assets/img/pwa-icon-512.png',
            'organization_description' => 'Fast. Safe. Powerful.',
            'default_schema_json' => '',
        ];
    }
}
