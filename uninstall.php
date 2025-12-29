<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'heatmap_leben_events';
$wpdb->query("DROP TABLE IF EXISTS {$table}");
