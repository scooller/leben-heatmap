<?php
if (!defined('ABSPATH')) {
    exit;
}

class Heatmap_Leben_Public
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_nopriv_hm_leben_event', [$this, 'ajax_record_event']);
        add_action('wp_ajax_hm_leben_event', [$this, 'ajax_record_event']);
    }

    public function enqueue_scripts()
    {
        if (is_admin()) return;

        wp_enqueue_script(
            'heatmap-leben-tracker',
            HEATMAP_LEBEN_PLUGIN_URL . 'assets/js/heatmap-tracker.js',
            [],
            HEATMAP_LEBEN_VERSION,
            true
        );

        // 🎯 USAR MISMO NONCE: 'heatmap_leben_admin'
        wp_localize_script(
            'heatmap-leben-tracker',
            'HeatmapLeben',
            [
                'enabled' => true,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('heatmap_leben_admin'),  // 🎯 MISMO NONCE
                'pageId' => is_singular() ? get_the_ID() : 0,
            ]
        );
    }


    public function ajax_record_event()
    {
        // 🎯 USAR MISMO NONCE QUE EN EL JAVASCRIPT: 'heatmap_leben_admin'
        check_ajax_referer('heatmap_leben_admin', 'nonce');

        // Filter bots by User-Agent
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if (heatmap_leben_is_bot_user_agent($ua)) {
            wp_send_json_success(['inserted' => 0, 'skipped' => 'bot']);
        }

        // Exclude logged-in users if configured
        $settings = heatmap_leben_get_settings();
        if (!$settings['track_logged_in_users'] && is_user_logged_in()) {
            wp_send_json_success(['inserted' => 0, 'skipped' => 'logged_in_excluded']);
        }

        $payload = isset($_POST['batch']) ? wp_unslash($_POST['batch']) : '';
        if (!$payload) {
            wp_send_json_error('missing batch', 400);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            wp_send_json_error('invalid payload', 400);
        }

        global $wpdb;
        $table = Heatmap_Leben_Activator::table_name();
        $now = current_time('mysql');
        $inserted = 0;

        foreach ($data as $item) {
            $page_url = isset($item['page']) ? esc_url_raw($item['page']) : '';
            $page_id = isset($item['pageId']) ? intval($item['pageId']) : null;
            $event_type = isset($item['t']) ? sanitize_text_field($item['t']) : 'click';
            $x = isset($item['x']) ? intval($item['x']) : 0;
            $y = isset($item['y']) ? intval($item['y']) : 0;
            $vw = isset($item['vw']) ? intval($item['vw']) : 0;
            $vh = isset($item['vh']) ? intval($item['vh']) : 0;
            $sx = isset($item['sx']) ? intval($item['sx']) : 0;
            $sy = isset($item['sy']) ? intval($item['sy']) : 0;
            $ph = isset($item['ph']) ? intval($item['ph']) : 0;  // 🎯 NUEVO: page_height
            $density = isset($item['d']) ? max(1, min(10, intval($item['d']))) : 1;
            $session = isset($item['s']) ? sanitize_text_field($item['s']) : '';

            if (!$session || strlen($session) < 6) {
                continue;
            }

            if (!$page_url || $x < 0 || $y < 0 || $vw <= 0 || $vh <= 0) {
                continue;
            }

            $ok = $wpdb->insert($table, [
                'page_url' => $page_url,
                'page_id' => $page_id ?: null,
                'event_type' => $event_type,
                'x' => $x,
                'y' => $y,
                'viewport_w' => $vw,
                'viewport_h' => $vh,
                'scroll_x' => $sx,
                'scroll_y' => $sy,
                'page_height' => $ph,  // 🎯 NUEVO
                'density' => $density,
                'session_id' => $session,
                'created_at' => $now,
            ], [
                '%s',
                '%d',
                '%s',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',  // 🎯 NUEVO
                '%d',
                '%s',
                '%s'
            ]);

            if ($ok) $inserted++;
        }

        wp_send_json_success(['inserted' => $inserted]);
    }
}
