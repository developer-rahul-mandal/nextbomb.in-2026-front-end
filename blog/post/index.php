<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/blog/bootstrap.php';

use Nextbomb\Blog\BlogRepository;
use Nextbomb\Blog\Database;
use function Nextbomb\Blog\build_site_schema;
use function Nextbomb\Blog\build_post_schema;
use function Nextbomb\Blog\csrf_token;
use function Nextbomb\Blog\current_url;
use function Nextbomb\Blog\escape;
use function Nextbomb\Blog\nl2br_safe;
use function Nextbomb\Blog\query_string_param;
use function Nextbomb\Blog\resolve_post_meta;
use function Nextbomb\Blog\site_url;

$slug = query_string_param('slug', 180);
$repository = null;
$settings = [];
$post = null;
$relatedPosts = [];
$comments = [];
$meta = [
    'title' => 'Story unavailable | NEXTBOMB Blog',
    'description' => 'The requested blog story could not be loaded.',
    'canonicalUrl' => current_url(),
    'metaRobots' => 'noindex,nofollow',
    'og' => [
        'title' => 'Story unavailable | NEXTBOMB Blog',
        'description' => 'The requested blog story could not be loaded.',
        'image' => '/assets/img/static/photo-1694119243549-35ab499dee3d.avif',
        'imageAlt' => 'NEXTBOMB blog cover',
    ],
    'twitter' => [
        'title' => 'Story unavailable | NEXTBOMB Blog',
        'description' => 'The requested blog story could not be loaded.',
        'image' => '/assets/img/static/photo-1694119243549-35ab499dee3d.avif',
        'card' => 'summary_large_image',
    ],
];
$schemaJson = null;
$siteSchemaJson = null;
$loadError = null;

