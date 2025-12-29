<?php
if (!defined('ABSPATH')) {
    exit;
}

class Heatmap_Leben_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_hm_leben_list_pages', [$this, 'ajax_list_pages']);
        add_action('wp_ajax_hm_leben_get_points', [$this, 'ajax_get_points']);
        add_action('wp_ajax_hm_leben_export_csv', [$this, 'ajax_export_csv']);
        add_action('wp_ajax_hm_leben_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_hm_leben_generate_test_data', [$this, 'ajax_generate_test_data']);
        add_action('wp_ajax_hm_leben_delete_data', [$this, 'ajax_delete_data']);
        add_action('wp_ajax_hm_leben_upload_screenshot', [$this, 'ajax_upload_screenshot']);
        add_action('wp_ajax_hm_leben_get_screenshot', [$this, 'ajax_get_screenshot']);
        add_action('wp_ajax_hm_leben_event', [$this, 'ajax_hm_leben_event']);
        add_action('wp_ajax_nopriv_hm_leben_event', [$this, 'ajax_hm_leben_event']);
    }

    public function add_menu()
    {
        add_menu_page(
            __('Heatmap', 'heatmap-leben'),
            __('Heatmap', 'heatmap-leben'),
            'manage_options',
            'heatmap-leben',
            [$this, 'render_page'],
            'dashicons-visibility',
            80
        );
        add_submenu_page(
            'heatmap-leben',
            __('Ajustes', 'heatmap-leben'),
            __('Ajustes', 'heatmap-leben'),
            'manage_options',
            'heatmap-leben-settings',
            [$this, 'render_settings']
        );
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function enqueue_assets($hook)
    {
        // Cargar estilos en ambas páginas del plugin
        if ($hook === 'toplevel_page_heatmap-leben' || $hook === 'heatmap_page_heatmap-leben-settings') {
            wp_enqueue_style('heatmap-leben-admin', HEATMAP_LEBEN_PLUGIN_URL . 'assets/css/admin.css', [], HEATMAP_LEBEN_VERSION);
        }

        // Solo cargar JS del heatmap en la página principal
        if ($hook !== 'toplevel_page_heatmap-leben') return;

        // No external CDN dependencies - use native canvas heatmap renderer
        wp_enqueue_script('heatmap-leben-admin', HEATMAP_LEBEN_PLUGIN_URL . 'assets/js/heatmap-admin.js', ['jquery'], HEATMAP_LEBEN_VERSION, true);
        wp_localize_script('heatmap-leben-admin', 'HeatmapLebenAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('heatmap_leben_admin'),
            'siteUrl' => site_url('/'),
        ]);
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) return;
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Mapa de Calor', 'heatmap-leben'); ?></h1>
            <div class="hm-toolbar">
                <label> Página:
                    <select id="hm-page-select"></select>
                </label>
                <label> Desde:
                    <input type="date" id="hm-date-from" />
                </label>
                <label> Hasta:
                    <input type="date" id="hm-date-to" />
                </label>
                <label style="margin-left: 15px;">
                    <select id="hm-event-type">
                        <option value="click" selected>Clics</option>
                        <option value="move">Movimientos</option>
                        <option value="all">Todos</option>
                    </select>
                </label>
                <button class="button" id="hm-refresh">Actualizar</button>
                <button class="button" id="hm-export-img">Exportar imagen</button>
                <button class="button" id="hm-export-csv">Exportar CSV</button>
                <button class="button button-secondary" id="hm-test-data">Generar datos de prueba</button>
            </div>
            <div class="hm-canvas-container">
                <div id="hm-heatmap" class="hm-heatmap"></div>
                <img id="hm-page-screenshot" class="hm-screenshot" src="" alt="" style="display:none;" />
            </div>
            <div id="hm-stats" class="hm-stats"></div>
        </div>
    <?php
    }

    public function register_settings()
    {
        register_setting('heatmap_leben', 'heatmap_leben_settings', [
            'type' => 'array',
            'sanitize_callback' => 'heatmap_leben_sanitize_settings',
            'default' => heatmap_leben_get_settings(),
        ]);
        add_settings_section('hm_main', __('Configuración principal', 'heatmap-leben'), function () {
            echo '<p>' . esc_html__('Ajusta filtros de bots y cómo definir usuarios recurrentes.', 'heatmap-leben') . '</p>';
        }, 'heatmap-leben-settings');

        add_settings_field('bot_filter_enabled', __('Filtrar bots', 'heatmap-leben'), function () {
            $s = heatmap_leben_get_settings();
            echo '<label><input type="checkbox" name="heatmap_leben_settings[bot_filter_enabled]" value="1"' . checked($s['bot_filter_enabled'], true, false) . '> ' . esc_html__('Habilitar filtro de bots', 'heatmap-leben') . '</label>';
        }, 'heatmap-leben-settings', 'hm_main');

        add_settings_field('treat_empty_ua_as_bot', __('UA vacío es bot', 'heatmap-leben'), function () {
            $s = heatmap_leben_get_settings();
            echo '<label><input type="checkbox" name="heatmap_leben_settings[treat_empty_ua_as_bot]" value="1"' . checked($s['treat_empty_ua_as_bot'], true, false) . '> ' . esc_html__('Considerar agentes de usuario vacíos como bots', 'heatmap-leben') . '</label>';
        }, 'heatmap-leben-settings', 'hm_main');

        add_settings_field('bot_patterns', __('Patrones extra de bots', 'heatmap-leben'), function () {
            $s = heatmap_leben_get_settings();
            echo '<textarea name="heatmap_leben_settings[bot_patterns]" rows="4" cols="60">' . esc_textarea($s['bot_patterns']) . '</textarea><p class="description">' . esc_html__('Separados por coma o salto de línea. Se hace coincidencia por substring en minúsculas.', 'heatmap-leben') . '</p>';
        }, 'heatmap-leben-settings', 'hm_main');

        add_settings_field('track_logged_in_users', __('Usuarios logueados', 'heatmap-leben'), function () {
            $s = heatmap_leben_get_settings();
            echo '<label><input type="checkbox" name="heatmap_leben_settings[track_logged_in_users]" value="1"' . checked($s['track_logged_in_users'], true, false) . '> ' . esc_html__('Incluir usuarios autenticados en el tracking', 'heatmap-leben') . '</label>';
        }, 'heatmap-leben-settings', 'hm_main');

        add_settings_field('recurring_definition', __('Definición de recurrente', 'heatmap-leben'), function () {
            $s = heatmap_leben_get_settings();
            echo '<select name="heatmap_leben_settings[recurring_definition]">';
            echo '<option value="boundary"' . selected($s['recurring_definition'], 'boundary', false) . '>' . esc_html__('Visto antes del inicio del rango', 'heatmap-leben') . '</option>';
            echo '<option value="days_lookback"' . selected($s['recurring_definition'], 'days_lookback', false) . '>' . esc_html__('Visto en los últimos N días', 'heatmap-leben') . '</option>';
            echo '</select>';
        }, 'heatmap-leben-settings', 'hm_main');

        add_settings_field('days_lookback', __('Días de retroceso', 'heatmap-leben'), function () {
            $s = heatmap_leben_get_settings();
            echo '<input type="number" min="1" max="365" name="heatmap_leben_settings[days_lookback]" value="' . intval($s['days_lookback']) . '">';
        }, 'heatmap-leben-settings', 'hm_main');
    }

    public function render_settings()
    {
        if (!current_user_can('manage_options')) return;
    ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Ajustes de Heatmap', 'heatmap-leben'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('heatmap_leben');
                do_settings_sections('heatmap-leben-settings');
                submit_button();
                ?>
            </form>

            <div class="hm-delete-section">
                <h3><?php echo esc_html__('Borrar Datos', 'heatmap-leben'); ?></h3>
                <p><?php echo esc_html__('Eliminar datos del heatmap en un rango de fechas específico. Esta acción no se puede deshacer.', 'heatmap-leben'); ?></p>
                <div class="hm-delete-controls">
                    <label>
                        <?php echo esc_html__('Desde:', 'heatmap-leben'); ?>
                        <input type="date" id="hm-delete-from" />
                    </label>
                    <label>
                        <?php echo esc_html__('Hasta:', 'heatmap-leben'); ?>
                        <input type="date" id="hm-delete-to" />
                    </label>
                    <button type="button" class="button button-primary" id="hm-delete-data">
                        <?php echo esc_html__('Borrar Datos', 'heatmap-leben'); ?>
                    </button>
                </div>
                <div id="hm-delete-result"></div>
            </div>

            <div style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #c3c4c7;">
                <h3><?php echo esc_html__('Screenshots de Páginas', 'heatmap-leben'); ?></h3>
                <p><?php echo esc_html__('Sube un screenshot para cada página para visualizar el heatmap sobre la imagen real.', 'heatmap-leben'); ?></p>
                <table class="wp-list-table widefat fixed striped" id="hm-screenshots-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;"><?php echo esc_html__('Página', 'heatmap-leben'); ?></th>
                            <th style="width: 15%;"><?php echo esc_html__('Eventos', 'heatmap-leben'); ?></th>
                            <th style="width: 20%;"><?php echo esc_html__('Screenshot', 'heatmap-leben'); ?></th>
                            <th style="width: 15%;"><?php echo esc_html__('Acción', 'heatmap-leben'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" style="text-align: center;">
                                <button type="button" class="button" id="hm-load-pages"><?php echo esc_html__('Cargar páginas', 'heatmap-leben'); ?></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <script>
                jQuery(document).ready(function($) {
                    const screenshots = <?php echo json_encode(get_option('heatmap_leben_screenshots', [])); ?>;

                    $('#hm-delete-data').on('click', function() {
                        const from = $('#hm-delete-from').val();
                        const to = $('#hm-delete-to').val();

                        if (!from || !to) {
                            alert('<?php echo esc_js(__('Por favor selecciona ambas fechas', 'heatmap-leben')); ?>');
                            return;
                        }

                        if (!confirm('<?php echo esc_js(__('¿Estás seguro de que deseas eliminar estos datos? Esta acción no se puede deshacer.', 'heatmap-leben')); ?>')) {
                            return;
                        }

                        $.post(ajaxurl, {
                            action: 'hm_leben_delete_data',
                            nonce: '<?php echo wp_create_nonce('heatmap_leben_delete'); ?>',
                            from: from,
                            to: to
                        }).done(function(res) {
                            if (res.success) {
                                $('#hm-delete-result').html('<p style="color:green;">' + res.data.message + '</p>');
                            } else {
                                $('#hm-delete-result').html('<p style="color:red;">' + res.data + '</p>');
                            }
                        });
                    });

                    $('#hm-load-pages').on('click', function() {
                        $(this).prop('disabled', true).text('Cargando...');
                        $.post(ajaxurl, {
                            action: 'hm_leben_list_pages',
                            nonce: '<?php echo wp_create_nonce('heatmap_leben_admin'); ?>'
                        }).done(function(res) {
                            if (res && res.success && res.data.length) {
                                const tbody = $('#hm-screenshots-table tbody');
                                tbody.empty();

                                res.data.forEach(function(page, idx) {
                                    const hasScreenshot = screenshots[page.page_url];
                                    const screenshotUrl = hasScreenshot ? '<?php echo wp_upload_dir()['baseurl']; ?>/' : '';

                                    const row = $('<tr>').append(
                                        $('<td>').append(
                                            $('<a>').attr('href', page.page_url).attr('target', '_blank').text(page.page_url)
                                        ),
                                        $('<td>').text(page.c),
                                        $('<td>').attr('id', 'screenshot-status-' + idx).html(
                                            hasScreenshot ?
                                            '<span style="color:green;">✓ Cargado</span> <a href="#" class="preview-screenshot" data-url="' + page.page_url + '">Ver</a>' :
                                            '<span style="color:#999;">Sin screenshot</span>'
                                        ),
                                        $('<td>').append(
                                            $('<input>').attr({
                                                type: 'file',
                                                accept: 'image/*',
                                                id: 'file-' + idx,
                                                'data-url': page.page_url,
                                                style: 'display:none;'
                                            }),
                                            $('<button>').addClass('button button-small upload-screenshot')
                                            .attr('data-idx', idx)
                                            .attr('data-url', page.page_url)
                                            .text('Subir')
                                        )
                                    );
                                    tbody.append(row);
                                });

                                // Eventos para upload
                                $('.upload-screenshot').on('click', function() {
                                    const idx = $(this).data('idx');
                                    $('#file-' + idx).click();
                                });

                                $('input[type="file"]').on('change', function() {
                                    const file = this.files[0];
                                    const url = $(this).data('url');
                                    const idx = this.id.split('-')[1];

                                    if (!file) return;

                                    const formData = new FormData();
                                    formData.append('action', 'hm_leben_upload_screenshot');
                                    formData.append('nonce', '<?php echo wp_create_nonce('heatmap_leben_admin'); ?>');
                                    formData.append('page_url', url);
                                    formData.append('screenshot', file);

                                    $('#screenshot-status-' + idx).html('<span style="color:#999;">Subiendo...</span>');

                                    $.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(res) {
                                            if (res && res.success) {
                                                $('#screenshot-status-' + idx).html(
                                                    '<span style="color:green;">✓ Cargado (' + res.data.width + 'x' + res.data.height + ')</span> ' +
                                                    '<a href="' + res.data.url + '" target="_blank">Ver</a>'
                                                );
                                                screenshots[url] = res.data.attachment_id;
                                            } else {
                                                $('#screenshot-status-' + idx).html('<span style="color:red;">✗ Error</span>');
                                            }
                                        },
                                        error: function() {
                                            $('#screenshot-status-' + idx).html('<span style="color:red;">✗ Error</span>');
                                        }
                                    });
                                });

                                // Preview screenshots
                                $(document).on('click', '.preview-screenshot', function(e) {
                                    e.preventDefault();
                                    const url = $(this).data('url');
                                    $.post(ajaxurl, {
                                        action: 'hm_leben_get_screenshot',
                                        nonce: '<?php echo wp_create_nonce('heatmap_leben_admin'); ?>',
                                        page_url: url
                                    }).done(function(res) {
                                        if (res && res.success && res.data.exists) {
                                            window.open(res.data.url, '_blank');
                                        }
                                    });
                                });

                            } else {
                                $('#hm-screenshots-table tbody').html(
                                    '<tr><td colspan="4" style="text-align:center;">No hay páginas con datos</td></tr>'
                                );
                            }
                        }).always(function() {
                            $('#hm-load-pages').prop('disabled', false).text('Recargar páginas');
                        });
                    });
                });
            </script>
        </div>
