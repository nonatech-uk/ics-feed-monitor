<?php
defined('ABSPATH') || exit;

class ICSFM_Proxy_Handler {

    private ICSFM_Feed_Repository $repo;
    private ICSFM_Poll_Logger $poll_logger;

    public function __construct(ICSFM_Feed_Repository $repo, ICSFM_Poll_Logger $poll_logger) {
        $this->repo = $repo;
        $this->poll_logger = $poll_logger;
    }

    public function register_routes(): void {
        register_rest_route('icsfm/v1', '/feed/(?P<token>[a-f0-9]{64})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_proxy_request'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return preg_match('/^[a-f0-9]{64}$/', $param);
                    },
                ],
            ],
        ]);
    }

    public function handle_proxy_request(\WP_REST_Request $request) {
        $token = $request->get_param('token');

        // Rate limiting
        $rate_key = 'icsfm_rate_' . substr($token, 0, 16);
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count > 60) {
            ICSFM_Logger::warning('proxy', 'Rate limit exceeded', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'ip'           => $this->get_client_ip(),
            ]);
            return new \WP_Error('rate_limited', 'Too many requests', ['status' => 429]);
        }
        set_transient($rate_key, $rate_count + 1, HOUR_IN_SECONDS);

        // Look up feed
        $feed = $this->repo->find_by_token($token);
        if (!$feed || !$feed->pair_is_active) {
            return new \WP_Error('not_found', 'Feed not found', ['status' => 404]);
        }

        $feed_label = ICSFM_Feed_Repository::derive_feed_label($feed);

        // Fetch upstream ICS
        $start = microtime(true);
        $response = wp_remote_get($feed->source_url, [
            'timeout'    => 15,
            'user-agent' => 'ICS-Feed-Monitor/1.0 (WordPress)',
            'headers'    => ['Accept' => 'text/calendar, text/plain, */*'],
        ]);
        $elapsed_ms = (int) ((microtime(true) - $start) * 1000);

        $client_ip = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 200)
            : 'unknown';

        // Handle upstream failure
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $error_msg = is_wp_error($response)
                ? $response->get_error_message()
                : 'HTTP ' . wp_remote_retrieve_response_code($response);

            $this->repo->update_poll((int) $feed->id, [
                'last_polled_at'    => current_time('mysql', true),
                'last_poll_ip'      => $client_ip,
                'last_fetch_status' => 'error',
            ]);
            $this->poll_logger->log((int) $feed->id, 'error', $elapsed_ms);

            ICSFM_Logger::error('proxy', 'Upstream fetch failed', [
                'feed_id'       => $feed->id,
                'feed_label'    => $feed_label,
                'apartment'     => $feed->apartment_name,
                'source_url'    => $feed->source_url,
                'error'         => $error_msg,
                'response_ms'   => $elapsed_ms,
                'polled_by_ip'  => $client_ip,
                'polled_by_ua'  => $user_agent,
            ]);

            // Return empty valid ICS so the polling platform doesn't break
            $fallback = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ICS Feed Monitor//EN\r\nEND:VCALENDAR\r\n";
            return $this->make_response($fallback);
        }

        // Success
        $ics_body = wp_remote_retrieve_body($response);

        // Validate it looks like ICS
        if (strpos($ics_body, 'BEGIN:VCALENDAR') === false) {
            ICSFM_Logger::warning('proxy', 'Upstream returned non-ICS content', [
                'feed_id'      => $feed->id,
                'feed_label'   => $feed_label,
                'content_type' => wp_remote_retrieve_header($response, 'content-type'),
                'body_preview' => substr($ics_body, 0, 200),
            ]);

            $this->repo->update_poll((int) $feed->id, [
                'last_polled_at'    => current_time('mysql', true),
                'last_poll_ip'      => $client_ip,
                'last_fetch_status' => 'error',
            ]);
            $this->poll_logger->log((int) $feed->id, 'error', $elapsed_ms);

            $fallback = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ICS Feed Monitor//EN\r\nEND:VCALENDAR\r\n";
            return $this->make_response($fallback);
        }

        $this->repo->update_poll((int) $feed->id, [
            'last_polled_at'    => current_time('mysql', true),
            'last_poll_ip'      => $client_ip,
            'last_fetch_status' => 'ok',
        ]);
        $this->poll_logger->log((int) $feed->id, 'ok', $elapsed_ms);

        ICSFM_Logger::debug('proxy', 'Feed polled successfully', [
            'feed_id'      => $feed->id,
            'feed_label'   => $feed_label,
            'apartment'    => $feed->apartment_name,
            'response_ms'  => $elapsed_ms,
            'ics_bytes'    => strlen($ics_body),
            'polled_by_ip' => $client_ip,
            'polled_by_ua' => $user_agent,
        ]);

        return $this->make_response($ics_body);
    }

    /**
     * Filter to serve raw ICS content instead of JSON.
     */
    public function serve_raw_ics(bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server): bool {
        if (strpos($request->get_route(), '/icsfm/v1/feed/') !== 0) {
            return $served;
        }

        $data = $result->get_data();

        // If it's a WP_Error response, let the default handler deal with it
        if ($result->get_status() >= 400) {
            return $served;
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="calendar.ics"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-ICS-Feed-Monitor: ' . ICSFM_VERSION);

        echo $data; // Raw ICS string
        return true;
    }

    private function make_response(string $body): \WP_REST_Response {
        return new \WP_REST_Response($body, 200);
    }

    private function get_client_ip(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
