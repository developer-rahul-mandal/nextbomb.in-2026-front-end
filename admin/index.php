<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/blog/bootstrap.php';

use Nextbomb\Blog\BlogRepository;
use Nextbomb\Blog\Database;
use function Nextbomb\Blog\admin_is_authenticated;
use function Nextbomb\Blog\admin_login;
use function Nextbomb\Blog\admin_logout;
use function Nextbomb\Blog\admin_user;
use function Nextbomb\Blog\csrf_token;
use function Nextbomb\Blog\escape;
use function Nextbomb\Blog\normalize_slug;
use function Nextbomb\Blog\post_bool;
use function Nextbomb\Blog\post_int;
use function Nextbomb\Blog\post_nullable_string;
use function Nextbomb\Blog\post_string;
use function Nextbomb\Blog\pull_flash;
use function Nextbomb\Blog\query_string_param;
use function Nextbomb\Blog\redirect;
use function Nextbomb\Blog\set_flash;
use function Nextbomb\Blog\to_mysql_datetime;
use function Nextbomb\Blog\using_default_admin_password;
use function Nextbomb\Blog\verify_csrf_token;

$section = query_string_param('section', 30) ?? 'dashboard';
$allowedSections = ['dashboard', 'posts', 'comments', 'categories', 'settings'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'dashboard';
}

$postId = isset($_GET['post_id']) ? max(0, (int) $_GET['post_id']) : 0;
$categoryId = isset($_GET['category_id']) ? max(0, (int) $_GET['category_id']) : 0;
$commentFilter = query_string_param('status', 20);
$pageErrors = [];
$flash = pull_flash();

$adminUrl = static function (string $target, array $params = []): string {
    $query = array_merge(['section' => $target], $params);
    return './?' . http_build_query($query);
};

$formatDate = static function (?string $value, string $format = 'M j, Y g:i A'): string {
    if (!is_string($value) || trim($value) === '') {
        return 'Not scheduled';
    }

    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable) {
        return $value;
    }
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = post_string($_POST, 'action', 40);

    if ($action === 'login') {
        if (!admin_login(post_string($_POST, 'username', 120), post_string($_POST, 'password', 120))) {
            $pageErrors[] = 'Invalid admin credentials.';
        } else {
            set_flash('success', 'Welcome back. The admin panel is ready.');
            redirect($adminUrl('dashboard'));
        }
    }

    if ($action === 'logout') {
        admin_logout();
        redirect('./');
    }
}

$repository = null;
$dbError = null;
$stats = [];
$posts = [];
$comments = [];
$categories = [];
$settings = [];
$currentPost = null;
$currentCategory = null;

