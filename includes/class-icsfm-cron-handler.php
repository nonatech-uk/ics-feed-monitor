<?php
defined('ABSPATH') || exit;

class ICSFM_Cron_Handler {

    private ICSFM_Feed_Repository $repo;
    private ICSFM_Poll_Logger $poll_logger;
    private ICSFM_Webhook $webhook;

    public function __construct(ICSFM_Feed_Repository $repo, ICSFM_Poll_Logger $poll_logger, ICSFM_Webhook $webhook) {
        $this->repo = $repo;
        $this->poll_logger = $poll_logger;
        $this->webhook = $webhook;
    }

    public function check_stale_feeds(): void {
        $settings = get_option('icsfm_settings', []);
        $cooldown_hours = (int) ($settings['alert_cooldown_hours'] ?? 6);
        $log_retention_days = (int) ($settings['log_retention_days'] ?? 30);

        // Ping healthcheck: job starting
        $this->webhook->ping_healthcheck('start');

        ICSFM_Logger::info('cron', 'Staleness check started');

        // Find feeds that are stale and haven't been alerted recently
        $stale_feeds = $this->repo->get_stale_feeds($cooldown_hours);

        // Filter out newly created feeds that haven't had time to be polled yet
        $now = time();
        $actionable = [];

        foreach ($stale_feeds as $feed) {
            // Grace period: don't alert for feeds created less than alert_window ago
            // that have never been polled
            if ($feed->last_polled_at === null) {
                $created_ts = strtotime($feed->created_at);
                if (($now - $created_ts) < ($feed->alert_window_hours * 3600)) {
                    continue;
                }
            }
            $actionable[] = $feed;
        }

        $alerted = 0;
        $stale_labels = [];

        foreach ($actionable as $feed) {
            $success = $this->webhook->fire_stale_alert($feed);

            if ($success) {
                $this->repo->update_feed((int) $feed->id, [
                    'last_alert_sent_at' => current_time('mysql', true),
                ]);
                $alerted++;
            }

            $stale_labels[] = sprintf(
                '%s / %s (%s)',
                $feed->apartment_name ?? 'Unknown',
                $feed->label,
                $feed->platform
            );
        }

        $total_active = $this->repo->count_active_feeds();

        ICSFM_Logger::info('cron', 'Staleness check completed', [
            'total_active_feeds' => $total_active,
            'stale_feeds_found'  => count($actionable),
            'alerts_sent'        => $alerted,
            'stale_feed_labels'  => $stale_labels,
        ]);

        // Prune old logs
        $pruned_polls = $this->poll_logger->prune($log_retention_days);
        $pruned_logs = ICSFM_Logger::prune($log_retention_days);

        if ($pruned_polls > 0 || $pruned_logs > 0) {
            ICSFM_Logger::info('cron', 'Old log entries pruned', [
                'poll_log_entries_removed' => $pruned_polls,
                'system_log_entries_removed' => $pruned_logs,
                'retention_days' => $log_retention_days,
            ]);
        }

        // Ping healthcheck: job complete (or /fail if there were stale feeds)
        if (count($actionable) > 0) {
            $this->webhook->ping_healthcheck('fail');
        } else {
            $this->webhook->ping_healthcheck();
        }
    }
}
