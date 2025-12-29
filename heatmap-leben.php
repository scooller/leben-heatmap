<?php

/**
 * Plugin Name: Heatmap Leben
 * Description: Plugin para mapa de calor de múltiples páginas con visualización en admin, informe y exportación de imagen.
 * Version: 1.1.0
 * Author: Leben
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: heatmap-leben
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('HEATMAP_LEBEN_VERSION', '1.1.0');
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

function heatmap_leben_init()
{
    if (is_admin()) {
        new Heatmap_Leben_Admin();
    }
    new Heatmap_Leben_Public();
}
add_action('plugins_loaded', 'heatmap_leben_init');