if (admin_is_authenticated()) {
    try {
        $repository = new BlogRepository(Database::connect());

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = post_string($_POST, 'action', 40);

            if (!in_array($action, ['login', 'logout'], true) && !verify_csrf_token(post_string($_POST, 'csrf_token', 255))) {
                $pageErrors[] = 'Your session expired. Refresh the page and try again.';
            } elseif ($action === 'save_post') {
                $section = 'posts';
                $currentPost = [
                    'id' => post_int($_POST, 'id', 0, 0),
                    'category_id' => post_int($_POST, 'category_id', 0, 0),
                    'title' => post_string($_POST, 'title', 180),
                    'slug' => normalize_slug(post_string($_POST, 'slug', 190)),
                    'excerpt' => post_string($_POST, 'excerpt', 8000),
                    'cover_image' => post_string($_POST, 'cover_image', 255),
                    'cover_alt' => post_string($_POST, 'cover_alt', 180),
                    'author_name' => post_string($_POST, 'author_name', 120),
                    'reading_time' => post_int($_POST, 'reading_time', 4, 1, 120),
                    'status' => post_string($_POST, 'status', 20, 'draft'),
                    'is_featured' => post_bool($_POST, 'is_featured') ? 1 : 0,
                    'tags' => post_string($_POST, 'tags', 255),
                    'body_html' => post_string($_POST, 'body_html', 65000),
                    'focus_keyword' => post_string($_POST, 'focus_keyword', 180),
                    'seo_title' => post_string($_POST, 'seo_title', 180),
                    'seo_description' => post_string($_POST, 'seo_description', 255),
                    'canonical_url' => post_string($_POST, 'canonical_url', 255),
                    'meta_robots' => post_string($_POST, 'meta_robots', 120, 'index,follow'),
                    'og_title' => post_string($_POST, 'og_title', 180),
                    'og_description' => post_string($_POST, 'og_description', 255),
                    'og_image' => post_string($_POST, 'og_image', 255),
                    'og_image_alt' => post_string($_POST, 'og_image_alt', 180),
                    'twitter_title' => post_string($_POST, 'twitter_title', 180),
                    'twitter_description' => post_string($_POST, 'twitter_description', 255),
                    'twitter_image' => post_string($_POST, 'twitter_image', 255),
                    'twitter_card' => post_string($_POST, 'twitter_card', 40, 'summary_large_image'),
                    'schema_type' => post_string($_POST, 'schema_type', 120, 'Article'),
                    'schema_json' => post_string($_POST, 'schema_json', 65000),
                    'published_at' => to_mysql_datetime(post_nullable_string($_POST, 'published_at', 40)),
                ];

                if ($currentPost['category_id'] <= 0 || $currentPost['title'] === '' || $currentPost['slug'] === '' || $currentPost['body_html'] === '') {
                    $pageErrors[] = 'Title, slug, category, and body are required.';
                } else {
                    $savedId = $repository->savePost($currentPost);
                    set_flash('success', 'Post saved with admin SEO, schema, and social metadata fields.');
                    redirect($adminUrl('posts', ['post_id' => $savedId]));
                }
            } elseif ($action === 'save_category') {
                $section = 'categories';
                $currentCategory = [
                    'id' => post_int($_POST, 'id', 0, 0),
                    'name' => post_string($_POST, 'name', 120),
                    'slug' => normalize_slug(post_string($_POST, 'slug', 140)),
                    'description' => post_string($_POST, 'description', 255),
                    'accent_color' => preg_match('/^#[0-9a-fA-F]{6}$/', post_string($_POST, 'accent_color', 7)) === 1 ? post_string($_POST, 'accent_color', 7) : '#b5512c',
                ];

                if ($currentCategory['name'] === '' || $currentCategory['slug'] === '') {
                    $pageErrors[] = 'Category name and slug are required.';
                } else {
                    $savedCategoryId = $repository->saveCategory($currentCategory);
                    set_flash('success', 'Category saved.');
                    redirect($adminUrl('categories', ['category_id' => $savedCategoryId]));
                }
            } elseif ($action === 'update_comment_status') {
                $repository->updateCommentStatus(post_int($_POST, 'comment_id', 0, 0), post_string($_POST, 'status', 20, 'pending'));
                set_flash('success', 'Comment status updated.');
                redirect($adminUrl('comments', ['status' => $commentFilter ?: 'all']));
            } elseif ($action === 'save_settings') {
                $section = 'settings';
                $settings = [
                    'site_title' => post_string($_POST, 'site_title', 180),
                    'site_description' => post_string($_POST, 'site_description', 255),
                    'site_url' => post_string($_POST, 'site_url', 255),
                    'meta_robots' => post_string($_POST, 'meta_robots', 120, 'index,follow'),
                    'default_og_image' => post_string($_POST, 'default_og_image', 255),
                    'default_og_image_alt' => post_string($_POST, 'default_og_image_alt', 180),
                    'default_twitter_card' => post_string($_POST, 'default_twitter_card', 40, 'summary_large_image'),
                    'twitter_site' => post_string($_POST, 'twitter_site', 120),
                    'twitter_creator' => post_string($_POST, 'twitter_creator', 120),
                    'facebook_app_id' => post_string($_POST, 'facebook_app_id', 80),
                    'organization_name' => post_string($_POST, 'organization_name', 180),
                    'organization_url' => post_string($_POST, 'organization_url', 255),
                    'organization_logo' => post_string($_POST, 'organization_logo', 255),
                    'organization_description' => post_string($_POST, 'organization_description', 255),
                    'default_schema_json' => post_string($_POST, 'default_schema_json', 65000),
                ];

                if ($settings['site_title'] === '' || $settings['site_description'] === '') {
                    $pageErrors[] = 'Site title and description are required.';
                } else {
                    $repository->saveSiteSettings($settings);
                    set_flash('success', 'Blog SEO defaults saved.');
                    redirect($adminUrl('settings'));
                }
            }
        }

        $stats = $repository->getDashboardStats();
        $posts = $repository->listAdminPosts($section === 'dashboard' ? 8 : 80);
        $categories = $repository->getAllCategories();
        $comments = $repository->listAdminComments($commentFilter === 'all' ? null : $commentFilter, $section === 'dashboard' ? 8 : 120);
        $settings = $settings ?: $repository->getSiteSettings();
        $currentPost = $currentPost ?? $repository->getAdminPostById($postId);
        $currentCategory = $currentCategory ?? $repository->getAdminCategoryById($categoryId);
    } catch (Throwable $exception) {
        $dbError = 'Database connection or schema issue. Check config/blog.php and import database/blog_schema.sql.';
    }
}

