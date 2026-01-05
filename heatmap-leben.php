<?php

/**
 * Plugin Name: Heatmap Leben
 * Description: Plugin para mapa de calor de múltiples páginas con visualización en admin, informe y exportación de imagen.
 * Version: 1.3.2
 * Author: Leben
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: heatmap-leben
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('HEATMAP_LEBEN_VERSION', '1.3.2');
define('HEATMAP_LEBEN_PLUGIN_FILE', __FILE__);
define('HEATMAP_LEBEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HEATMAP_LEBEN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload includes
require_once HEATMAP_LEBEN_PLUGIN_DIR . 'includes/class-heatmap-leben-activator.php';
require_once HEATMAP_LEBEN_PLUGIN_DIR . 'includes/class-heatmap-leben-deactivator.php';
require_once HEATMAP_LEBEN_PLUGIN_DIR . 'includes/functions-heatmap-leben-utils.php';
require_once HEATMAP_LEBEN_PLUGIN_DIR . 'includes/class-heatmap-leben-admin.php';
require_once HEATMAP_LEBEN_PLUGIN_DIR . 'includes/class-heatmap-leben-public.php';

register_activation_hook(__FILE__, ['Heatmap_Leben_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Heatmap_Leben_Deactivator', 'deactivate']);

// Ejecutar migraciones automáticas en cada carga
add_action('plugins_loaded', function () {
    // Verificar si necesitamos ejecutar migraciones
    $db_version = get_option('heatmap_leben_db_version', '0');

    if (version_compare($db_version, '1.3.2', '<')) {
        global $wpdb;
        $table = $wpdb->prefix . 'heatmap_leben_events';

        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            // Verificar si la columna device_type existe
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'device_type'");

            if (empty($column_exists)) {
                // Agregar columna si no existe
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN device_type VARCHAR(20) NOT NULL DEFAULT 'desktop' AFTER event_type");
                $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_device_type (device_type)");
            }

            // Migrar datos existentes sin device_type
            $wpdb->query("UPDATE {$table} SET device_type = 'desktop' WHERE device_type IS NULL OR device_type = ''");

            // Actualizar versión de la BD
            update_option('heatmap_leben_db_version', '1.3.1');
        }
    }
}, 5);

function heatmap_leben_init()
{
    if (is_admin()) {
        new Heatmap_Leben_Admin();
    }
    new Heatmap_Leben_Public();
}
add_action('plugins_loaded', 'heatmap_leben_init');
