<?php
defined('ABSPATH') || exit;

class ICSFM_Admin_Dashboard {

    private static ICSFM_Feed_Repository $repo;
    private static ICSFM_Poll_Logger $poll_logger;

    public function __construct(ICSFM_Feed_Repository $repo, ICSFM_Poll_Logger $poll_logger) {
        self::$repo = $repo;
        self::$poll_logger = $poll_logger;
    }

    public function init(): void {
        // Nothing extra needed
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $grouped = self::$repo->get_pairs_grouped_by_apartment();
        $settings = get_option('icsfm_settings', []);
        $alert_window_hours = (int) ($settings['alert_window_hours'] ?? 6);
        $recent_logs = ICSFM_Logger::get_recent(10);
        $next_cron = wp_next_scheduled('icsfm_check_stale_feeds');

        include ICSFM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Get feed status. Accepts optional pair_is_active and alert_window_hours
     * to support the new pair-based model.
     */
    public static function get_feed_status(object $feed, ?bool $pair_is_active = null, ?int $alert_window_hours = null): array {
        // Determine active state
        $is_active = $pair_is_active ?? ($feed->pair_is_active ?? true);
        if (!$is_active) {
            return ['class' => 'inactive', 'label' => 'Inactive', 'color' => '#999'];
        }

        if ($feed->last_polled_at === null) {
            return ['class' => 'never', 'label' => 'Never polled', 'color' => '#999'];
        }

        $now = time();
        $last_polled = strtotime($feed->last_polled_at);
        $elapsed_hours = ($now - $last_polled) / 3600;

        // Use provided threshold, fall back to global setting
        if ($alert_window_hours === null) {
            $settings = get_option('icsfm_settings', []);
            $alert_window_hours = (int) ($settings['alert_window_hours'] ?? 6);
        }
        $threshold = $alert_window_hours;

        if ($elapsed_hours <= $threshold * 0.5) {
            return ['class' => 'healthy', 'label' => 'Healthy', 'color' => '#46b450'];
        }

        if ($elapsed_hours <= $threshold) {
            return ['class' => 'warning', 'label' => 'Approaching threshold', 'color' => '#ffb900'];
        }

        return ['class' => 'stale', 'label' => 'Stale — alert sent', 'color' => '#dc3232'];
    }

    public static function time_ago(string $datetime_utc): string {
        $timestamp = strtotime($datetime_utc);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $mins = (int) ($diff / 60);
            return $mins . 'm ago';
        }
        if ($diff < 86400) {
            $hours = (int) ($diff / 3600);
            $mins = (int) (($diff % 3600) / 60);
            return $hours . 'h ' . $mins . 'm ago';
        }

        $days = (int) ($diff / 86400);
        return $days . 'd ago';
    }
}