$csrf = csrf_token();
$postPublishedValue = $currentPost && !empty($currentPost['published_at']) ? str_replace(' ', 'T', substr((string) $currentPost['published_at'], 0, 16)) : '';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Blog Admin | NEXTBOMB</title>
    <meta name="robots" content="noindex, nofollow" />
    <script src="../assets/js/theme-init.js?v2"></script>
    <script src="../assets/js/site-guard.js?v1"></script>
    <link rel="stylesheet" href="../assets/css/style.css?v18" />
    <link rel="stylesheet" href="../assets/css/site-enhancements.css?v2" />
    <link rel="stylesheet" href="../assets/css/admin.css?v1" />
  </head>
  <body>
<?php if (!admin_is_authenticated()): ?>
    <main class="admin-login-wrap">
      <section class="admin-login-card">
        <p class="admin-kicker">NEXTBOMB Blog Admin</p>
        <h2>Sign in to manage posts, SEO, comments, and stats.</h2>
        <p>The admin route is <code>/admin/</code>. Update <code>config/admin.php</code> or set <code>BLOG_ADMIN_USER</code> and <code>BLOG_ADMIN_PASS</code> before production.</p>
<?php if ($pageErrors !== []): ?><div class="admin-alert is-error"><p><?= escape(implode(' ', $pageErrors)) ?></p></div><?php endif; ?>
        <form class="admin-login-form" method="post">
          <input type="hidden" name="action" value="login" />
          <label>Username<input type="text" name="username" maxlength="120" required /></label>
          <label>Password<input type="password" name="password" maxlength="120" required /></label>
          <button class="button button-primary button-wide" type="submit">Open Admin Panel</button>
        </form>
        <div class="admin-help"><a class="button button-secondary" href="../blog">Back to Blog</a></div>
      </section>
    </main>
<?php else: ?>
    <div class="page-shell">
      <header class="site-header">
        <a class="brand" href="../" aria-label="NEXTBOMB home"><img src="https://nextbomb.in/includes/img/logo.webp" alt="Nextbomb logo" /><span class="brand-copy"><strong>NEXTBOMB</strong><span>Blog Admin</span></span></a>
        <div class="header-panel">
          <nav class="site-nav" aria-label="Admin">
            <a class="nav-link" href="../">Site</a>
            <a class="nav-link" href="../blog">Blog</a>
            <a class="nav-link nav-link-active" href="./">Admin</a>
          </nav>
          <div class="header-actions">
            <button class="theme-toggle" type="button" aria-label="Toggle dark mode" aria-pressed="false">Theme</button>
            <form method="post"><input type="hidden" name="action" value="logout" /><button class="install-link" type="submit">Logout</button></form>
          </div>
        </div>
      </header>
      <main class="admin-main">
        <section class="content-section admin-shell">
          <div class="admin-topbar">
            <div>
              <p class="admin-kicker">Logged in as <?= escape(admin_user()) ?></p>
              <h1>Blog Admin</h1>
            </div>
            <div class="admin-actions"><a class="button button-secondary" href="../blog" target="_blank" rel="noopener noreferrer">View Blog</a><a class="button button-primary" href="<?= escape($adminUrl('posts')) ?>#post-editor">New Post</a></div>
          </div>
          <div class="admin-tabs">
<?php foreach ($allowedSections as $tab): ?><a class="admin-tab<?= $section === $tab ? ' is-active' : '' ?>" href="<?= escape($adminUrl($tab)) ?>"><?= escape(ucwords(str_replace('-', ' ', $tab))) ?></a><?php endforeach; ?>
          </div>
