<?php
defined('ABSPATH') || exit;

class ICSFM_Admin_Ajax {

    private ICSFM_Feed_Repository $repo;
    private ICSFM_Poll_Logger $poll_logger;

    public function __construct(ICSFM_Feed_Repository $repo, ICSFM_Poll_Logger $poll_logger) {
        $this->repo = $repo;
        $this->poll_logger = $poll_logger;
    }

    public function init(): void {
        add_action('wp_ajax_icsfm_test_feed', [$this, 'test_feed']);
        add_action('wp_ajax_icsfm_test_webhook', [$this, 'test_webhook']);
        add_action('wp_ajax_icsfm_test_healthcheck', [$this, 'test_healthcheck']);
        add_action('wp_ajax_icsfm_refresh_dashboard', [$this, 'refresh_dashboard']);
    }

    public function test_feed(): void {
        check_ajax_referer('icsfm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';

        if (empty($url)) {
            wp_send_json_error('No URL provided');
        }

        ICSFM_Logger::info('admin', 'Testing feed source URL', ['url' => $url]);

        $start = microtime(true);
        $response = wp_remote_get($url, [
            'timeout'    => 15,
            'user-agent' => 'ICS-Feed-Monitor/1.0 (WordPress)',
            'headers'    => ['Accept' => 'text/calendar, text/plain, */*'],
        ]);
        $elapsed_ms = (int) ((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            ICSFM_Logger::error('admin', 'Feed source test failed', [
                'url'   => $url,
                'error' => $response->get_error_message(),
            ]);
            wp_send_json_error([
                'message' => 'Connection failed: ' . $response->get_error_message(),
                'time_ms' => $elapsed_ms,
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        $is_valid_ics = strpos($body, 'BEGIN:VCALENDAR') !== false;

        // Count events
        $event_count = substr_count($body, 'BEGIN:VEVENT');

        $result = [
            'status_code'  => $status_code,
            'content_type' => $content_type,
            'is_valid_ics' => $is_valid_ics,
            'body_size'    => strlen($body),
            'event_count'  => $event_count,
            'time_ms'      => $elapsed_ms,
            'body_preview' => substr($body, 0, 500),
        ];

        ICSFM_Logger::info('admin', 'Feed source test completed', $result);

        if ($status_code !== 200) {
            wp_send_json_error(array_merge($result, [
                'message' => "HTTP {$status_code} response",
            ]));
        }

        if (!$is_valid_ics) {
            wp_send_json_error(array_merge($result, [
                'message' => 'Response does not contain valid ICS data (no BEGIN:VCALENDAR found)',
            ]));
        }

        wp_send_json_success(array_merge($result, [
            'message' => "Valid ICS feed with {$event_count} event(s). Response time: {$elapsed_ms}ms.",
        ]));
    }

    public function test_webhook(): void {
        check_ajax_referer('icsfm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $webhook = new ICSFM_Webhook();
        $success = $webhook->fire_test();

        if ($success) {
            wp_send_json_success(['message' => 'Test webhook sent successfully.']);
        } else {
            wp_send_json_error(['message' => 'Webhook delivery failed. Check the logs for details.']);
        }
    }

    public function test_healthcheck(): void {
        check_ajax_referer('icsfm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $settings = get_option('icsfm_settings', []);
        $url = $settings['healthcheck_url'] ?? '';

        if (empty($url)) {
            wp_send_json_error(['message' => 'No healthcheck URL configured. Save settings first.']);
            return;
        }

        ICSFM_Logger::info('admin', 'Testing healthcheck ping', ['url' => $url]);

        $response = wp_remote_get($url, [
            'timeout'    => 10,
            'user-agent' => 'ICS-Feed-Monitor/' . ICSFM_VERSION,
        ]);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            ICSFM_Logger::error('admin', 'Healthcheck test failed', [
                'url'   => $url,
                'error' => $error,
            ]);
            wp_send_json_error([
                'message' => "Ping failed: {$error}",
                'url'     => $url,
            ]);
            return;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        ICSFM_Logger::info('admin', 'Healthcheck test completed', [
            'url'    => $url,
            'status' => $status,
            'body'   => substr($body, 0, 200),
        ]);

        if ($status >= 200 && $status < 300) {
            wp_send_json_success([
                'message' => "Healthcheck pinged successfully (HTTP {$status}).",
                'url'     => $url,
            ]);
        } else {
            wp_send_json_error([
                'message' => "Healthcheck returned HTTP {$status}.",
                'url'     => $url,
                'body'    => substr($body, 0, 200),
            ]);
        }
    }

    public function refresh_dashboard(): void {
        check_ajax_referer('icsfm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $grouped = $this->repo->get_feeds_grouped_by_apartment();
        $data = [];

        foreach ($grouped as $apartment) {
            $apt_data = [
                'id'    => $apartment->id,
                'name'  => $apartment->name,
                'feeds' => [],
            ];

            foreach ($apartment->feeds as $feed) {
                $status = ICSFM_Admin_Dashboard::get_feed_status($feed);
                $apt_data['feeds'][] = [
                    'id'               => $feed->id,
                    'label'            => $feed->label,
                    'platform'         => $feed->platform,
                    'status'           => $status,
                    'last_polled_at'   => $feed->last_polled_at,
                    'last_polled_ago'  => $feed->last_polled_at
                        ? ICSFM_Admin_Dashboard::time_ago($feed->last_polled_at)
                        : 'Never',
                    'last_fetch_status' => $feed->last_fetch_status,
                    'alert_window'     => $feed->alert_window_hours,
                ];
            }

            $data[] = $apt_data;
        }

        wp_send_json_success([
            'apartments' => $data,
            'next_cron'  => wp_next_scheduled('icsfm_check_stale_feeds'),
        ]);
    }
}
