<?php

/**
 * Quick Fix for Device Type Column
 * 
 * Run this file once to add the device_type column to the heatmap_leben_events table.
 * Access: /wp-content/plugins/heatmap-leben/fix-device-type.php
 * 
 * IMPORTANT: Delete this file after running!
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

global $wpdb;
$table = $wpdb->prefix . 'heatmap_leben_events';

echo '<h1>Device Type Column Fix</h1>';
echo '<p>Checking and fixing the database table...</p>';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if (!$table_exists) {
    echo '<p style="color:red;">❌ Error: Table does not exist. Please activate the plugin first.</p>';
    exit;
}

// Check if column exists
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'device_type'");

if (empty($column_exists)) {
    echo '<p>⚙️ Adding device_type column...</p>';

    $result1 = $wpdb->query("ALTER TABLE `$table` ADD COLUMN `device_type` VARCHAR(20) NOT NULL DEFAULT 'desktop' AFTER `event_type`");

    if ($result1 !== false) {
        echo '<p style="color:green;">✅ Column added successfully!</p>';

        echo '<p>⚙️ Adding index...</p>';
        $result2 = $wpdb->query("ALTER TABLE `$table` ADD INDEX `idx_device_type` (`device_type`)");

        if ($result2 !== false) {
            echo '<p style="color:green;">✅ Index added successfully!</p>';
        } else {
            echo '<p style="color:orange;">⚠️ Warning: Could not add index (may already exist)</p>';
        }
    } else {
        echo '<p style="color:red;">❌ Error adding column: ' . $wpdb->last_error . '</p>';
        exit;
    }
} else {
    echo '<p style="color:green;">✅ Column already exists!</p>';
}

// Update existing records
echo '<p>⚙️ Updating existing records...</p>';
$updated = $wpdb->query("UPDATE `$table` SET device_type = 'desktop' WHERE device_type IS NULL OR device_type = ''");
echo '<p style="color:green;">✅ Updated ' . $updated . ' records.</p>';

echo '<hr>';
echo '<h2 style="color:green;">✅ Fix completed successfully!</h2>';
echo '<p><strong>IMPORTANT:</strong> Please delete this file (fix-device-type.php) for security.</p>';
echo '<p>You can now use the heatmap plugin normally.</p>';