<?php if ($flash): ?><div class="admin-alert is-<?= escape((string) $flash['type']) ?>"><p><?= escape((string) $flash['message']) ?></p></div><?php endif; ?>
<?php if ($pageErrors !== []): ?><div class="admin-alert is-error"><p><?= escape(implode(' ', $pageErrors)) ?></p></div><?php endif; ?>
<?php if ($dbError !== null): ?><div class="admin-alert is-error"><p><?= escape($dbError) ?></p></div><?php endif; ?>
<?php if ($dbError === null && $section === 'dashboard'): ?>
          <div class="admin-stat-grid">
            <article class="admin-stat-card"><p>Total posts</p><strong><?= escape((string) ($stats['totalPosts'] ?? 0)) ?></strong></article>
            <article class="admin-stat-card"><p>Published</p><strong><?= escape((string) ($stats['publishedPosts'] ?? 0)) ?></strong></article>
            <article class="admin-stat-card"><p>Drafts</p><strong><?= escape((string) ($stats['draftPosts'] ?? 0)) ?></strong></article>
            <article class="admin-stat-card"><p>Categories</p><strong><?= escape((string) ($stats['totalCategories'] ?? 0)) ?></strong></article>
            <article class="admin-stat-card"><p>Total views</p><strong><?= escape((string) ($stats['totalViews'] ?? 0)) ?></strong></article>
            <article class="admin-stat-card"><p>Pending comments</p><strong><?= escape((string) ($stats['pendingComments'] ?? 0)) ?></strong></article>
            <article class="admin-stat-card"><p>Approved comments</p><strong><?= escape((string) ($stats['approvedComments'] ?? 0)) ?></strong></article>
            <article class="admin-stat-card"><p>Featured posts</p><strong><?= escape((string) ($stats['featuredPosts'] ?? 0)) ?></strong></article>
          </div>
          <div class="admin-grid">
            <article class="admin-card">
              <div class="admin-section-head">
                <h2>Recent posts</h2>
                <p>Views, publish state, and moderation context at a glance.</p>
              </div>
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead><tr><th>Post</th><th>Status</th><th>Views</th><th>Comments</th></tr></thead>
                  <tbody>
<?php foreach ($posts as $item): ?>
                    <tr>
                      <td><strong><?= escape((string) $item['title']) ?></strong><br /><a href="<?= escape($adminUrl('posts', ['post_id' => (int) $item['id']])) ?>">Edit</a></td>
                      <td><span class="admin-badge<?= $item['status'] === 'draft' ? ' is-draft' : '' ?>"><?= escape((string) $item['status']) ?></span></td>
                      <td><?= escape((string) $item['views']) ?></td>
                      <td><?= escape((string) $item['comments']['approved']) ?> approved / <?= escape((string) $item['comments']['pending']) ?> pending</td>
                    </tr>
<?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>
            <article class="admin-card">
              <div class="admin-section-head">
                <h2>Recent comments</h2>
                <p>Moderation queue and latest approved replies.</p>
              </div>
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead><tr><th>Author</th><th>Post</th><th>Status</th></tr></thead>
                  <tbody>
<?php foreach ($comments as $comment): ?>
                    <tr>
                      <td><strong><?= escape((string) $comment['authorName']) ?></strong><br /><?= escape((string) $comment['authorEmail']) ?></td>
                      <td><?= escape((string) $comment['postTitle']) ?></td>
                      <td><span class="admin-badge<?= $comment['status'] === 'approved' ? ' is-approved' : '' ?>"><?= escape((string) $comment['status']) ?></span></td>
                    </tr>
<?php endforeach; ?>
                  </tbody>
                </table>
              </div>
<?php if (using_default_admin_password()): ?>
              <p>Change the default admin password before going live.</p>
<?php endif; ?>
            </article>
          </div>
<?php elseif ($dbError === null && $section === 'posts'): ?>
          <div class="admin-grid">
            <article class="admin-card">
              <div class="admin-section-head">
                <h2>All posts</h2>
                <p>Views count and approved or pending comments update live from the blog tables.</p>
              </div>
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead><tr><th>Title</th><th>Status</th><th>Views</th><th>Comments</th><th>Published</th></tr></thead>
                  <tbody>
