<?php
defined('ABSPATH') || exit;

class ICSFM_Webhook {

    public function fire_stale_alert(object $feed): bool {
        return $this->fire('feed_stale', [
            'feed' => [
                'id'                 => (int) $feed->id,
                'label'              => $feed->label,
                'platform'           => $feed->platform,
                'apartment'          => $feed->apartment_name ?? '',
                'proxy_url'          => rest_url("icsfm/v1/feed/{$feed->proxy_token}"),
                'alert_window_hours' => (int) $feed->alert_window_hours,
            ],
            'staleness' => [
                'last_polled_at'    => $feed->last_polled_at ?: null,
                'hours_since_poll'  => $feed->last_polled_at
                    ? round((time() - strtotime($feed->last_polled_at)) / 3600, 1)
                    : null,
                'last_fetch_status' => $feed->last_fetch_status,
            ],
        ]);
    }

    public function fire_test(): bool {
        return $this->fire('test', [
            'message' => 'This is a test webhook from ICS Feed Monitor.',
        ]);
    }

    /**
     * Ping the healthcheck URL. Used as a heartbeat at the start and end of
     * each cron run so the healthcheck service knows the cron is alive.
     *
     * Supports /start and /fail suffixes (healthchecks.io convention):
     *   - ping_healthcheck('start') → appends /start to the URL
     *   - ping_healthcheck('fail')  → appends /fail to the URL
     *   - ping_healthcheck()        → pings the bare URL (success/complete)
     */
    public function ping_healthcheck(string $signal = ''): bool {
        $settings = get_option('icsfm_settings', []);
        $url = $settings['healthcheck_url'] ?? '';

        if (empty($url)) {
            return false;
        }

        // Append signal suffix (e.g. /start, /fail)
        if (!empty($signal)) {
            $url = rtrim($url, '/') . '/' . ltrim($signal, '/');
        }

        ICSFM_Logger::debug('cron', "Pinging healthcheck ({$signal})", [
            'url' => $url,
        ]);

        $response = wp_remote_get($url, [
            'timeout'    => 10,
            'user-agent' => 'ICS-Feed-Monitor/' . ICSFM_VERSION,
        ]);

        if (is_wp_error($response)) {
            ICSFM_Logger::warning('cron', 'Healthcheck ping failed', [
                'url'   => $url,
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);

        ICSFM_Logger::debug('cron', "Healthcheck pinged ({$status})", [
            'url'    => $url,
            'status' => $status,
        ]);

        return $status >= 200 && $status < 300;
    }

    public function fire_upstream_error(object $feed, string $error): bool {
        return $this->fire('upstream_error', [
            'feed' => [
                'id'        => (int) $feed->id,
                'label'     => $feed->label,
                'platform'  => $feed->platform,
                'apartment' => $feed->apartment_name ?? '',
            ],
            'error' => $error,
        ]);
    }

    private function fire(string $event, array $data): bool {
        $settings = get_option('icsfm_settings', []);
        $webhook_url = $settings['webhook_url'] ?? '';

        if (empty($webhook_url)) {
            ICSFM_Logger::warning('webhook', 'No webhook URL configured, skipping alert', [
                'event' => $event,
            ]);
            return false;
        }

        $payload = array_merge([
            'event'     => $event,
            'timestamp' => gmdate('c'),
            'site'      => [
                'url'  => home_url(),
                'name' => get_bloginfo('name'),
            ],
        ], $data);

        $body = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type' => 'application/json',
        ];

        // HMAC signature
        $secret = $settings['webhook_secret'] ?? '';
        if (defined('ICSFM_WEBHOOK_SECRET')) {
            $secret = ICSFM_WEBHOOK_SECRET;
        }
        if (!empty($secret)) {
            $signature = hash_hmac('sha256', $body, $secret);
            $headers['X-ICSFM-Signature'] = 'sha256=' . $signature;
        }

        $method = strtoupper($settings['webhook_method'] ?? 'POST');

        ICSFM_Logger::info('webhook', "Firing {$event} webhook", [
            'url'    => $webhook_url,
            'method' => $method,
            'event'  => $event,
        ]);

        if ($method === 'GET') {
            $response = wp_remote_get(add_query_arg(['event' => $event], $webhook_url), [
                'timeout' => 15,
                'headers' => $headers,
            ]);
        } else {
            $response = wp_remote_post($webhook_url, [
                'timeout' => 15,
                'headers' => $headers,
                'body'    => $body,
            ]);
        }

        if (is_wp_error($response)) {
            ICSFM_Logger::error('webhook', 'Webhook dispatch failed', [
                'url'   => $webhook_url,
                'event' => $event,
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        ICSFM_Logger::info('webhook', "Webhook delivered ({$status_code})", [
            'url'           => $webhook_url,
            'event'         => $event,
            'status_code'   => $status_code,
            'response_body' => substr($response_body, 0, 500),
        ]);

        return $status_code >= 200 && $status_code < 300;
    }
}
