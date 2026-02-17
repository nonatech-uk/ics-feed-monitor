<?php
/**
 * Plugin Name: ICS Feed Monitor
 * Plugin URI:  https://github.com/nonatech-uk/ICS-Sync-Checker
 * Description: Proxies ICS calendar feeds and monitors polling activity. Alerts via webhook when platforms stop syncing.
 * Version:     1.1.0
 * Author:      Nonatech UK
 * Author URI:  https://nonatech.co.uk
 * Text Domain: ics-feed-monitor
 * Requires PHP: 7.4
 * GitHub Plugin URI: nonatech-uk/ICS-Sync-Checker
 */

defined('ABSPATH') || exit;

define('ICSFM_VERSION', '1.1.0');
define('ICSFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ICSFM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ICSFM_PLUGIN_FILE', __FILE__);
define('ICSFM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ICSFM_GITHUB_REPO', 'nonatech-uk/ICS-Sync-Checker');

// Includes
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-logger.php';
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-activator.php';
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-deactivator.php';
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-feed-repository.php';
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-poll-logger.php';
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-proxy-handler.php';
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-cron-handler.php';
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-webhook.php';
require_once ICSFM_PLUGIN_DIR . 'includes/class-icsfm-github-updater.php';

// Activation / Deactivation
register_activation_hook(__FILE__, ['ICSFM_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['ICSFM_Deactivator', 'deactivate']);

// Initialize
add_action('plugins_loaded', function () {
    // REST API proxy endpoints
    $repo = new ICSFM_Feed_Repository();
    $poll_logger = new ICSFM_Poll_Logger();

    $proxy = new ICSFM_Proxy_Handler($repo, $poll_logger);
    add_action('rest_api_init', [$proxy, 'register_routes']);

    // Raw ICS output filter
    add_filter('rest_pre_serve_request', [$proxy, 'serve_raw_ics'], 10, 4);

    // Cron
    $cron = new ICSFM_Cron_Handler($repo, $poll_logger, new ICSFM_Webhook());
    add_action('icsfm_check_stale_feeds', [$cron, 'check_stale_feeds']);

    // GitHub updater
    new ICSFM_GitHub_Updater();

    // Admin
    if (is_admin()) {
        require_once ICSFM_PLUGIN_DIR . 'admin/class-icsfm-admin.php';
        require_once ICSFM_PLUGIN_DIR . 'admin/class-icsfm-admin-dashboard.php';
        require_once ICSFM_PLUGIN_DIR . 'admin/class-icsfm-admin-apartments.php';
        require_once ICSFM_PLUGIN_DIR . 'admin/class-icsfm-admin-feeds.php';
        require_once ICSFM_PLUGIN_DIR . 'admin/class-icsfm-admin-settings.php';
        require_once ICSFM_PLUGIN_DIR . 'admin/class-icsfm-admin-logs.php';
        require_once ICSFM_PLUGIN_DIR . 'admin/class-icsfm-admin-ajax.php';

        $admin = new ICSFM_Admin($repo, $poll_logger);
        $admin->init();
    }
});
