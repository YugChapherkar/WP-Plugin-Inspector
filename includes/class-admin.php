<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPI_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_ajax_wpi_run_scan', [self::class, 'run_scan']);
        add_action('wp_ajax_wpi_analyze_list', [self::class, 'analyze_list']);
    }

    public static function add_menu(): void
    {
        add_management_page(
            __('Plugin Inspector', 'wp-plugin-inspector'),
            __('Plugin Inspector', 'wp-plugin-inspector'),
            'manage_options',
            'wp-plugin-inspector',
            [self::class, 'render_page']
        );
    }

    public static function enqueue(string $hook): void
    {
        if ('tools_page_wp-plugin-inspector' !== $hook) {
            return;
        }

        wp_enqueue_style('wpi-admin', WPI_PLUGIN_URL . 'assets/admin.css', [], WPI_VERSION);
        wp_enqueue_script('wpi-admin', WPI_PLUGIN_URL . 'assets/admin.js', ['jquery'], WPI_VERSION, true);
        wp_localize_script('wpi-admin', 'WPI', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpi_run_scan'),
            'strings' => [
                'running' => __('Scanning site...', 'wp-plugin-inspector'),
                'failed' => __('Scan failed. Please try again.', 'wp-plugin-inspector'),
            ],
        ]);
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap wpi-wrap">
            <div class="wpi-header">
                <div>
                    <h1><?php esc_html_e('Plugin Inspector', 'wp-plugin-inspector'); ?></h1>
                    <p><?php esc_html_e('Analyze plugin inventory, duplicate functionality, likely conflicts, security update signals, and performance pressure.', 'wp-plugin-inspector'); ?></p>
                </div>
                <button id="wpi-run-scan" class="button button-primary button-hero">
                    <?php esc_html_e('Run Scan', 'wp-plugin-inspector'); ?>
                </button>
            </div>

            <div class="wpi-upload-panel">
                <h2><?php esc_html_e('Client Report Details', 'wp-plugin-inspector'); ?></h2>
                <div class="wpi-report-fields">
                    <label>
                        <?php esc_html_e('Agency / freelancer name', 'wp-plugin-inspector'); ?>
                        <input id="wpi-agency-name" type="text" placeholder="Yug Chapherkar">
                    </label>
                    <label>
                        <?php esc_html_e('Client / site name', 'wp-plugin-inspector'); ?>
                        <input id="wpi-client-name" type="text" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    </label>
                </div>

                <h2><?php esc_html_e('Analyze Plugin List', 'wp-plugin-inspector'); ?></h2>
                <p><?php esc_html_e('Paste plugin slugs or names, one per line, to check known duplicate and conflict rules before touching a live site.', 'wp-plugin-inspector'); ?></p>
                <textarea id="wpi-plugin-list" rows="5" placeholder="wordpress-seo&#10;seo-by-rank-math&#10;wp-rocket"></textarea>
                <button id="wpi-analyze-list" class="button">
                    <?php esc_html_e('Analyze List', 'wp-plugin-inspector'); ?>
                </button>
            </div>

            <div id="wpi-status" class="wpi-status" role="status" aria-live="polite"></div>

            <div id="wpi-results" class="wpi-results is-empty">
                <div class="wpi-empty">
                    <?php esc_html_e('Run a scan to generate your first plugin health report.', 'wp-plugin-inspector'); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function run_scan(): void
    {
        check_ajax_referer('wpi_run_scan', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to run scans.', 'wp-plugin-inspector')], 403);
        }

        $scanner = new WPI_Scanner();
        wp_send_json_success($scanner->run());
    }

    public static function analyze_list(): void
    {
        check_ajax_referer('wpi_run_scan', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to analyze plugin lists.', 'wp-plugin-inspector')], 403);
        }

        $raw_list = isset($_POST['plugins']) ? sanitize_textarea_field(wp_unslash($_POST['plugins'])) : '';

        if ('' === trim($raw_list)) {
            wp_send_json_error(['message' => __('Paste at least one plugin slug or name.', 'wp-plugin-inspector')], 400);
        }

        $scanner = new WPI_Scanner();
        wp_send_json_success($scanner->analyze_plugin_list($raw_list));
    }
}
