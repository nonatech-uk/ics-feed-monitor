<?php
defined('ABSPATH') || exit;

class ICSFM_Admin {

    private ICSFM_Feed_Repository $repo;
    private ICSFM_Poll_Logger $poll_logger;

    public function __construct(ICSFM_Feed_Repository $repo, ICSFM_Poll_Logger $poll_logger) {
        $this->repo = $repo;
        $this->poll_logger = $poll_logger;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'admin_notices']);

        // Initialize sub-pages
        $dashboard = new ICSFM_Admin_Dashboard($this->repo, $this->poll_logger);
        $apartments = new ICSFM_Admin_Apartments($this->repo);
        $platforms = new ICSFM_Admin_Platforms($this->repo);
        $pairs = new ICSFM_Admin_Pairs($this->repo, $this->poll_logger);
        $settings = new ICSFM_Admin_Settings($this->repo);
        $logs = new ICSFM_Admin_Logs();
        $ajax = new ICSFM_Admin_Ajax($this->repo, $this->poll_logger);

        $dashboard->init();
        $apartments->init();
        $platforms->init();
        $pairs->init();
        $settings->init();
        $logs->init();
        $ajax->init();
    }

    public function register_menus(): void {
        add_menu_page(
            __('ICS Feed Monitor', 'ics-feed-monitor'),
            __('ICS Monitor', 'ics-feed-monitor'),
            'manage_options',
            'icsfm-dashboard',
            [ICSFM_Admin_Dashboard::class, 'render_page'],
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'icsfm-dashboard',
            __('Dashboard', 'ics-feed-monitor'),
            __('Dashboard', 'ics-feed-monitor'),
            'manage_options',
            'icsfm-dashboard',
            [ICSFM_Admin_Dashboard::class, 'render_page']
        );

        add_submenu_page(
            'icsfm-dashboard',
            __('Apartments', 'ics-feed-monitor'),
            __('Apartments', 'ics-feed-monitor'),
            'manage_options',
            'icsfm-apartments',
            [ICSFM_Admin_Apartments::class, 'render_page']
        );

        add_submenu_page(
            'icsfm-dashboard',
            __('Platforms', 'ics-feed-monitor'),
            __('Platforms', 'ics-feed-monitor'),
            'manage_options',
            'icsfm-platforms',
            [ICSFM_Admin_Platforms::class, 'render_page']
        );

        add_submenu_page(
            'icsfm-dashboard',
            __('Feed Pairs', 'ics-feed-monitor'),
            __('Feed Pairs', 'ics-feed-monitor'),
            'manage_options',
            'icsfm-pairs',
            [ICSFM_Admin_Pairs::class, 'render_page']
        );

        add_submenu_page(
            'icsfm-dashboard',
            __('Logs', 'ics-feed-monitor'),
            __('Logs', 'ics-feed-monitor'),
            'manage_options',
            'icsfm-logs',
            [ICSFM_Admin_Logs::class, 'render_page']
        );

        add_submenu_page(
            'icsfm-dashboard',
            __('Settings', 'ics-feed-monitor'),
            __('Settings', 'ics-feed-monitor'),
            'manage_options',
            'icsfm-settings',
            [ICSFM_Admin_Settings::class, 'render_page']
        );
    }

    public function enqueue_assets(string $hook): void {
        // Only load on our pages
        if (strpos($hook, 'icsfm') === false) {
            return;
        }

        wp_enqueue_style(
            'icsfm-admin',
            ICSFM_PLUGIN_URL . 'admin/css/icsfm-admin.css',
            [],
            ICSFM_VERSION
        );

        wp_enqueue_script(
            'icsfm-admin',
            ICSFM_PLUGIN_URL . 'admin/js/icsfm-admin.js',
            ['jquery'],
            ICSFM_VERSION,
            true
        );

        wp_localize_script('icsfm-admin', 'icsfm', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('icsfm/v1/'),
            'nonce'    => wp_create_nonce('icsfm_admin_nonce'),
        ]);
    }

    public function admin_notices(): void {
        // Only show on our pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'icsfm') === false) {
            return;
        }
    }
}
