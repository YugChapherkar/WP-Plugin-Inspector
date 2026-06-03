<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPI_Activator
{
    public static function activate(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . WPI_SCAN_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_date datetime NOT NULL,
            health_score tinyint(3) unsigned NOT NULL DEFAULT 0,
            performance_score tinyint(3) unsigned NOT NULL DEFAULT 0,
            security_score tinyint(3) unsigned NOT NULL DEFAULT 0,
            results_json longtext NOT NULL,
            PRIMARY KEY  (id),
            KEY scan_date (scan_date)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
