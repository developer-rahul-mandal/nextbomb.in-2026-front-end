<?php
declare(strict_types=1);

namespace Nextbomb\Blog;

function decode_schema_json(?string $json): ?array
{
    if (!is_string($json) || trim($json) === '') {
        return null;
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : null;
}

function resolve_post_meta(array $post, array $settings, string $requestUrl): array
{
    $canonicalUrl = trim((string) ($post['seo']['canonicalUrl'] ?? ''));

    if ($canonicalUrl === '') {
        $canonicalUrl = $requestUrl;
    }

    $seoTitle = trim((string) ($post['seoTitle'] ?? '')) ?: (string) ($post['title'] ?? '');
    $seoDescription = trim((string) ($post['seoDescription'] ?? '')) ?: (string) ($post['excerpt'] ?? '');
    $coverImage = trim((string) ($post['coverImage'] ?? ''));
    $coverAlt = trim((string) ($post['coverAlt'] ?? '')) ?: (string) ($post['title'] ?? '');

    $metaRobots = trim((string) ($post['seo']['metaRobots'] ?? ''));
    if ($metaRobots === '') {
        $metaRobots = trim((string) ($settings['meta_robots'] ?? 'index,follow')) ?: 'index,follow';
    }

    $ogTitle = trim((string) ($post['seo']['openGraph']['title'] ?? '')) ?: $seoTitle;
    $ogDescription = trim((string) ($post['seo']['openGraph']['description'] ?? '')) ?: $seoDescription;
    $ogImage = trim((string) ($post['seo']['openGraph']['image'] ?? '')) ?: trim((string) ($settings['default_og_image'] ?? '')) ?: $coverImage;
    $ogImageAlt = trim((string) ($post['seo']['openGraph']['imageAlt'] ?? '')) ?: trim((string) ($settings['default_og_image_alt'] ?? '')) ?: $coverAlt;

    $twitterTitle = trim((string) ($post['seo']['twitter']['title'] ?? '')) ?: $ogTitle;
    $twitterDescription = trim((string) ($post['seo']['twitter']['description'] ?? '')) ?: $ogDescription;
    $twitterImage = trim((string) ($post['seo']['twitter']['image'] ?? '')) ?: $ogImage;
    $twitterCard = trim((string) ($post['seo']['twitter']['card'] ?? '')) ?: trim((string) ($settings['default_twitter_card'] ?? 'summary_large_image')) ?: 'summary_large_image';

    return [
        'title' => $seoTitle,
        'description' => $seoDescription,
        'canonicalUrl' => $canonicalUrl,
        'metaRobots' => $metaRobots,
        'og' => [
            'title' => $ogTitle,
            'description' => $ogDescription,
            'image' => $ogImage,
            'imageAlt' => $ogImageAlt,
        ],
        'twitter' => [
            'title' => $twitterTitle,
            'description' => $twitterDescription,
            'image' => $twitterImage,
            'card' => $twitterCard,
        ],
    ];
}

function build_post_schema(array $post, array $settings, string $requestUrl): array
{
    $customSchema = decode_schema_json((string) ($post['seo']['schemaJson'] ?? ''));

    if (is_array($customSchema)) {
        return $customSchema;
    }

    $canonicalUrl = trim((string) ($post['seo']['canonicalUrl'] ?? '')) ?: $requestUrl;
    $coverImage = trim((string) ($post['coverImage'] ?? ''));

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => trim((string) ($post['seo']['schemaType'] ?? '')) ?: 'Article',
        'headline' => (string) ($post['title'] ?? ''),
        'description' => trim((string) ($post['seoDescription'] ?? '')) ?: (string) ($post['excerpt'] ?? ''),
        'datePublished' => (string) ($post['publishedAt'] ?? ''),
        'dateModified' => (string) ($post['publishedAt'] ?? ''),
        'mainEntityOfPage' => $canonicalUrl,
        'author' => [
            '@type' => 'Person',
            'name' => (string) ($post['author'] ?? 'NEXTBOMB Team'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => trim((string) ($settings['organization_name'] ?? '')) ?: 'NEXTBOMB',
            'url' => trim((string) ($settings['organization_url'] ?? '')) ?: site_url($settings),
        ],
    ];

    if ($coverImage !== '') {
        $schema['image'] = [
            site_url($settings, $coverImage),
        ];
    }

    $organizationLogo = trim((string) ($settings['organization_logo'] ?? ''));
    if ($organizationLogo !== '') {
        $schema['publisher']['logo'] = [
            '@type' => 'ImageObject',
            'url' => site_url($settings, $organizationLogo),
        ];
    }

    return $schema;
}

function build_site_schema(array $settings): ?array
{
    $customSchema = decode_schema_json((string) ($settings['default_schema_json'] ?? ''));

    if (is_array($customSchema)) {
        return $customSchema;
    }

    $organizationName = trim((string) ($settings['organization_name'] ?? ''));

    if ($organizationName === '') {
        return null;
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $organizationName,
        'url' => trim((string) ($settings['organization_url'] ?? '')) ?: site_url($settings),
        'description' => trim((string) ($settings['organization_description'] ?? '')),
    ];

    $organizationLogo = trim((string) ($settings['organization_logo'] ?? ''));

    if ($organizationLogo !== '') {
        $schema['logo'] = site_url($settings, $organizationLogo);
    }

    return $schema;
}
