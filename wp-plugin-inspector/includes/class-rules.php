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
            ],
            'caching' => [
                'label' => __('Caching', 'wp-plugin-inspector'),
                'plugins' => [
                    'litespeed-cache',
                    'wp-rocket',
                    'w3-total-cache',
                    'wp-super-cache',
                    'wp-fastest-cache',
                    'autoptimize',
                    'sg-cachepress',
                ],
            ],
            'security' => [
                'label' => __('Security', 'wp-plugin-inspector'),
                'plugins' => [
                    'wordfence',
                    'sucuri-scanner',
                    'better-wp-security',
                    'all-in-one-wp-security-and-firewall',
                    'wp-cerber',
                ],
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
                ],
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
                ],
            ],
            'commerce' => [
                'label' => __('Commerce', 'wp-plugin-inspector'),
                'plugins' => [
                    'woocommerce',
                    'easy-digital-downloads',
                    'surecart',
                    'wp-e-commerce',
                ],
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
                'plugins' => ['woocommerce', 'w3-total-cache'],
                'title' => __('WooCommerce with aggressive page caching', 'wp-plugin-inspector'),
                'severity' => 'medium',
                'message' => __('Cart, checkout, and account pages need explicit cache exclusions.', 'wp-plugin-inspector'),
            ],
            [
                'plugins' => ['woocommerce', 'wp-super-cache'],
                'title' => __('WooCommerce with page cache plugin', 'wp-plugin-inspector'),
                'severity' => 'medium',
                'message' => __('Verify dynamic WooCommerce pages are excluded from full-page cache.', 'wp-plugin-inspector'),
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
