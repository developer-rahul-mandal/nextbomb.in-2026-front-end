CREATE DATABASE IF NOT EXISTS `nextbomb_blog`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `nextbomb_blog`;

DROP TABLE IF EXISTS `blog_comments`;
DROP TABLE IF EXISTS `blog_settings`;
DROP TABLE IF EXISTS `blog_posts`;
DROP TABLE IF EXISTS `blog_categories`;

CREATE TABLE `blog_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `accent_color` CHAR(7) NOT NULL DEFAULT '#b5512c',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blog_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blog_posts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `excerpt` TEXT NOT NULL,
  `cover_image` VARCHAR(255) NOT NULL,
  `cover_alt` VARCHAR(180) DEFAULT NULL,
  `author_name` VARCHAR(120) NOT NULL DEFAULT 'NEXTBOMB Team',
  `reading_time` SMALLINT UNSIGNED NOT NULL DEFAULT 4,
  `status` ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `tags` VARCHAR(255) DEFAULT NULL,
  `body_html` LONGTEXT NOT NULL,
  `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `focus_keyword` VARCHAR(180) DEFAULT NULL,
  `seo_title` VARCHAR(180) DEFAULT NULL,
  `seo_description` VARCHAR(255) DEFAULT NULL,
  `canonical_url` VARCHAR(255) DEFAULT NULL,
  `meta_robots` VARCHAR(120) NOT NULL DEFAULT 'index,follow',
  `og_title` VARCHAR(180) DEFAULT NULL,
  `og_description` VARCHAR(255) DEFAULT NULL,
  `og_image` VARCHAR(255) DEFAULT NULL,
  `og_image_alt` VARCHAR(180) DEFAULT NULL,
  `twitter_title` VARCHAR(180) DEFAULT NULL,
  `twitter_description` VARCHAR(255) DEFAULT NULL,
  `twitter_image` VARCHAR(255) DEFAULT NULL,
  `twitter_card` VARCHAR(40) NOT NULL DEFAULT 'summary_large_image',
  `schema_type` VARCHAR(120) NOT NULL DEFAULT 'Article',
  `schema_json` LONGTEXT DEFAULT NULL,
  `published_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blog_posts_slug` (`slug`),
  KEY `idx_blog_posts_status_published` (`status`, `published_at`),
  KEY `idx_blog_posts_category_id` (`category_id`),
  CONSTRAINT `fk_blog_posts_category`
    FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blog_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `author_name` VARCHAR(120) NOT NULL,
  `author_email` VARCHAR(180) NOT NULL,
  `author_website` VARCHAR(255) DEFAULT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'spam', 'trash') NOT NULL DEFAULT 'pending',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blog_comments_post_id` (`post_id`),
  KEY `idx_blog_comments_status` (`status`),
  CONSTRAINT `fk_blog_comments_post`
    FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blog_settings` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `site_title` VARCHAR(180) NOT NULL,
  `site_description` VARCHAR(255) NOT NULL,
  `site_url` VARCHAR(255) DEFAULT NULL,
  `meta_robots` VARCHAR(120) NOT NULL DEFAULT 'index,follow',
  `default_og_image` VARCHAR(255) DEFAULT NULL,
  `default_og_image_alt` VARCHAR(180) DEFAULT NULL,
  `default_twitter_card` VARCHAR(40) NOT NULL DEFAULT 'summary_large_image',
  `twitter_site` VARCHAR(120) DEFAULT NULL,
  `twitter_creator` VARCHAR(120) DEFAULT NULL,
  `facebook_app_id` VARCHAR(80) DEFAULT NULL,
  `organization_name` VARCHAR(180) DEFAULT NULL,
  `organization_url` VARCHAR(255) DEFAULT NULL,
  `organization_logo` VARCHAR(255) DEFAULT NULL,
  `organization_description` VARCHAR(255) DEFAULT NULL,
  `default_schema_json` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `blog_settings` (
  `id`,
  `site_title`,
  `site_description`,
  `site_url`,
  `meta_robots`,
  `default_og_image`,
  `default_og_image_alt`,
  `default_twitter_card`,
  `twitter_site`,
  `twitter_creator`,
  `facebook_app_id`,
  `organization_name`,
  `organization_url`,
  `organization_logo`,
  `organization_description`,
  `default_schema_json`
) VALUES (
  1,
  'NEXTBOMB Blog',
  'Updates, stories, support explainers, and release notes from NEXTBOMB.',
  '',
  'index,follow',
  '/assets/img/static/photo-1694119243549-35ab499dee3d.avif',
  'NEXTBOMB blog default cover image',
  'summary_large_image',
  '@nextbomb',
  '@nextbomb',
  '',
  'NEXTBOMB',
  '',
  '/assets/img/pwa-icon-512.png',
  'Fast. Safe. Powerful.',
  '{"@context":"https://schema.org","@type":"Organization","name":"NEXTBOMB"}'
);

