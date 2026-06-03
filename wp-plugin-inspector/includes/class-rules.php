<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPI_Rules
{
    public static function categories(): array
    {
        return [
            'seo' => [
                'label' => __('SEO', 'wp-plugin-inspector'),
                'plugins' => [
                    'wordpress-seo',
                    'seo-by-rank-math',
                    'all-in-one-seo-pack',
                    'autodescription',
                    'slim-seo',
                    'wp-seopress',
                ],
                'keywords' => ['yoast', 'rank-math', 'aioseo', 'seo'],
            ],
            'caching' => [
                'label' => __('Cache', 'wp-plugin-inspector'),
                'plugins' => [
                    'litespeed-cache',
                    'wp-rocket',
                    'w3-total-cache',
                    'wp-super-cache',
                    'wp-fastest-cache',
                    'autoptimize',
                    'sg-cachepress',
                    'breeze',
                    'hummingbird-performance',
                ],
                'keywords' => ['cache', 'caching', 'rocket', 'litespeed', 'autoptimize', 'performance'],
            ],
            'security' => [
                'label' => __('Security', 'wp-plugin-inspector'),
                'plugins' => [
                    'wordfence',
                    'sucuri-scanner',
                    'better-wp-security',
                    'all-in-one-wp-security-and-firewall',
                    'wp-cerber',
                    'defender-security',
                    'shield-security',
                ],
                'keywords' => ['security', 'wordfence', 'sucuri', 'cerber', 'firewall'],
            ],
            'backup' => [
                'label' => __('Backup', 'wp-plugin-inspector'),
                'plugins' => [
                    'updraftplus',
                    'backwpup',
                    'duplicator',
                    'all-in-one-wp-migration',
                    'wpvivid-backuprestore',
                    'backupwordpress',
                    'backup-migration',
                ],
                'keywords' => ['backup', 'migration', 'duplicator', 'updraft'],
            ],
            'forms' => [
                'label' => __('Forms', 'wp-plugin-inspector'),
                'plugins' => [
                    'contact-form-7',
                    'fluentform',
                    'wpforms-lite',
                    'gravityforms',
                    'ninja-forms',
                    'formidable',
                    'happyforms',
                    'everest-forms',
                ],
                'keywords' => ['form', 'forms', 'contact-form', 'wpforms', 'gravity'],
            ],
            'smtp' => [
                'label' => __('SMTP / Email Delivery', 'wp-plugin-inspector'),
                'plugins' => [
                    'wp-mail-smtp',
                    'post-smtp',
                    'easy-wp-smtp',
                    'fluent-smtp',
                    'smtp-mailer',
                    'mailgun',
                ],
                'keywords' => ['smtp', 'mail', 'email-delivery'],
            ],
            'image_optimization' => [
                'label' => __('Image Optimization', 'wp-plugin-inspector'),
                'plugins' => [
                    'imagify',
                    'ewww-image-optimizer',
                    'wp-smushit',
                    'shortpixel-image-optimiser',
                    'optimole-wp',
                    'tiny-compress-images',
                    'webp-converter-for-media',
                ],
                'keywords' => ['image', 'imagify', 'smush', 'shortpixel', 'webp', 'optimole'],
            ],
            'page_builder' => [
                'label' => __('Page Builder', 'wp-plugin-inspector'),
                'plugins' => [
                    'elementor',
                    'beaver-builder-lite-version',
                    'js_composer',
                    'siteorigin-panels',
                    'brizy',
                    'oxygen',
                    'visualcomposer',
                    'seedprod-coming-soon-pro-5',
                ],
                'keywords' => ['elementor', 'builder', 'beaver-builder', 'visual-composer', 'oxygen'],
            ],
            'redirection' => [
                'label' => __('Redirection', 'wp-plugin-inspector'),
                'plugins' => [
                    'redirection',
                    'safe-redirect-manager',
                    'quick-pagepost-redirect-plugin',
                    'eps-301-redirects',
                    'rank-math-redirections',
                    'simple-301-redirects',
                ],
                'keywords' => ['redirect', 'redirection', '301'],
            ],
            'analytics' => [
                'label' => __('Analytics', 'wp-plugin-inspector'),
                'plugins' => [
                    'google-site-kit',
                    'google-analytics-for-wordpress',
                    'exactmetrics',
                    'ga-google-analytics',
                    'woocommerce-google-analytics-integration',
                    'independent-analytics',
                ],
                'keywords' => ['analytics', 'google-analytics', 'site-kit', 'monsterinsights'],
            ],
            'commerce' => [
                'label' => __('WooCommerce / Commerce', 'wp-plugin-inspector'),
                'plugins' => [
                    'woocommerce',
                    'easy-digital-downloads',
                    'surecart',
                    'wp-e-commerce',
                ],
                'keywords' => ['woocommerce', 'commerce', 'checkout', 'cart', 'store'],
            ],
        ];
    }

    public static function conflicts(): array
    {
        return [
            [
                'plugins' => ['elementor', 'elementor-pro-old'],
                'title' => __('Elementor and legacy Elementor Pro detected', 'wp-plugin-inspector'),
                'severity' => 'high',
                'message' => __('Old Elementor Pro builds can break widgets and editor loading with newer Elementor versions.', 'wp-plugin-inspector'),
            ],
            [
                'categories' => ['commerce', 'caching'],
                'title' => __('Commerce stack with page caching', 'wp-plugin-inspector'),
                'severity' => 'medium',
                'message' => __('Cart, checkout, account, and payment callback pages should be excluded from full-page cache.', 'wp-plugin-inspector'),
            ],
            [
                'categories' => ['forms', 'smtp'],
                'title' => __('Forms without a clear email-delivery owner', 'wp-plugin-inspector'),
                'severity' => 'low',
                'message' => __('Forms and SMTP plugins can work well together, but failed notification emails are common when both are not configured intentionally.', 'wp-plugin-inspector'),
            ],
            [
                'categories' => ['seo', 'redirection'],
                'title' => __('SEO plugin and redirect manager overlap', 'wp-plugin-inspector'),
                'severity' => 'medium',
                'message' => __('Many SEO plugins include redirect managers. Keep one redirect source of truth to avoid redirect loops and duplicate rules.', 'wp-plugin-inspector'),
            ],
            [
                'categories' => ['backup', 'security'],
                'title' => __('Backup and security stack needs storage review', 'wp-plugin-inspector'),
                'severity' => 'low',
                'message' => __('Security scanners and backup plugins are often both needed, but local backups can increase disk usage and expose sensitive archives if public.', 'wp-plugin-inspector'),
            ],
            [
                'plugins' => ['contact-form-7', 'flamingo', 'akismet'],
                'title' => __('Multiple form spam/data plugins detected', 'wp-plugin-inspector'),
                'severity' => 'low',
                'message' => __('Confirm each add-on is still required for the active forms workflow.', 'wp-plugin-inspector'),
                'match' => 'any_two',
            ],
        ];
    }

    public static function vulnerabilities(): array
    {
        return [
            'elementor' => [
                [
                    'affected_below' => '3.22.0',
                    'severity' => 'medium',
                    'cve' => '',
                    'message' => __('Elementor versions below the maintained branch should be reviewed against the latest security advisories.', 'wp-plugin-inspector'),
                ],
            ],
            'woocommerce' => [
                [
                    'affected_below' => '9.0.0',
                    'severity' => 'high',
                    'cve' => '',
                    'message' => __('Old WooCommerce major versions frequently miss payment, REST API, and order-management fixes.', 'wp-plugin-inspector'),
                ],
            ],
            'contact-form-7' => [
                [
                    'affected_below' => '5.9.0',
                    'severity' => 'medium',
                    'cve' => '',
                    'message' => __('Older form plugins should be updated quickly because public endpoints are easy to target.', 'wp-plugin-inspector'),
                ],
            ],
        ];
    }

    public static function version_rules(): array
    {
        return [
            [
                'plugin' => 'woocommerce',
                'version_at_least' => '10.0.0',
                'conflicts_with' => [
                    'woocommerce-gateway-stripe' => '8.0.0',
                ],
                'title' => __('WooCommerce major version compatibility check', 'wp-plugin-inspector'),
                'message' => __('A connected WooCommerce extension appears older than the tested compatibility baseline.', 'wp-plugin-inspector'),
                'severity' => 'medium',
            ],
        ];
    }
}
