<?php
defined('ABSPATH') || exit;

class ICSFM_Admin_Pairs {

    private static ICSFM_Feed_Repository $repo;
    private static ICSFM_Poll_Logger $poll_logger;

    public function __construct(ICSFM_Feed_Repository $repo, ICSFM_Poll_Logger $poll_logger) {
        self::$repo = $repo;
        self::$poll_logger = $poll_logger;
    }

    public function init(): void {
        add_action('admin_post_icsfm_save_pair', [$this, 'handle_save']);
        add_action('admin_post_icsfm_delete_pair', [$this, 'handle_delete']);
        add_action('admin_post_icsfm_regenerate_token', [$this, 'handle_regenerate_token']);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($action === 'edit' || $action === 'add') {
            $pair = $id ? self::$repo->get_pair($id) : null;
            $feeds = $id ? self::$repo->get_feeds_for_pair($id) : [];
            $apartments = self::$repo->get_all_apartments();
            $platforms = self::$repo->get_all_platforms();

            // Organize feeds by direction
            $feed_a_to_b = null;
            $feed_b_to_a = null;
            foreach ($feeds as $f) {
                if ($f->direction === 'a_to_b') $feed_a_to_b = self::$repo->get_feed((int) $f->id);
                if ($f->direction === 'b_to_a') $feed_b_to_a = self::$repo->get_feed((int) $f->id);
            }

            // Get poll history for each feed
            $poll_history_a_to_b = $feed_a_to_b ? self::$poll_logger->get_logs_for_feed((int) $feed_a_to_b->id, 10) : [];
            $poll_history_b_to_a = $feed_b_to_a ? self::$poll_logger->get_logs_for_feed((int) $feed_b_to_a->id, 10) : [];

            include ICSFM_PLUGIN_DIR . 'admin/views/pair-form.php';
        } else {
            $pairs = self::$repo->get_all_pairs();

            // Attach feeds to each pair for status display
            foreach ($pairs as $p) {
                $p->feeds = self::$repo->get_feeds_for_pair((int) $p->id);
            }

            include ICSFM_PLUGIN_DIR . 'admin/views/pairs-list.php';
        }
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('icsfm_save_pair');

        $id = isset($_POST['pair_id']) ? (int) $_POST['pair_id'] : 0;
        $apartment_id = isset($_POST['apartment_id']) ? (int) $_POST['apartment_id'] : 0;
        $platform_a_id = isset($_POST['platform_a_id']) ? (int) $_POST['platform_a_id'] : 0;
        $platform_b_id = isset($_POST['platform_b_id']) ? (int) $_POST['platform_b_id'] : 0;
        $source_url_a_to_b = isset($_POST['source_url_a_to_b']) ? esc_url_raw(wp_unslash($_POST['source_url_a_to_b'])) : '';
        $source_url_b_to_a = isset($_POST['source_url_b_to_a']) ? esc_url_raw(wp_unslash($_POST['source_url_b_to_a'])) : '';

        if (empty($apartment_id) || empty($platform_a_id) || empty($platform_b_id)) {
            wp_redirect(admin_url('admin.php?page=icsfm-pairs&error=required_fields'));
            exit;
        }

        if ($platform_a_id === $platform_b_id) {
            wp_redirect(admin_url('admin.php?page=icsfm-pairs&error=same_platform'));
            exit;
        }

        if ($id > 0) {
            // Edit: update source URLs and active status
            $data = [
                'source_url_a_to_b' => $source_url_a_to_b,
                'source_url_b_to_a' => $source_url_b_to_a,
                'is_active'         => isset($_POST['is_active']) ? 1 : 0,
            ];
            self::$repo->update_pair($id, $data);
            $msg = 'updated';
        } else {
            // Create new pair
            $data = [
                'apartment_id'      => $apartment_id,
                'platform_a_id'     => $platform_a_id,
                'platform_b_id'     => $platform_b_id,
                'source_url_a_to_b' => $source_url_a_to_b,
                'source_url_b_to_a' => $source_url_b_to_a,
            ];
            $id = self::$repo->create_pair($data);
            $msg = 'created';
        }

        wp_redirect(admin_url('admin.php?page=icsfm-pairs&action=edit&id=' . $id . '&message=' . $msg));
        exit;
    }

    public function handle_delete(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('icsfm_delete_pair_' . $id);

        if ($id > 0) {
            self::$repo->delete_pair($id);
        }

        wp_redirect(admin_url('admin.php?page=icsfm-pairs&message=deleted'));
        exit;
    }

    public function handle_regenerate_token(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $pair_id = isset($_GET['pair_id']) ? (int) $_GET['pair_id'] : 0;
        check_admin_referer('icsfm_regenerate_token_' . $id);

        if ($id > 0) {
            self::$repo->regenerate_token($id);
        }

        wp_redirect(admin_url('admin.php?page=icsfm-pairs&action=edit&id=' . $pair_id . '&message=token_regenerated'));
        exit;
    }
}
