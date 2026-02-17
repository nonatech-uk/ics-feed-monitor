<?php
defined('ABSPATH') || exit;

class ICSFM_Admin_Feeds {

    private static ICSFM_Feed_Repository $repo;
    private static ICSFM_Poll_Logger $poll_logger;

    public function __construct(ICSFM_Feed_Repository $repo, ICSFM_Poll_Logger $poll_logger) {
        self::$repo = $repo;
        self::$poll_logger = $poll_logger;
    }

    public function init(): void {
        add_action('admin_post_icsfm_save_feed', [$this, 'handle_save']);
        add_action('admin_post_icsfm_delete_feed', [$this, 'handle_delete']);
        add_action('admin_post_icsfm_regenerate_token', [$this, 'handle_regenerate_token']);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($action === 'edit' || $action === 'add') {
            $feed = $id ? self::$repo->get_feed($id) : null;
            $apartments = self::$repo->get_all_apartments();
            $settings = get_option('icsfm_settings', []);

            // If viewing a specific feed, get its poll history
            $poll_history = $id ? self::$poll_logger->get_logs_for_feed($id, 20) : [];

            include ICSFM_PLUGIN_DIR . 'admin/views/feed-form.php';
        } else {
            $feeds = self::$repo->get_all_feeds();
            $apartments = self::$repo->get_all_apartments();
            include ICSFM_PLUGIN_DIR . 'admin/views/feeds-list.php';
        }
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('icsfm_save_feed');

        $id = isset($_POST['feed_id']) ? (int) $_POST['feed_id'] : 0;
        $apartment_id = isset($_POST['apartment_id']) ? (int) $_POST['apartment_id'] : 0;
        $platform = isset($_POST['platform']) ? sanitize_text_field(wp_unslash($_POST['platform'])) : 'other';

        // Auto-generate label from apartment name + platform
        $platform_labels = self::get_platform_options();
        $apartment = $apartment_id ? self::$repo->get_apartment($apartment_id) : null;
        $label = ($apartment ? $apartment->name : 'Unknown') . ' — ' . ($platform_labels[$platform] ?? ucfirst($platform));

        $data = [
            'apartment_id'       => $apartment_id,
            'label'              => $label,
            'platform'           => $platform,
            'source_url'         => isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '',
            'alert_window_hours' => isset($_POST['alert_window_hours']) ? (int) $_POST['alert_window_hours'] : 6,
            'is_active'          => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (empty($data['source_url']) || empty($data['apartment_id'])) {
            wp_redirect(admin_url('admin.php?page=icsfm-feeds&error=required_fields'));
            exit;
        }

        if ($id > 0) {
            self::$repo->update_feed($id, $data);
            $msg = 'updated';

            ICSFM_Logger::info('admin', 'Feed updated', [
                'feed_id'  => $id,
                'label'    => $data['label'],
                'platform' => $data['platform'],
                'user'     => wp_get_current_user()->user_login ?? 'system',
            ]);
        } else {
            $id = self::$repo->create_feed($data);
            $msg = 'created';
        }

        wp_redirect(admin_url('admin.php?page=icsfm-feeds&action=edit&id=' . $id . '&message=' . $msg));
        exit;
    }

    public function handle_delete(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('icsfm_delete_feed_' . $id);

        if ($id > 0) {
            self::$repo->delete_feed($id);
        }

        wp_redirect(admin_url('admin.php?page=icsfm-feeds&message=deleted'));
        exit;
    }

    public function handle_regenerate_token(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('icsfm_regenerate_token_' . $id);

        if ($id > 0) {
            self::$repo->regenerate_token($id);
        }

        wp_redirect(admin_url('admin.php?page=icsfm-feeds&action=edit&id=' . $id . '&message=token_regenerated'));
        exit;
    }

    public static function get_platform_options(): array {
        return [
            'booking' => 'Booking Platform',
            'airbnb'  => 'Airbnb',
            'vrbo'    => 'VRBO',
            'other'   => 'Other',
        ];
    }
}
