<?php
defined('ABSPATH') || exit;

class ICSFM_Activator {

    public static function activate(): void {
        self::create_tables();
        self::seed_settings();
        self::schedule_cron();

        ICSFM_Logger::info('system', 'Plugin activated', [
            'version' => ICSFM_VERSION,
        ]);
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Fresh start: drop all existing tables
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_poll_log");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_log");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_feeds");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_feed_pairs");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_platforms");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_apartments");

        $sql_apartments = "CREATE TABLE {$prefix}icsfm_apartments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        $sql_platforms = "CREATE TABLE {$prefix}icsfm_platforms (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        $sql_feed_pairs = "CREATE TABLE {$prefix}icsfm_feed_pairs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            apartment_id bigint(20) unsigned NOT NULL,
            platform_a_id bigint(20) unsigned NOT NULL,
            platform_b_id bigint(20) unsigned NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY apartment_platforms (apartment_id, platform_a_id, platform_b_id),
            KEY apartment_id (apartment_id),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql_feeds = "CREATE TABLE {$prefix}icsfm_feeds (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pair_id bigint(20) unsigned NOT NULL,
            direction varchar(10) NOT NULL,
            source_url text NOT NULL,
            proxy_token varchar(64) NOT NULL,
            last_polled_at datetime DEFAULT NULL,
            last_poll_ip varchar(45) DEFAULT NULL,
            last_fetch_status varchar(20) DEFAULT 'never',
            last_alert_sent_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY proxy_token (proxy_token),
            KEY pair_id (pair_id)
        ) $charset_collate;";

        $sql_poll_log = "CREATE TABLE {$prefix}icsfm_poll_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            feed_id bigint(20) unsigned NOT NULL,
            polled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            remote_ip varchar(45) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            upstream_status varchar(20) NOT NULL DEFAULT 'ok',
            response_time_ms int(11) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY feed_id (feed_id),
            KEY polled_at (polled_at)
        ) $charset_collate;";

        $sql_log = "CREATE TABLE {$prefix}icsfm_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            source varchar(50) NOT NULL DEFAULT 'system',
            message text NOT NULL,
            context text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY source (source),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_apartments);
        dbDelta($sql_platforms);
        dbDelta($sql_feed_pairs);
        dbDelta($sql_feeds);
        dbDelta($sql_poll_log);
        dbDelta($sql_log);
    }

    private static function seed_settings(): void {
        $existing = get_option('icsfm_settings');

        if ($existing && ($existing['db_version'] ?? '') === '2.0') {
            return;
        }

        $defaults = [
            'webhook_url'          => '',
            'webhook_method'       => 'POST',
            'webhook_secret'       => bin2hex(random_bytes(16)),
            'alert_email'          => $existing['alert_email'] ?? '',
            'healthcheck_url'      => $existing['healthcheck_url'] ?? '',
            'alert_window_hours'   => (int) ($existing['default_alert_window'] ?? 6),
            'alert_cooldown_hours' => (int) ($existing['alert_cooldown_hours'] ?? 6),
            'log_retention_days'   => (int) ($existing['log_retention_days'] ?? 30),
            'db_version'           => '2.0',
        ];

        if ($existing && !empty($existing['webhook_url'])) {
            $defaults['webhook_url'] = $existing['webhook_url'];
            $defaults['webhook_method'] = $existing['webhook_method'] ?? 'POST';
            $defaults['webhook_secret'] = $existing['webhook_secret'] ?? $defaults['webhook_secret'];
        }

        update_option('icsfm_settings', $defaults);
    }

    private static function schedule_cron(): void {
        if (!wp_next_scheduled('icsfm_check_stale_feeds')) {
            wp_schedule_event(time(), 'every_five_minutes', 'icsfm_check_stale_feeds');
        }
    }
}
