<?php
/**
 * Plugin Name: WP Plugin Inspector
 * Plugin URI: https://example.com/wp-plugin-inspector
 * Description: Scans installed WordPress plugins for duplicate functionality, likely conflicts, security risk signals, and performance pressure.
 * Version: 0.1.0
 * Author: WP Plugin Inspector
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: wp-plugin-inspector
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPI_VERSION', '0.1.0');
define('WPI_PLUGIN_FILE', __FILE__);
define('WPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPI_SCAN_TABLE', 'plugin_inspector_scans');

require_once WPI_PLUGIN_DIR . 'includes/class-activator.php';
require_once WPI_PLUGIN_DIR . 'includes/class-rules.php';
require_once WPI_PLUGIN_DIR . 'includes/class-scanner.php';
require_once WPI_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook(__FILE__, ['WPI_Activator', 'activate']);

add_action('plugins_loaded', static function (): void {
    WPI_Admin::init();
});
