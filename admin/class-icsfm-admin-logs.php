<?php
defined('ABSPATH') || exit;

class ICSFM_Admin_Logs {

    public function init(): void {
        add_action('admin_post_icsfm_clear_logs', [$this, 'handle_clear']);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $args = [
            'level'    => isset($_GET['level']) ? sanitize_text_field(wp_unslash($_GET['level'])) : '',
            'source'   => isset($_GET['source']) ? sanitize_text_field(wp_unslash($_GET['source'])) : '',
            'search'   => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
            'per_page' => 50,
            'page'     => isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1,
        ];

        $result = ICSFM_Logger::get_logs($args);

        include ICSFM_PLUGIN_DIR . 'admin/views/logs.php';
    }

    public function handle_clear(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('icsfm_clear_logs');

        ICSFM_Logger::clear_all();

        ICSFM_Logger::info('admin', 'All logs cleared', [
            'user' => wp_get_current_user()->user_login ?? 'system',
        ]);

        wp_redirect(admin_url('admin.php?page=icsfm-logs&message=cleared'));
        exit;
    }
}
