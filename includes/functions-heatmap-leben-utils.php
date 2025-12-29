<?php
if (!defined('ABSPATH')) {
    exit;
}

function heatmap_leben_get_settings()
{
    $defaults = [
        'bot_filter_enabled' => true,
        'bot_patterns' => '',
        'treat_empty_ua_as_bot' => true,
        'track_logged_in_users' => true,
        'recurring_definition' => 'boundary', // 'boundary' or 'days_lookback'
        'days_lookback' => 7,
    ];
    $opt = get_option('heatmap_leben_settings');
    if (!is_array($opt)) $opt = [];
    return array_merge($defaults, $opt);
}

function heatmap_leben_sanitize_settings($input)
{
    $out = [];
    $out['bot_filter_enabled'] = !empty($input['bot_filter_enabled']);
    $out['treat_empty_ua_as_bot'] = !empty($input['treat_empty_ua_as_bot']);
    $out['track_logged_in_users'] = !empty($input['track_logged_in_users']);
    $out['bot_patterns'] = isset($input['bot_patterns']) ? sanitize_textarea_field($input['bot_patterns']) : '';
    $def = isset($input['recurring_definition']) ? sanitize_text_field($input['recurring_definition']) : 'boundary';
    $out['recurring_definition'] = in_array($def, ['boundary', 'days_lookback'], true) ? $def : 'boundary';
    $days = isset($input['days_lookback']) ? intval($input['days_lookback']) : 7;
    $out['days_lookback'] = max(1, min(365, $days));
    return $out;
}

function heatmap_leben_is_bot_user_agent($ua)
{
    $settings = heatmap_leben_get_settings();
    if (!$settings['bot_filter_enabled']) return false;
    if (!$ua) return $settings['treat_empty_ua_as_bot'];
    $ua = strtolower($ua);
    // Common bot/crawler indicators
    $patterns = [
        'bot',
        'crawl',
        'spider',
        'facebookexternalhit',
        'facebot',
        'slackbot',
        'telegrambot',
        'applebot',
        'googlebot',
        'bingbot',
        'baiduspider',
        'yandexbot',
        'duckduckbot',
        'sogou',
        'exabot',
        'ahrefsbot',
        'semrushbot',
        'mj12bot',
        'uptimerobot',
        'wget',
        'curl',
        'httpclient',
        'python-requests',
        'java/',
        'libwww',
        'go-http-client',
        'headless',
        'phantomjs',
        'puppeteer',
        'rendertron',
        'screaming frog',
        'seznambot',
        'petalbot',
        'bytespider',
        'censys',
        'gptbot'
    ];
    // Merge custom patterns from settings (comma or newline separated)
    if (!empty($settings['bot_patterns'])) {
        $extra = preg_split('/[\n,]+/', strtolower($settings['bot_patterns']));
        foreach ($extra as $e) {
            $e = trim($e);
            if ($e) $patterns[] = $e;
        }
    }
    foreach ($patterns as $p) {
        if (strpos($ua, $p) !== false) return true;
    }
    return false;
}
