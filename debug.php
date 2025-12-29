<?php

/**
 * Debug script - remove after testing
 * Verifica que los datos se están guardando en la BD y que los endpoints AJAX funcionan
 */

// Load WordPress
require_once dirname(dirname(dirname(__FILE__))) . '/wp-load.php';

if (!current_user_can('manage_options')) {
    die('Acceso denegado');
}

require_once plugin_dir_path(__FILE__) . 'includes/class-heatmap-leben-activator.php';

global $wpdb;
$table = Heatmap_Leben_Activator::table_name();

echo '<h1>Debug Heatmap Leben</h1>';

echo '<h2>Tabla de BD</h2>';
echo '<p>Tabla esperada: <code>' . $table . '</code></p>';

// Check if table exists using information_schema
$exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
    DB_NAME,
    str_replace($wpdb->prefix, '', $table)
));
echo '<p>¿Tabla existe?: ' . ($exists ? 'SÍ ✓' : 'NO ✗') . '</p>';

if (!$exists) {
    echo '<p style="color:red"><strong>La tabla NO existe. Activa el plugin de nuevo.</strong></p>';
    exit;
}

echo '<h2>Conteo de eventos</h2>';
$count = $wpdb->get_var("SELECT COUNT(*) FROM " . $table);
echo '<p><strong>Total de eventos: ' . $count . '</strong></p>';

if ($count == 0) {
    echo '<p style="color:orange">⚠ Sin datos. Genera datos de prueba desde el admin.</p>';
}

echo '<h2>Primeros 10 eventos</h2>';
$rows = $wpdb->get_results("SELECT id, page_url, event_type, x, y, created_at FROM " . $table . " ORDER BY id DESC LIMIT 10", ARRAY_A);
echo '<table border="1" style="border-collapse:collapse; width:100%">';
echo '<tr><th>ID</th><th>Page URL</th><th>Event</th><th>X</th><th>Y</th><th>Created</th></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . $r['id'] . '</td><td>' . $r['page_url'] . '</td><td>' . $r['event_type'] . '</td><td>' . $r['x'] . '</td><td>' . $r['y'] . '</td><td>' . $r['created_at'] . '</td></tr>';
}
echo '</table>';

echo '<h2>URLs únicas (GROUP BY)</h2>';
$urls = $wpdb->get_results("SELECT page_url, COUNT(*) as c FROM " . $table . " GROUP BY page_url ORDER BY c DESC", ARRAY_A);
echo '<table border="1" style="border-collapse:collapse">';
echo '<tr><th>Page URL</th><th>Count</th></tr>';
foreach ($urls as $u) {
    echo '<tr><td>' . $u['page_url'] . '</td><td>' . $u['c'] . '</td></tr>';
}
echo '</table>';

echo '<h2>Estructura de tabla</h2>';
$cols = $wpdb->get_results("DESCRIBE " . $table, ARRAY_A);
echo '<table border="1" style="border-collapse:collapse">';
echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
foreach ($cols as $c) {
    echo '<tr><td>' . $c['Field'] . '</td><td>' . $c['Type'] . '</td><td>' . $c['Null'] . '</td><td>' . $c['Key'] . '</td><td>' . $c['Default'] . '</td><td>' . $c['Extra'] . '</td></tr>';
}
echo '</table>';
