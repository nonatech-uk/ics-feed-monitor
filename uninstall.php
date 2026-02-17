<?php
/**
 * Fired when the plugin is uninstalled (deleted via WP admin).
 * Cleans up all database tables and options.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;
$prefix = $wpdb->prefix;

// Drop tables in correct order (child tables first)
$wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_poll_log");
$wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_log");
$wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_feeds");
$wpdb->query("DROP TABLE IF EXISTS {$prefix}icsfm_apartments");

// Remove options
delete_option('icsfm_settings');

// Clear cron hooks
wp_clear_scheduled_hook('icsfm_check_stale_feeds');

// Clear any transients
$wpdb->query("DELETE FROM {$prefix}options WHERE option_name LIKE '_transient_icsfm_%'");
$wpdb->query("DELETE FROM {$prefix}options WHERE option_name LIKE '_transient_timeout_icsfm_%'");