INSERT INTO `blog_categories` (`name`, `slug`, `description`, `accent_color`) VALUES
  ('Product Notes', 'product-notes', 'Release notes, launches, and shipping notes.', '#b5512c'),
  ('Design System', 'design-system', 'Visual language, UI structure, and layout notes.', '#205866'),
  ('Safety & Support', 'safety-support', 'Support explainers, moderation, and clarity updates.', '#4f7d54'),
  ('Community', 'community', 'Feedback summaries, planning notes, and audience signals.', '#8c381c');

INSERT INTO `blog_posts` (
  `category_id`, `title`, `slug`, `excerpt`, `cover_image`, `cover_alt`, `author_name`,
  `reading_time`, `status`, `is_featured`, `tags`, `body_html`, `view_count`,
  `focus_keyword`, `seo_title`, `seo_description`, `canonical_url`, `meta_robots`,
  `og_title`, `og_description`, `og_image`, `og_image_alt`,
  `twitter_title`, `twitter_description`, `twitter_image`, `twitter_card`,
  `schema_type`, `schema_json`, `published_at`
) VALUES
(
  (SELECT `id` FROM `blog_categories` WHERE `slug` = 'product-notes'),
  'Shipping the NEXTBOMB 2026 interface refresh',
  'shipping-the-nextbomb-2026-interface-refresh',
  'A behind the scenes look at the shared shell, cleaner cards, and visual polish that shaped the 2026 rollout.',
  '/assets/img/static/photo-1758873268550-48f1506510fc.avif',
  'Layered interface cards in the NEXTBOMB style',
  'NEXTBOMB Team',
  5,
  'published',
  1,
  'release notes,interface refresh,frontend',
  '<p>The 2026 refresh was planned around one rule: every new surface should feel like it belongs to the same product family.</p><p>That led us to a shared shell, warmer surfaces, clearer spacing, and stronger calls to action that still leave room for route specific personality.</p><h2>What changed first</h2><p>We started with the pieces visitors touch most often: the header, hero sections, CTA bands, and content cards.</p><ul><li>Unified spacing and radius values across sections.</li><li>Warmer gradients that still hold up in dark mode.</li><li>Reusable content blocks for support pages, tools, and now the blog.</li></ul>',
  1248,
  'nextbomb interface refresh',
  'Shipping the NEXTBOMB 2026 interface refresh',
  'A behind the scenes look at the shared shell, cleaner cards, and visual polish that shaped the 2026 rollout.',
  '',
  'index,follow',
  'Shipping the NEXTBOMB 2026 interface refresh',
  'A behind the scenes look at the shared shell, cleaner cards, and visual polish that shaped the 2026 rollout.',
  '/assets/img/static/photo-1758873268550-48f1506510fc.avif',
  'NEXTBOMB 2026 interface refresh cover',
  'Shipping the NEXTBOMB 2026 interface refresh',
  'A behind the scenes look at the shared shell, cleaner cards, and visual polish that shaped the 2026 rollout.',
  '/assets/img/static/photo-1758873268550-48f1506510fc.avif',
  'summary_large_image',
  'Article',
  '',
  '2026-03-29 10:00:00'
),
(
  (SELECT `id` FROM `blog_categories` WHERE `slug` = 'product-notes'),
  'Why the new blog runs on PHP and MySQL',
  'why-the-new-blog-runs-on-php-and-mysql',
  'The blog stack was chosen to stay simple, server friendly, and easy to deploy beside the current static routes.',
  '/assets/img/static/photo-1694119243549-35ab499dee3d.avif',
  'Warm editorial workspace with layered panels',
  'Rahul Mandal',
  4,
  'published',
  0,
  'php,mysql,backend',
  '<p>The site already had a strong static layer, so the blog backend needed to stay lightweight and predictable.</p><p>PHP and MySQL fit that requirement well: the API endpoints can live beside the existing files, and the schema is easy to maintain on common shared hosting stacks.</p><h2>What this stack unlocks</h2><ul><li>Simple JSON endpoints for the listing and post pages.</li><li>Structured categories, featured stories, and clean slugs.</li><li>A focused editorial workflow without a heavy CMS.</li></ul>',
  812,
  'php mysql blog stack',
  'Why the new blog runs on PHP and MySQL',
  'The blog stack was chosen to stay simple, server friendly, and easy to deploy beside the current static routes.',
  '',
  'index,follow',
  'Why the new blog runs on PHP and MySQL',
  'The blog stack was chosen to stay simple, server friendly, and easy to deploy beside the current static routes.',
  '/assets/img/static/photo-1694119243549-35ab499dee3d.avif',
  'PHP and MySQL blog stack cover',
  'Why the new blog runs on PHP and MySQL',
  'The blog stack was chosen to stay simple, server friendly, and easy to deploy beside the current static routes.',
  '/assets/img/static/photo-1694119243549-35ab499dee3d.avif',
  'summary_large_image',
  'Article',
  '',
  '2026-03-26 08:30:00'
),
(
  (SELECT `id` FROM `blog_categories` WHERE `slug` = 'design-system'),
  'Design tokens that keep every route on brand',
  'design-tokens-that-keep-every-route-on-brand',
  'A closer look at the custom properties and shared section rhythm that make new pages feel at home immediately.',
  '/assets/img/static/premium_photo-1681400209490-bf267d0c17c2.avif',
  'Editorial layout with premium bronze tones',
  'NEXTBOMB Design',
  4,
  'published',
  0,
  'design tokens,scss,theme',
  '<p>Our shared tokens handle the heavy lifting: colors, radii, shadows, backgrounds, and section widths stay consistent even when the page purpose changes.</p><p>For the new blog, we could focus on editorial layout, filters, and article readability instead of rebuilding buttons or dark mode behavior from scratch.</p>',
  694,
  'design tokens blog theme',
  'Design tokens that keep every route on brand',
  'A closer look at the custom properties and shared section rhythm that make new pages feel at home immediately.',
  '',
  'index,follow',
  'Design tokens that keep every route on brand',
  'A closer look at the custom properties and shared section rhythm that make new pages feel at home immediately.',
  '/assets/img/static/premium_photo-1681400209490-bf267d0c17c2.avif',
  'Design tokens editorial cover',
  'Design tokens that keep every route on brand',
  'A closer look at the custom properties and shared section rhythm that make new pages feel at home immediately.',
  '/assets/img/static/premium_photo-1681400209490-bf267d0c17c2.avif',
  'summary_large_image',
  'Article',
  '',
  '2026-03-22 14:15:00'
),
(
  (SELECT `id` FROM `blog_categories` WHERE `slug` = 'safety-support'),
  'Turning support questions into clearer pages',
  'turning-support-questions-into-clearer-pages',
  'How common support conversations now feed page improvements, FAQs, and more direct guidance across the site.',
  '/assets/img/static/photo-1606942257943-dd1859b3e380.avif',
  'Support driven editorial planning',
  'NEXTBOMB Support',
  3,
  'published',
  0,
  'support,content strategy,documentation',
  '<p>Support is one of the best signals a team can get. If the same question keeps appearing, the page usually needs to work harder.</p><p>We now treat those patterns as editorial inputs and turn them into clearer headings, sharper descriptions, and practical explainers.</p>',
  522,
  'support questions clearer pages',
  'Turning support questions into clearer pages',
  'How common support conversations now feed page improvements, FAQs, and more direct guidance across the site.',
  '',
  'index,follow',
  'Turning support questions into clearer pages',
  'How common support conversations now feed page improvements, FAQs, and more direct guidance across the site.',
  '/assets/img/static/photo-1606942257943-dd1859b3e380.avif',
  'Support clarity cover',
  'Turning support questions into clearer pages',
  'How common support conversations now feed page improvements, FAQs, and more direct guidance across the site.',
  '/assets/img/static/photo-1606942257943-dd1859b3e380.avif',
  'summary_large_image',
  'Article',
  '',
  '2026-03-18 11:45:00'
);