<?php foreach ($posts as $item): ?>
                    <tr>
                      <td><strong><?= escape((string) $item['title']) ?></strong><br /><a href="<?= escape($adminUrl('posts', ['post_id' => (int) $item['id']])) ?>#post-editor">Edit post</a></td>
                      <td><span class="admin-badge<?= $item['status'] === 'draft' ? ' is-draft' : '' ?>"><?= escape((string) $item['status']) ?></span></td>
                      <td><?= escape((string) $item['views']) ?></td>
                      <td><?= escape((string) $item['comments']['approved']) ?> / <?= escape((string) $item['comments']['pending']) ?></td>
                      <td><?= escape($formatDate((string) $item['published_at'])) ?></td>
                    </tr>
<?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>
            <aside class="admin-card" id="post-editor">
              <div class="admin-section-head">
                <h2><?= $currentPost && (int) $currentPost['id'] > 0 ? 'Edit post' : 'Create post' ?></h2>
                <p>Includes schema fields, OG fields, canonical URLs, and social SEO defaults.</p>
              </div>
              <form class="admin-editor" method="post">
                <input type="hidden" name="action" value="save_post" />
                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>" />
                <input type="hidden" name="id" value="<?= escape((string) ($currentPost['id'] ?? 0)) ?>" />
                <div class="admin-form-grid">
                  <div class="admin-field"><label for="post-title">Title</label><input id="post-title" name="title" value="<?= escape((string) ($currentPost['title'] ?? '')) ?>" data-slug-source data-slug-target="#post-slug" required /></div>
                  <div class="admin-field"><label for="post-slug">Slug</label><input id="post-slug" name="slug" value="<?= escape((string) ($currentPost['slug'] ?? '')) ?>" required /></div>
                  <div class="admin-field"><label for="post-category">Category</label><select id="post-category" name="category_id" required><option value="">Choose one</option><?php foreach ($categories as $category): ?><option value="<?= escape((string) $category['id']) ?>"<?= (int) ($currentPost['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>><?= escape((string) $category['name']) ?></option><?php endforeach; ?></select></div>
                  <div class="admin-field"><label for="post-author">Author</label><input id="post-author" name="author_name" value="<?= escape((string) ($currentPost['author_name'] ?? '')) ?>" /></div>
                  <div class="admin-field"><label for="post-reading-time">Reading time</label><input id="post-reading-time" type="number" min="1" max="120" name="reading_time" value="<?= escape((string) ($currentPost['reading_time'] ?? 4)) ?>" /></div>
                  <div class="admin-field"><label for="post-status">Status</label><select id="post-status" name="status"><option value="draft"<?= ($currentPost['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Draft</option><option value="published"<?= ($currentPost['status'] ?? '') === 'published' ? ' selected' : '' ?>>Published</option></select></div>
                  <div class="admin-field"><label for="post-published-at">Publish date</label><input id="post-published-at" type="datetime-local" name="published_at" value="<?= escape($postPublishedValue) ?>" /></div>
                  <div class="admin-field"><label for="post-tags">Tags</label><input id="post-tags" name="tags" value="<?= escape((string) ($currentPost['tags'] ?? '')) ?>" placeholder="comma,separated,tags" /></div>
                  <div class="admin-field-full"><label><input type="checkbox" name="is_featured" value="1"<?= !empty($currentPost['is_featured']) ? ' checked' : '' ?> /> Make this the featured story</label></div>
                  <div class="admin-field"><label for="post-cover-image">Cover image</label><input id="post-cover-image" name="cover_image" value="<?= escape((string) ($currentPost['cover_image'] ?? '')) ?>" placeholder="/assets/img/..." /></div>
                  <div class="admin-field"><label for="post-cover-alt">Cover alt text</label><input id="post-cover-alt" name="cover_alt" value="<?= escape((string) ($currentPost['cover_alt'] ?? '')) ?>" /></div>
                  <div class="admin-field-full"><label for="post-excerpt">Excerpt</label><textarea id="post-excerpt" name="excerpt"><?= escape((string) ($currentPost['excerpt'] ?? '')) ?></textarea></div>
                  <div class="admin-field-full"><label for="post-body">Body HTML</label><textarea id="post-body" name="body_html"><?= escape((string) ($currentPost['body_html'] ?? '')) ?></textarea></div>
                </div>
                <details class="admin-editor-block" open>
                  <summary>SEO Core</summary>
                  <div class="admin-editor-panel">
                    <div class="admin-form-grid">
                      <div class="admin-field"><label>Focus keyword</label><input name="focus_keyword" value="<?= escape((string) ($currentPost['focus_keyword'] ?? '')) ?>" /></div>
                      <div class="admin-field"><label>Canonical URL</label><input name="canonical_url" value="<?= escape((string) ($currentPost['canonical_url'] ?? '')) ?>" /></div>
                      <div class="admin-field-full"><label>SEO title</label><input name="seo_title" value="<?= escape((string) ($currentPost['seo_title'] ?? '')) ?>" /></div>
                      <div class="admin-field-full"><label>SEO description</label><textarea name="seo_description"><?= escape((string) ($currentPost['seo_description'] ?? '')) ?></textarea></div>
                      <div class="admin-field-full"><label>Meta robots</label><input name="meta_robots" value="<?= escape((string) ($currentPost['meta_robots'] ?? 'index,follow')) ?>" /></div>
                    </div>
                  </div>
                </details>
                <details class="admin-editor-block">
                  <summary>Open Graph</summary>
                  <div class="admin-editor-panel">
                    <div class="admin-form-grid">
                      <div class="admin-field-full"><label>OG title</label><input name="og_title" value="<?= escape((string) ($currentPost['og_title'] ?? '')) ?>" /></div>
                      <div class="admin-field-full"><label>OG description</label><textarea name="og_description"><?= escape((string) ($currentPost['og_description'] ?? '')) ?></textarea></div>
                      <div class="admin-field"><label>OG image</label><input name="og_image" value="<?= escape((string) ($currentPost['og_image'] ?? '')) ?>" /></div>
                      <div class="admin-field"><label>OG image alt</label><input name="og_image_alt" value="<?= escape((string) ($currentPost['og_image_alt'] ?? '')) ?>" /></div>
                    </div>
                  </div>
                </details>
                <details class="admin-editor-block">
                  <summary>Twitter and Social SEO</summary>
                  <div class="admin-editor-panel">
                    <div class="admin-form-grid">
                      <div class="admin-field-full"><label>Twitter title</label><input name="twitter_title" value="<?= escape((string) ($currentPost['twitter_title'] ?? '')) ?>" /></div>
                      <div class="admin-field-full"><label>Twitter description</label><textarea name="twitter_description"><?= escape((string) ($currentPost['twitter_description'] ?? '')) ?></textarea></div>
                      <div class="admin-field"><label>Twitter image</label><input name="twitter_image" value="<?= escape((string) ($currentPost['twitter_image'] ?? '')) ?>" /></div>
                      <div class="admin-field"><label>Twitter card</label><select name="twitter_card"><option value="summary_large_image"<?= ($currentPost['twitter_card'] ?? '') === 'summary_large_image' ? ' selected' : '' ?>>summary_large_image</option><option value="summary"<?= ($currentPost['twitter_card'] ?? '') === 'summary' ? ' selected' : '' ?>>summary</option></select></div>
                    </div>
                  </div>
                </details>
                <details class="admin-editor-block">
                  <summary>Schema</summary>
                  <div class="admin-editor-panel">
                    <div class="admin-form-grid">
                      <div class="admin-field"><label>Schema type</label><input name="schema_type" value="<?= escape((string) ($currentPost['schema_type'] ?? 'Article')) ?>" /></div>
                      <div class="admin-field-full"><label>Custom schema JSON-LD</label><textarea name="schema_json"><?= escape((string) ($currentPost['schema_json'] ?? '')) ?></textarea></div>
                    </div>
                  </div>
                </details>
                <div class="admin-inline-actions"><button class="button button-primary" type="submit">Save Post</button><a class="button button-secondary" href="<?= escape($adminUrl('posts')) ?>">Reset</a></div>
              </form>
            </aside>
          </div>
<?php elseif ($dbError === null && $section === 'comments'): ?>
          <article class="admin-card">
            <div class="admin-section-head"><h2>Comment moderation</h2><p>Approve, hold, mark spam, or send comments to trash from one place.</p></div>
            <div class="admin-tabs">
              <a class="admin-tab<?= $commentFilter === null || $commentFilter === 'all' ? ' is-active' : '' ?>" href="<?= escape($adminUrl('comments')) ?>">All</a>
              <a class="admin-tab<?= $commentFilter === 'pending' ? ' is-active' : '' ?>" href="<?= escape($adminUrl('comments', ['status' => 'pending'])) ?>">Pending</a>
              <a class="admin-tab<?= $commentFilter === 'approved' ? ' is-active' : '' ?>" href="<?= escape($adminUrl('comments', ['status' => 'approved'])) ?>">Approved</a>
              <a class="admin-tab<?= $commentFilter === 'spam' ? ' is-active' : '' ?>" href="<?= escape($adminUrl('comments', ['status' => 'spam'])) ?>">Spam</a>
            </div>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead><tr><th>Comment</th><th>Post</th><th>Status</th><th>Moderate</th></tr></thead>
                <tbody>
<?php foreach ($comments as $comment): ?>
                  <tr>
                    <td><strong><?= escape((string) $comment['authorName']) ?></strong><br /><?= escape((string) $comment['authorEmail']) ?><br /><?= escape((string) $comment['body']) ?></td>
                    <td><?= escape((string) $comment['postTitle']) ?><br /><a href="../blog/post/?slug=<?= escape((string) $comment['postSlug']) ?>" target="_blank" rel="noopener noreferrer">Open story</a></td>
                    <td><span class="admin-badge<?= $comment['status'] === 'approved' ? ' is-approved' : '' ?>"><?= escape((string) $comment['status']) ?></span></td>
                    <td><form class="admin-inline-actions admin-comment-form" method="post"><input type="hidden" name="action" value="update_comment_status" /><input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>" /><input type="hidden" name="comment_id" value="<?= escape((string) $comment['id']) ?>" /><select name="status"><option value="pending"<?= $comment['status'] === 'pending' ? ' selected' : '' ?>>Pending</option><option value="approved"<?= $comment['status'] === 'approved' ? ' selected' : '' ?>>Approved</option><option value="spam"<?= $comment['status'] === 'spam' ? ' selected' : '' ?>>Spam</option><option value="trash"<?= $comment['status'] === 'trash' ? ' selected' : '' ?>>Trash</option></select><button class="button button-secondary button-compact" type="submit">Update</button></form></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
<?php elseif ($dbError === null && $section === 'categories'): ?>
          <div class="admin-grid">
            <article class="admin-card">
              <div class="admin-section-head"><h2>Categories</h2><p>Accent colors here flow through the public filters, cards, and featured stories.</p></div>
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead><tr><th>Name</th><th>Slug</th><th>Accent</th></tr></thead>
                  <tbody>
<?php foreach ($categories as $category): ?>
                    <tr>
                      <td><strong><?= escape((string) $category['name']) ?></strong><br /><a href="<?= escape($adminUrl('categories', ['category_id' => (int) $category['id']])) ?>">Edit</a></td>
                      <td><?= escape((string) $category['slug']) ?></td>
                      <td><?= escape((string) $category['accent_color']) ?></td>
                    </tr>
<?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>
            <aside class="admin-card">
              <div class="admin-section-head"><h2><?= $currentCategory && (int) $currentCategory['id'] > 0 ? 'Edit category' : 'Create category' ?></h2><p>Used in filters, metadata, and route styling accents.</p></div>
              <form class="admin-editor" method="post">
                <input type="hidden" name="action" value="save_category" />
                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>" />
                <input type="hidden" name="id" value="<?= escape((string) ($currentCategory['id'] ?? 0)) ?>" />
                <div class="admin-form-grid">
                  <div class="admin-field"><label for="category-name">Name</label><input id="category-name" name="name" value="<?= escape((string) ($currentCategory['name'] ?? '')) ?>" data-slug-source data-slug-target="#category-slug" required /></div>
                  <div class="admin-field"><label for="category-slug">Slug</label><input id="category-slug" name="slug" value="<?= escape((string) ($currentCategory['slug'] ?? '')) ?>" required /></div>
                  <div class="admin-field"><label for="category-color">Accent color</label><input id="category-color" name="accent_color" value="<?= escape((string) ($currentCategory['accent_color'] ?? '#b5512c')) ?>" /></div>
                  <div class="admin-field-full"><label for="category-description">Description</label><textarea id="category-description" name="description"><?= escape((string) ($currentCategory['description'] ?? '')) ?></textarea></div>
                </div>
                <div class="admin-inline-actions"><button class="button button-primary" type="submit">Save Category</button><a class="button button-secondary" href="<?= escape($adminUrl('categories')) ?>">Reset</a></div>
              </form>
            </aside>
          </div>
<?php elseif ($dbError === null && $section === 'settings'): ?>
          <article class="admin-card">
            <div class="admin-section-head"><h2>Site SEO and social defaults</h2><p>Fallback metadata used when a post does not override OG, Twitter, canonical, or schema values.</p></div>
            <form class="admin-editor" method="post">
              <input type="hidden" name="action" value="save_settings" />
              <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>" />
              <div class="admin-settings-grid">
                <div class="admin-field"><label>Site title</label><input name="site_title" value="<?= escape((string) ($settings['site_title'] ?? '')) ?>" required /></div>
                <div class="admin-field"><label>Site URL</label><input name="site_url" value="<?= escape((string) ($settings['site_url'] ?? '')) ?>" placeholder="https://example.com" /></div>
                <div class="admin-field-full"><label>Site description</label><textarea name="site_description"><?= escape((string) ($settings['site_description'] ?? '')) ?></textarea></div>
                <div class="admin-field"><label>Meta robots</label><input name="meta_robots" value="<?= escape((string) ($settings['meta_robots'] ?? 'index,follow')) ?>" /></div>
                <div class="admin-field"><label>Default Twitter card</label><select name="default_twitter_card"><option value="summary_large_image"<?= ($settings['default_twitter_card'] ?? '') === 'summary_large_image' ? ' selected' : '' ?>>summary_large_image</option><option value="summary"<?= ($settings['default_twitter_card'] ?? '') === 'summary' ? ' selected' : '' ?>>summary</option></select></div>
                <div class="admin-field"><label>Default OG image</label><input name="default_og_image" value="<?= escape((string) ($settings['default_og_image'] ?? '')) ?>" /></div>
                <div class="admin-field"><label>Default OG image alt</label><input name="default_og_image_alt" value="<?= escape((string) ($settings['default_og_image_alt'] ?? '')) ?>" /></div>
                <div class="admin-field"><label>Twitter site</label><input name="twitter_site" value="<?= escape((string) ($settings['twitter_site'] ?? '')) ?>" placeholder="@nextbomb" /></div>
                <div class="admin-field"><label>Twitter creator</label><input name="twitter_creator" value="<?= escape((string) ($settings['twitter_creator'] ?? '')) ?>" placeholder="@author" /></div>
                <div class="admin-field"><label>Facebook app ID</label><input name="facebook_app_id" value="<?= escape((string) ($settings['facebook_app_id'] ?? '')) ?>" /></div>
                <div class="admin-field"><label>Organization name</label><input name="organization_name" value="<?= escape((string) ($settings['organization_name'] ?? '')) ?>" /></div>
                <div class="admin-field"><label>Organization URL</label><input name="organization_url" value="<?= escape((string) ($settings['organization_url'] ?? '')) ?>" /></div>
                <div class="admin-field"><label>Organization logo</label><input name="organization_logo" value="<?= escape((string) ($settings['organization_logo'] ?? '')) ?>" /></div>
                <div class="admin-field"><label>Organization description</label><input name="organization_description" value="<?= escape((string) ($settings['organization_description'] ?? '')) ?>" /></div>
                <div class="admin-field-full"><label>Default schema JSON-LD</label><textarea name="default_schema_json"><?= escape((string) ($settings['default_schema_json'] ?? '')) ?></textarea></div>
              </div>
              <div class="admin-inline-actions"><button class="button button-primary" type="submit">Save SEO Defaults</button></div>
            </form>
          </article>
<?php endif; ?>
        </section>
      </main>
    </div>
    <script src="../assets/js/theme-toggle.js?v1" defer></script>
    <script src="../assets/js/admin.js?v1" defer></script>
<?php endif; ?>
  </body>
</html>
