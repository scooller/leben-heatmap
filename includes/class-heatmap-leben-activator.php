<?php
if (!defined('ABSPATH')) {
    exit;
}

class Heatmap_Leben_Activator
{
    public static function activate()
    {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        page_url TEXT NOT NULL,
        page_id BIGINT NULL,
        event_type VARCHAR(20) NOT NULL,
        x INT NOT NULL,
        y INT NOT NULL,
        viewport_w INT NOT NULL,
        viewport_h INT NOT NULL,
        scroll_x INT NOT NULL,
        scroll_y INT NOT NULL,
        page_height INT NOT NULL DEFAULT 0,
        density TINYINT UNSIGNED NOT NULL DEFAULT 1,
        session_id VARCHAR(64) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_page_id (page_id),
        KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Verify table was created
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$table_exists) {
            // Force create if dbDelta failed
            $wpdb->query($sql);
        }
    }

    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'heatmap_leben_events';
    }
}