INSERT INTO `blog_comments` (
  `post_id`, `author_name`, `author_email`, `author_website`, `body`,
  `status`, `ip_address`, `user_agent`, `approved_at`, `created_at`
) VALUES
(
  (SELECT `id` FROM `blog_posts` WHERE `slug` = 'shipping-the-nextbomb-2026-interface-refresh'),
  'Aman Patel',
  'aman@example.com',
  '',
  'The visual polish on the new cards feels much more intentional. Looking forward to more dev notes like this.',
  'approved',
  '127.0.0.1',
  'seed',
  '2026-03-29 16:15:00',
  '2026-03-29 16:10:00'
),
(
  (SELECT `id` FROM `blog_posts` WHERE `slug` = 'why-the-new-blog-runs-on-php-and-mysql'),
  'Priya Sen',
  'priya@example.com',
  'https://example.com',
  'Clean choice for deployment. A small admin panel on top of this will go a long way.',
  'approved',
  '127.0.0.1',
  'seed',
  '2026-03-27 10:40:00',
  '2026-03-27 10:35:00'
),
(
  (SELECT `id` FROM `blog_posts` WHERE `slug` = 'turning-support-questions-into-clearer-pages'),
  'Rohit',
  'rohit@example.com',
  '',
  'Would love to see more screenshots in future support posts.',
  'pending',
  '127.0.0.1',
  'seed',
  NULL,
  '2026-03-20 08:20:00'
);