try {
    $repository = new BlogRepository(Database::connect());
    $settings = $repository->getSiteSettings();

    if ($slug !== null) {
        $post = $repository->getPostBySlug($slug, true);
    }

    if ($post !== null) {
        $relatedPosts = $repository->getRelatedPosts((int) $post['id'], (int) $post['category']['id'], 3);
        $comments = $repository->listApprovedCommentsForPost((int) $post['id']);
        $meta = resolve_post_meta($post, $settings, current_url());
        $schemaJson = json_encode(
            build_post_schema($post, $settings, current_url()),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    } else {
        $loadError = $slug === null
            ? 'Choose a story from the blog index to load the full article.'
            : 'We could not find that story.';
    }
} catch (Throwable $exception) {
    $loadError = 'Unable to load this story right now. Check config/blog.php and import database/blog_schema.sql.';
}

$siteSchema = build_site_schema($settings);
if (is_array($siteSchema)) {
    $siteSchemaJson = json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

$settings = $settings ?: [
    'site_title' => 'NEXTBOMB Blog',
    'site_description' => 'Updates, stories, support explainers, and release notes from NEXTBOMB.',
    'site_url' => '',
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
];

$csrf = csrf_token();
$ogImageUrl = site_url($settings, (string) $meta['og']['image']);
$twitterImageUrl = site_url($settings, (string) $meta['twitter']['image']);
$coverImage = $post !== null ? site_url($settings, (string) $post['coverImage']) : '../../assets/img/static/photo-1694119243549-35ab499dee3d.avif';

$formatDate = static function (?string $isoDate): string {
    if (!is_string($isoDate) || trim($isoDate) === '') {
        return 'Date unavailable';
    }

    try {
        return (new DateTimeImmutable($isoDate))->format('F j, Y');
    } catch (Throwable) {
        return $isoDate;
    }
};
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= escape($meta['title']) ?></title>
    <meta name="robots" content="<?= escape($meta['metaRobots']) ?>" />
    <meta name="description" content="<?= escape($meta['description']) ?>" />
    <link rel="canonical" href="<?= escape($meta['canonicalUrl']) ?>" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="<?= escape($meta['og']['title']) ?>" />
    <meta property="og:description" content="<?= escape($meta['og']['description']) ?>" />
    <meta property="og:url" content="<?= escape($meta['canonicalUrl']) ?>" />
    <meta property="og:image" content="<?= escape($ogImageUrl) ?>" />
    <meta property="og:image:alt" content="<?= escape($meta['og']['imageAlt']) ?>" />
    <meta property="og:site_name" content="<?= escape((string) $settings['site_title']) ?>" />
    <meta name="twitter:card" content="<?= escape($meta['twitter']['card']) ?>" />
    <meta name="twitter:title" content="<?= escape($meta['twitter']['title']) ?>" />
    <meta name="twitter:description" content="<?= escape($meta['twitter']['description']) ?>" />
    <meta name="twitter:image" content="<?= escape($twitterImageUrl) ?>" />
<?php if ((string) $settings['twitter_site'] !== ''): ?>
    <meta name="twitter:site" content="<?= escape((string) $settings['twitter_site']) ?>" />
<?php endif; ?>
<?php if ((string) $settings['twitter_creator'] !== ''): ?>
    <meta name="twitter:creator" content="<?= escape((string) $settings['twitter_creator']) ?>" />
<?php endif; ?>
<?php if ((string) $settings['facebook_app_id'] !== ''): ?>
    <meta property="fb:app_id" content="<?= escape((string) $settings['facebook_app_id']) ?>" />
<?php endif; ?>
<?php if ($post !== null): ?>
    <meta property="article:published_time" content="<?= escape((string) $post['publishedAt']) ?>" />
    <meta property="article:author" content="<?= escape((string) $post['author']) ?>" />
<?php foreach ($post['tags'] as $tag): ?>
    <meta property="article:tag" content="<?= escape((string) $tag) ?>" />
<?php endforeach; ?>
<?php endif; ?>
    <script src="../../assets/js/theme-init.js?v2"></script>
    <script src="../../assets/js/site-guard.js?v1"></script>
    <link rel="stylesheet" href="../../assets/css/style.css?v18" />
    <link rel="stylesheet" href="../../assets/css/site-enhancements.css?v2" />
    <link rel="stylesheet" href="../../assets/css/blog.css?v2" />
    <link rel="shortcut icon" href="https://nextbomb.in/includes/img/favicon.ico" type="image/x-icon" />
    <meta name="theme-color" content="#fbf6ee" />
    <meta name="color-scheme" content="light dark" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <link rel="manifest" href="../../manifest.webmanifest" />
    <link rel="apple-touch-icon" href="../../assets/img/pwa-icon-192.png" />
<?php if ($schemaJson !== null): ?>
    <script type="application/ld+json"><?= $schemaJson ?></script>
<?php endif; ?>
<?php if ($siteSchemaJson !== null): ?>
    <script type="application/ld+json"><?= $siteSchemaJson ?></script>
<?php endif; ?>
  </head>
  <body data-site-root="../../">
    <div class="page-shell">
      <header class="site-header">
        <a class="brand" href="../../" aria-label="NEXTBOMB home">
          <img src="https://nextbomb.in/includes/img/logo.webp" alt="Nextbomb logo" />
          <span class="brand-copy">
            <strong>NEXTBOMB</strong>
            <span>Fast. Safe. Powerful.</span>
          </span>
        </a>
        <input class="nav-toggle-input" type="checkbox" id="nav-toggle" />
        <label class="mobile-nav-toggle" for="nav-toggle" aria-label="Toggle navigation">
          <span class="mobile-nav-label">Menu</span>
          <span class="mobile-nav-bars" aria-hidden="true"><span></span><span></span><span></span></span>
        </label>
        <div class="header-panel">
          <nav class="site-nav" aria-label="Primary">
            <a class="nav-link" href="../../">Home</a>
            <a class="nav-link nav-link-active" href="../" aria-current="page">Blog</a>
            <details class="nav-group" name="primary-nav">
              <summary>Tools</summary>
              <div class="nav-menu">
                <a href="../../call-bomber">Call Bomber</a>
                <a href="../../sms-bomber">SMS Bomber</a>
                <a href="../../whatsapp-bomber">WhatsApp Bomber</a>
                <a href="../../protect-number">Protect Number</a>
              </div>
            </details>
            <details class="nav-group" name="primary-nav">
              <summary>Info</summary>
              <div class="nav-menu">
                <a href="../../about-us">About Us</a>
                <a href="../../our-donations">Our Donations</a>
                <a href="../../contact-us">Contact Us</a>
                <a href="../../terms-of-service">Terms of Service</a>
                <a href="../../privacy-policy">Privacy Policy</a>
              </div>
            </details>
          </nav>
          <div class="header-actions">
            <button class="theme-toggle" type="button" aria-label="Toggle dark mode" aria-pressed="false">Theme</button>
            <button class="install-link site-install-button" type="button" hidden>Install</button>
          </div>
        </div>
      </header>

      <main class="blog-post-main">
        <section class="hero-section blog-post-hero">
          <div class="hero-copy blog-post-copy">
            <nav class="blog-breadcrumbs" aria-label="Breadcrumb">
              <a href="../../">Home</a>
              <span>/</span>
              <a href="../">Blog</a>
              <span>/</span>
              <span><?= escape($post['title'] ?? 'Story') ?></span>
            </nav>
<?php if ($post !== null): ?>
            <span class="legal-kicker"><?= escape($post['category']['name']) ?></span>
            <h1><?= escape($post['title']) ?></h1>
            <p><?= escape($post['excerpt']) ?></p>
            <div class="blog-meta-row">
              <span class="blog-meta-pill"><?= escape($formatDate($post['publishedAt'])) ?></span>
              <span class="blog-meta-pill"><?= escape((string) $post['author']) ?></span>
              <span class="blog-meta-pill"><?= escape((string) $post['readingTime']) ?> min read</span>
              <span class="blog-meta-pill"><?= escape((string) $post['viewCount']) ?> views</span>
              <span class="blog-meta-pill"><?= escape((string) $post['commentCount']) ?> comments</span>
            </div>
            <div class="blog-tag-row">
<?php foreach ($post['tags'] as $tag): ?>
              <span class="blog-tag-pill"><?= escape((string) $tag) ?></span>
<?php endforeach; ?>
            </div>
<?php else: ?>
            <span class="legal-kicker">Story unavailable</span>
            <h1>We could not load this story.</h1>
            <p><?= escape((string) $loadError) ?></p>
<?php endif; ?>
            <div class="hero-actions legal-actions">
              <a class="button button-secondary" href="../">Back to Blog</a>
              <a class="button button-primary" href="../../tools">Explore Tools</a>
            </div>
          </div>
          <aside class="steps-panel blog-cover-panel">
            <figure class="blog-cover-figure">
              <img src="<?= escape($coverImage) ?>" alt="<?= escape($post['coverAlt'] ?? 'NEXTBOMB blog cover') ?>" />
            </figure>
          </aside>
        </section>

        <section class="content-section blog-post-section">
          <div class="blog-post-layout">
            <article class="blog-article">
              <div class="blog-article-content">
<?php if ($post !== null): ?>
<?= $post['bodyHtml'] ?>
<?php else: ?>
                <p class="blog-empty-state"><?= escape((string) $loadError) ?></p>
<?php endif; ?>
              </div>
            </article>
            <aside class="blog-article-sidebar">
              <div class="blog-side-card">
                <h3>Story Snapshot</h3>
                <div class="blog-side-list">
                  <div class="blog-side-item"><strong>Published</strong><span><?= escape($formatDate($post['publishedAt'] ?? null)) ?></span></div>
                  <div class="blog-side-item"><strong>Views</strong><span><?= escape((string) ($post['viewCount'] ?? 0)) ?></span></div>
                  <div class="blog-side-item"><strong>Comments</strong><span><?= escape((string) ($post['commentCount'] ?? 0)) ?></span></div>
                  <div class="blog-side-item"><strong>SEO</strong><span><?= escape((string) ($post['seo']['schemaType'] ?? 'Article')) ?></span></div>
                </div>
              </div>
              <div class="blog-side-card">
                <h3>More from the journal</h3>
                <div class="blog-related-list">
<?php foreach ($relatedPosts as $related): ?>
                  <a class="blog-related-card" href="./?slug=<?= escape((string) $related['slug']) ?>">
                    <span class="blog-related-category"><?= escape((string) $related['category']['name']) ?></span>
                    <strong><?= escape((string) $related['title']) ?></strong>
                    <span><?= escape($formatDate((string) $related['publishedAt'])) ?> | <?= escape((string) $related['viewCount']) ?> views</span>
                  </a>
<?php endforeach; ?>
<?php if ($relatedPosts === []): ?>
                  <p class="blog-empty-state">No related stories yet.</p>
<?php endif; ?>
                </div>
              </div>
            </aside>
          </div>
        </section>

<?php if ($post !== null): ?>
        <section class="content-section blog-comment-section">
          <div class="section-heading">
            <h2>Comments <span><?= escape((string) count($comments)) ?></span></h2>
            <p>Comments are moderated before they go live. New submissions are added to the admin review queue.</p>
          </div>
          <div class="blog-comments-layout">
            <div class="blog-comment-list" id="blog-comments-list">
<?php foreach ($comments as $comment): ?>
              <article class="blog-comment-card">
                <div class="blog-comment-head">
                  <strong><?= escape((string) $comment['authorName']) ?></strong>
                  <span><?= escape($formatDate((string) $comment['approvedAt'])) ?></span>
                </div>
                <p><?= nl2br_safe((string) $comment['body']) ?></p>
              </article>
<?php endforeach; ?>
<?php if ($comments === []): ?>
              <article class="blog-empty-card"><h3>No approved comments yet.</h3><p>Your comment can be the first one once it passes moderation.</p></article>
<?php endif; ?>
            </div>
            <aside class="blog-comment-form-card">
              <h3>Leave a comment</h3>
              <p>Use your name and email so the team can moderate responsibly.</p>
              <div class="blog-comment-feedback" id="blog-comment-feedback" hidden></div>
              <form class="blog-comment-form" id="blog-comment-form" action="../../api/blog/comment.php" method="post">
                <input type="hidden" name="slug" value="<?= escape((string) $post['slug']) ?>" />
                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>" />
                <input class="blog-honeypot" type="text" name="company" autocomplete="off" tabindex="-1" />
                <label class="blog-comment-field"><span>Name</span><input type="text" name="author_name" maxlength="120" required /></label>
                <label class="blog-comment-field"><span>Email</span><input type="email" name="author_email" maxlength="180" required /></label>
                <label class="blog-comment-field"><span>Website</span><input type="url" name="author_website" maxlength="255" placeholder="https://example.com" /></label>
                <label class="blog-comment-field"><span>Comment</span><textarea name="body" rows="6" maxlength="4000" required></textarea></label>
                <button class="button button-primary button-wide" type="submit">Submit Comment</button>
              </form>
            </aside>
          </div>
        </section>
<?php endif; ?>
      </main>

      <footer class="site-footer" id="footer">
        <div class="footer-grid">
          <div class="footer-brand">
            <a class="brand brand-footer" href="../../" aria-label="NEXTBOMB home">
              <img src="https://nextbomb.in/includes/img/logo.webp" alt="Nextbomb logo" />
              <span class="brand-copy"><strong>NEXTBOMB</strong><span>Fast. Safe. Powerful.</span></span>
            </a>
            <p class="footer-description">Product stories, route updates, support explainers, and design notes, all kept in one place.</p>
            <a class="footer-telegram" href="https://t.me/nextbomb" target="_blank" rel="noopener noreferrer"><span>Join our Telegram</span></a>
          </div>
          <div class="footer-column"><h4>Company Info</h4><a href="../../about-us">About Us</a><a href="../">Blog</a><a href="../../contact-us">Contact Us</a></div>
          <div class="footer-column"><h4>Services</h4><a href="../../call-bomber">Call Bomber</a><a href="../../sms-bomber">SMS Bomber</a><a href="../../whatsapp-bomber">WhatsApp Bomber</a></div>
          <div class="footer-column"><h4>Legal</h4><a href="../../terms-of-service">Terms of Service</a><a href="../../privacy-policy">Privacy Policy</a><a href="../../disclaimer">Disclaimer</a></div>
        </div>
        <p class="footer-meta">&copy; 2022-26 NEXTBOMB. All rights reserved.</p>
      </footer>
    </div>
    <script src="../../assets/js/theme-toggle.js?v1" defer></script>
    <script src="../../assets/js/site-loader.js?v1" defer></script>
    <script src="../../assets/js/site-pwa.js?v1" defer></script>
    <script src="../../assets/js/blog-post.js?v2" defer></script>
  </body>
</html>
