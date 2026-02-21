<?php
defined('ABSPATH') || exit;

class ICSFM_Admin_Settings {

    private static ICSFM_Feed_Repository $repo;

    public function __construct(ICSFM_Feed_Repository $repo = null) {
        if ($repo) {
            self::$repo = $repo;
        }
    }

    public function init(): void {
        add_action('admin_post_icsfm_save_settings', [$this, 'handle_save']);
        add_action('admin_post_icsfm_test_healthcheck_direct', [$this, 'handle_test_healthcheck']);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('icsfm_settings', []);
        $next_cron = wp_next_scheduled('icsfm_check_stale_feeds');
        $grouped = self::$repo->get_feeds_grouped_by_apartment();

        include ICSFM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('icsfm_save_settings');

        $old_settings = get_option('icsfm_settings', []);

        $new_settings = [
            'webhook_url'          => isset($_POST['webhook_url']) ? esc_url_raw(wp_unslash($_POST['webhook_url'])) : '',
            'webhook_method'       => isset($_POST['webhook_method']) && $_POST['webhook_method'] === 'GET' ? 'GET' : 'POST',
            'webhook_secret'       => isset($_POST['webhook_secret']) ? sanitize_text_field(wp_unslash($_POST['webhook_secret'])) : '',
            'alert_email'          => isset($_POST['alert_email']) ? sanitize_email(wp_unslash($_POST['alert_email'])) : '',
            'healthcheck_url'      => isset($_POST['healthcheck_url']) ? esc_url_raw(wp_unslash($_POST['healthcheck_url'])) : '',
            'default_alert_window' => isset($_POST['default_alert_window']) ? max(1, (int) $_POST['default_alert_window']) : 6,
            'alert_cooldown_hours' => isset($_POST['alert_cooldown_hours']) ? max(1, (int) $_POST['alert_cooldown_hours']) : 6,
            'log_retention_days'   => isset($_POST['log_retention_days']) ? max(1, (int) $_POST['log_retention_days']) : 30,
            'db_version'           => $old_settings['db_version'] ?? '1.0',
        ];

        update_option('icsfm_settings', $new_settings);

        // Log changes
        $changes = [];
        foreach ($new_settings as $key => $value) {
            $old = $old_settings[$key] ?? null;
            if ($old !== $value && $key !== 'webhook_secret') {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
            if ($key === 'webhook_secret' && $old !== $value) {
                $changes[$key] = ['changed' => true]; // Don't log actual secret
            }
        }

        if (!empty($changes)) {
            ICSFM_Logger::info('admin', 'Settings updated', [
                'changes' => $changes,
                'user'    => wp_get_current_user()->user_login ?? 'system',
            ]);
        }

        // Save feed source URLs if submitted
        if (!empty($_POST['feed_source_url']) && is_array($_POST['feed_source_url'])) {
            $feed_url_changes = [];
            foreach ($_POST['feed_source_url'] as $feed_id => $url) {
                $feed_id = (int) $feed_id;
                $url = esc_url_raw(wp_unslash($url));
                $feed = self::$repo->get_feed($feed_id);
                if ($feed && $feed->source_url !== $url) {
                    self::$repo->update_feed($feed_id, ['source_url' => $url]);
                    $feed_url_changes[] = [
                        'feed_id' => $feed_id,
                        'label'   => $feed->label,
                        'old_url' => $feed->source_url,
                        'new_url' => $url,
                    ];
                }
            }
            if (!empty($feed_url_changes)) {
                ICSFM_Logger::info('admin', 'Feed source URLs updated via settings', [
                    'changes' => $feed_url_changes,
                    'user'    => wp_get_current_user()->user_login ?? 'system',
                ]);
            }
        }

        wp_redirect(admin_url('admin.php?page=icsfm-settings&message=saved'));
        exit;
    }

    public function handle_test_healthcheck(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('icsfm_test_healthcheck_direct');

        $settings = get_option('icsfm_settings', []);
        $url = $settings['healthcheck_url'] ?? '';

        if (empty($url)) {
            wp_redirect(admin_url('admin.php?page=icsfm-settings&hc_result=' . urlencode('No healthcheck URL configured. Save settings first.')));
            exit;
        }

        ICSFM_Logger::info('admin', 'Testing healthcheck ping (direct)', ['url' => $url]);

        $response = wp_remote_get($url, [
            'timeout'    => 10,
            'user-agent' => 'ICS-Feed-Monitor/' . ICSFM_VERSION,
        ]);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            ICSFM_Logger::error('admin', 'Healthcheck test failed', ['url' => $url, 'error' => $error]);
            wp_redirect(admin_url('admin.php?page=icsfm-settings&hc_result=' . urlencode("Ping failed: {$error}")));
            exit;
        }

        $status = wp_remote_retrieve_response_code($response);
        ICSFM_Logger::info('admin', 'Healthcheck test completed', ['url' => $url, 'status' => $status]);

        if ($status >= 200 && $status < 300) {
            wp_redirect(admin_url('admin.php?page=icsfm-settings&hc_result=' . urlencode("Healthcheck pinged successfully (HTTP {$status}).")));
        } else {
            wp_redirect(admin_url('admin.php?page=icsfm-settings&hc_result=' . urlencode("Healthcheck returned HTTP {$status}.")));
        }
        exit;
    }
}