<?php
    }

    public function ajax_list_pages()
    {
        check_ajax_referer('heatmap_leben_admin', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        global $wpdb;
        $table = Heatmap_Leben_Activator::table_name();

        // First, check total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        // Get unique pages
        $rows = $wpdb->get_results("SELECT page_url, COALESCE(page_id, 0) as page_id, COUNT(*) as c FROM {$table} GROUP BY page_url, page_id ORDER BY c DESC LIMIT 500", ARRAY_A);
        if (empty($rows)) {
            error_log("Heatmap: Last error = " . $wpdb->last_error);
        }

        wp_send_json_success($rows);
    }

    public function ajax_hm_leben_event()
    {
        check_ajax_referer('heatmap_leben_admin', 'nonce');

        if (!isset($_POST['batch'])) {
            wp_send_json_error('No batch data', 400);
        }

        $batch_json = sanitize_text_field(wp_unslash($_POST['batch']));
        $batch = json_decode($batch_json, true);

        if (empty($batch) || !is_array($batch)) {
            wp_send_json_error('Invalid batch format', 400);
        }

        global $wpdb;
        $table = Heatmap_Leben_Activator::table_name();
        $inserted = 0;

        foreach ($batch as $raw_item) {
            $item = $raw_item;

            // Validar y normalizar
            if (empty($item['t']) || empty($item['page'])) {
                error_log('🚫 Batch item skipped: missing t or page');
                continue;
            }

            $page_url = esc_url_raw($item['page']);
            $page_id = isset($item['pageId']) ? intval($item['pageId']) : 0;
            $event_type = sanitize_text_field($item['t']);

            $x = isset($item['x']) ? intval($item['x']) : 0;
            $y = isset($item['y']) ? intval($item['y']) : 0;
            $viewport_w = isset($item['vw']) ? intval($item['vw']) : 1280;
            $viewport_h = isset($item['vh']) ? intval($item['vh']) : 800;
            $scroll_x = isset($item['sx']) ? intval($item['sx']) : 0;
            $scroll_y = isset($item['sy']) ? intval($item['sy']) : 0;
            $page_height = isset($item['ph']) ? intval($item['ph']) : 0;
            $density = isset($item['d']) ? intval($item['d']) : 1;
            $session_id = isset($item['s']) ? sanitize_text_field($item['s']) : '';

            //error_log('📝 Inserting: x=' . $x . ', y=' . $y . ', sy=' . $scroll_y . ', ph=' . $page_height);

            // Aplicar filtros
            $settings = heatmap_leben_get_settings();

            if ($settings['bot_filter_enabled'] && empty($session_id)) {
                error_log('🤖 Filtered as bot (no session_id)');
                continue;
            }

            // Insertar
            $result = $wpdb->insert($table, [
                'page_url' => $page_url,
                'page_id' => $page_id,
                'event_type' => $event_type,
                'x' => $x,
                'y' => $y,
                'viewport_w' => $viewport_w,
                'viewport_h' => $viewport_h,
                'scroll_x' => $scroll_x,
                'scroll_y' => $scroll_y,
                'page_height' => $page_height,
                'density' => $density,
                'session_id' => $session_id,
                'created_at' => current_time('mysql'),
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
                '%d',
                '%d',
                '%s',
                '%s'
            ]);

            if ($result) {
                $inserted++;
                //error_log('✅ Inserted successfully');
            } else {
                error_log('❌ Insert failed: ' . $wpdb->last_error);
            }
        }
        wp_send_json_success(['inserted' => $inserted]);
    }


    public function ajax_get_points()
    {
        check_ajax_referer('heatmap_leben_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('forbidden', 403);
        }

        $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';
        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
        $to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : '';
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'click';

        if (empty($page_url)) {
            wp_send_json_error('missing page_url', 400);
        }

        global $wpdb;
        $table = Heatmap_Leben_Activator::table_name();

        $where = "WHERE page_url = %s";
        $params = [$page_url];

        if ($from) {
            $where .= " AND created_at >= %s";
            $params[] = $from . ' 00:00:00';
        }

        if ($to) {
            $where .= " AND created_at <= %s";
            $params[] = $to . ' 23:59:59';
        }

        // 🎯 FILTRO DE EVENTOS
        if ($event_type !== 'all') {
            $where .= " AND event_type = %s";
            $params[] = $event_type;
        }

        $sql = $wpdb->prepare(
            "SELECT x, y, viewport_w, viewport_h, scroll_x, scroll_y, page_height, density 
            FROM {$table} 
            {$where} 
            ORDER BY id DESC 
            LIMIT 200000",
            $params
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (empty($rows)) {
            error_log('Heatmap: No rows found');
        }

        wp_send_json_success($rows ?: []);
    }


    public function ajax_get_stats()
    {
        check_ajax_referer('heatmap_leben_admin', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';
        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
        $to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : '';
        if (empty($page_url)) wp_send_json_error('missing page_url', 400);

        global $wpdb;
        $table = Heatmap_Leben_Activator::table_name();
        $where = ['page_url = %s'];
        $params = [$page_url];
        if ($from) {
            $where[] = 'created_at >= %s';
            $params[] = $from . ' 00:00:00';
        }
        if ($to) {
            $where[] = 'created_at <= %s';
            $params[] = $to . ' 23:59:59';
        }
        // Basic counts
        $sqlBase = $wpdb->prepare("SELECT COUNT(*) as total, SUM(event_type='click') as clicks, SUM(event_type='move') as moves, COUNT(DISTINCT session_id) as unique_sessions FROM {$table} WHERE " . implode(' AND ', $where), $params);
        $base = $wpdb->get_row($sqlBase, ARRAY_A);

        // Returning sessions depends on settings
        $settings = heatmap_leben_get_settings();
        $unique = intval($base['unique_sessions'] ?? 0);
        $returning = 0;
        if ($settings['recurring_definition'] === 'days_lookback') {
            // Lookback from start of range (or now if no 'from') by N days
            $startBoundary = $from ? ($from . ' 00:00:00') : current_time('mysql');
            $ts = strtotime($startBoundary);
            $lookbackBoundary = gmdate('Y-m-d H:i:s', $ts - (DAY_IN_SECONDS * max(1, intval($settings['days_lookback']))));
            $sqlReturning = $wpdb->prepare(
                "SELECT COUNT(DISTINCT t.session_id) FROM {$table} t INNER JOIN (
                    SELECT DISTINCT session_id FROM {$table} WHERE " . implode(' AND ', $where) . "
                ) r ON t.session_id = r.session_id WHERE t.page_url = %s AND t.created_at < %s",
                [$page_url, $lookbackBoundary]
            );
            $returning = intval($wpdb->get_var($sqlReturning));
        } else {
            // Default: before the start boundary of the range
            $boundary = $from ? ($from . ' 00:00:00') : null;
            if (!$boundary) {
                $earliestSql = $wpdb->prepare("SELECT MIN(created_at) FROM {$table} WHERE " . implode(' AND ', $where), $params);
                $boundary = $wpdb->get_var($earliestSql);
            }
            if ($boundary) {
                $sqlReturning = $wpdb->prepare(
                    "SELECT COUNT(DISTINCT t.session_id) FROM {$table} t INNER JOIN (
                        SELECT DISTINCT session_id FROM {$table} WHERE " . implode(' AND ', $where) . "
                    ) r ON t.session_id = r.session_id WHERE t.page_url = %s AND t.created_at < %s",
                    [$page_url, $boundary]
                );
                $returning = intval($wpdb->get_var($sqlReturning));
            }
        }

        wp_send_json_success([
            'total' => intval($base['total'] ?? 0),
            'clicks' => intval($base['clicks'] ?? 0),
            'moves' => intval($base['moves'] ?? 0),
            'unique_sessions' => $unique,
            'returning_sessions' => $returning
        ]);
    }

    public function ajax_export_csv()
    {
        check_ajax_referer('heatmap_leben_admin', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        $page_url = isset($_GET['page_url']) ? esc_url_raw(wp_unslash($_GET['page_url'])) : '';
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
        if (empty($page_url)) wp_die('missing page_url');
        global $wpdb;
        $table = Heatmap_Leben_Activator::table_name();
        $where = ['page_url = %s'];
        $params = [$page_url];
        if ($from) {
            $where[] = 'created_at >= %s';
            $params[] = $from . ' 00:00:00';
        }
        if ($to) {
            $where[] = 'created_at <= %s';
            $params[] = $to . ' 23:59:59';
        }
        $sql = $wpdb->prepare("SELECT event_type,x,y,viewport_w,viewport_h,scroll_x,scroll_y,density,created_at FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY id ASC", $params);
        $rows = $wpdb->get_results($sql, ARRAY_A);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="heatmap-export.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($rows ? $rows[0] : ['event_type', 'x', 'y', 'viewport_w', 'viewport_h', 'scroll_x', 'scroll_y', 'density', 'created_at']));
        foreach ($rows as $r) fputcsv($out, $r);
        fclose($out);
        exit;
    }

    public function ajax_generate_test_data()
    {
        check_ajax_referer('heatmap_leben_admin', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);

        try {
            global $wpdb;
            $table = Heatmap_Leben_Activator::table_name();
            $now = current_time('mysql');

            // Obtener páginas reales de WordPress
            $posts = get_posts([
                'post_type' => ['page', 'post'],
                'post_status' => 'publish',
                'numberposts' => 10,
                'orderby' => 'rand'
            ]);

            if (empty($posts)) {
                wp_send_json_error('No hay páginas publicadas', 400);
                return;
            }

            // Construir array de URLs reales
            $pages = [home_url('/')]; // Siempre incluir home
            foreach ($posts as $post) {
                $pages[] = get_permalink($post->ID);
            }

            $inserted = 0;
            for ($i = 0; $i < 200; $i++) {
                $page_url = $pages[array_rand($pages)];
                $x = rand(0, 1200);
                $y = rand(0, 800);
                $vw = 1280;
                $vh = 800;
                $sx = rand(0, 500);
                $sy = rand(0, 2000);
                $event_type = rand(0, 10) < 7 ? 'move' : 'click';
                $density = rand(1, 5);
                $session = 'test_' . rand(1, 30);
                $mins_ago = rand(0, 10080);
                $time = date('Y-m-d H:i:s', strtotime("-{$mins_ago} minutes", strtotime($now)));

                $wpdb->insert(
                    $table,
                    [
                        'page_url' => $page_url,
                        'page_id' => null,
                        'event_type' => $event_type,
                        'x' => $x,
                        'y' => $y,
                        'viewport_w' => $vw,
                        'viewport_h' => $vh,
                        'scroll_x' => $sx,
                        'scroll_y' => $sy,
                        'density' => $density,
                        'session_id' => $session,
                        'created_at' => $time,
                    ],
                    ['%s', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s']
                );
                $inserted++;
            }

            wp_send_json_success(['inserted' => $inserted]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 500);
        }
    }

    public function ajax_delete_data()
    {
        check_ajax_referer('heatmap_leben_delete', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);

        $from = sanitize_text_field($_POST['from'] ?? '');
        $to = sanitize_text_field($_POST['to'] ?? '');

        if (empty($from) || empty($to)) {
            wp_send_json_error(__('Fechas requeridas', 'heatmap-leben'));
        }

        try {
            global $wpdb;
            $table = Heatmap_Leben_Activator::table_name();

            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE created_at >= %s AND created_at <= %s",
                    $from . ' 00:00:00',
                    $to . ' 23:59:59'
                )
            );

            wp_send_json_success([
                'message' => sprintf(
                    __('Se eliminaron %d registros entre %s y %s', 'heatmap-leben'),
                    $deleted,
                    $from,
                    $to
                ),
                'deleted' => $deleted
            ]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 500);
        }
    }

    public function ajax_upload_screenshot()
    {
        check_ajax_referer('heatmap_leben_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }

        // 🎯 OBTENER Y NORMALIZAR URL
        $pageurl = isset($_POST['page_url']) ? wp_unslash($_POST['page_url']) : '';
        // 🎯 NORMALIZAR: Solo la URL sin query strings
        $pageurl = remove_query_arg([], $pageurl);

        if (empty($pageurl)) {
            error_log('Heatmap: Missing pageurl');
            wp_send_json_error('missing pageurl', 400);
        }

        if (empty($_FILES['screenshot'])) {
            error_log('Heatmap: No file uploaded');
            wp_send_json_error('No file uploaded', 400);
        }

        // Handle upload
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file = $_FILES['screenshot'];
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            error_log('Heatmap: Upload error - ' . $upload['error']);
            wp_send_json_error($upload['error'], 400);
        }

        // Create attachment
        $attachment_id = wp_insert_attachment(
            [
                'post_mime_type' => $upload['type'],
                'post_title' => 'Screenshot - ' . $pageurl,
                'post_content' => '',
                'post_status' => 'inherit',
            ],
            $upload['file']
        );

        if (is_wp_error($attachment_id)) {
            error_log('Heatmap: Attachment error - ' . $attachment_id->get_error_message());
            wp_send_json_error($attachment_id->get_error_message(), 400);
        }

        // Generate attachment metadata
        wp_update_attachment_metadata(
            $attachment_id,
            wp_generate_attachment_metadata($attachment_id, $upload['file'])
        );

        // Save screenshot URL to pageurl mapping
        $screenshots = get_option('heatmap_leben_screenshots', []);
        $screenshots[$pageurl] = $attachment_id;
        update_option('heatmap_leben_screenshots', $screenshots);

        wp_send_json_success([
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'width' => wp_get_attachment_metadata($attachment_id)['width'] ?? 0,
            'height' => wp_get_attachment_metadata($attachment_id)['height'] ?? 0,
        ]);
    }

    public function ajax_get_screenshot()
    {
        check_ajax_referer('heatmap_leben_admin', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);

        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        if (empty($page_url)) wp_send_json_error('missing page_url', 400);

        $screenshots = get_option('heatmap_leben_screenshots', []);
        $attachment_id = $screenshots[$page_url] ?? 0;

        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_success(['exists' => false]);
        }

        wp_send_json_success([
            'exists' => true,
            'url' => wp_get_attachment_url($attachment_id),
            'width' => wp_get_attachment_metadata($attachment_id)['width'] ?? 0,
            'height' => wp_get_attachment_metadata($attachment_id)['height'] ?? 0
        ]);
    }
}
