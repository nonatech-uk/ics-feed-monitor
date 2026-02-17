<?php
defined('ABSPATH') || exit;

class ICSFM_Poll_Logger {

    private function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'icsfm_poll_log';
    }

    public function log(int $feed_id, string $status, int $response_time_ms = 0): void {
        global $wpdb;

        $wpdb->insert($this->table_name(), [
            'feed_id'          => $feed_id,
            'polled_at'        => current_time('mysql', true),
            'remote_ip'        => $this->get_client_ip(),
            'user_agent'       => isset($_SERVER['HTTP_USER_AGENT'])
                ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500)
                : null,
            'upstream_status'  => $status,
            'response_time_ms' => $response_time_ms,
        ], ['%d', '%s', '%s', '%s', '%s', '%d']);
    }

    public function get_logs_for_feed(int $feed_id, int $limit = 50): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name()} WHERE feed_id = %d ORDER BY polled_at DESC LIMIT %d",
                $feed_id,
                $limit
            )
        ) ?: [];
    }

    public function get_poll_stats(int $feed_id, int $hours = 24): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) AS total_polls,
                    SUM(CASE WHEN upstream_status = 'ok' THEN 1 ELSE 0 END) AS successful_polls,
                    SUM(CASE WHEN upstream_status = 'error' THEN 1 ELSE 0 END) AS failed_polls,
                    AVG(response_time_ms) AS avg_response_ms,
                    MAX(polled_at) AS last_poll,
                    MIN(polled_at) AS first_poll
                 FROM {$this->table_name()}
                 WHERE feed_id = %d
                   AND polled_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)",
                $feed_id,
                $hours
            )
        );
    }

    public function prune(int $days = 30): int {
        global $wpdb;
        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name()} WHERE polled_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    private function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // X-Forwarded-For can contain multiple IPs
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
