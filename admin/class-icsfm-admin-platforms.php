<?php
defined('ABSPATH') || exit;

class ICSFM_Admin_Platforms {

    private static ICSFM_Feed_Repository $repo;

    public function __construct(ICSFM_Feed_Repository $repo) {
        self::$repo = $repo;
    }

    public function init(): void {
        add_action('admin_post_icsfm_save_platform', [$this, 'handle_save']);
        add_action('admin_post_icsfm_delete_platform', [$this, 'handle_delete']);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($action === 'edit' || $action === 'add') {
            $platform = $id ? self::$repo->get_platform($id) : null;
            include ICSFM_PLUGIN_DIR . 'admin/views/platform-form.php';
        } else {
            $platforms = self::$repo->get_all_platforms();
            include ICSFM_PLUGIN_DIR . 'admin/views/platforms-list.php';
        }
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('icsfm_save_platform');

        $id = isset($_POST['platform_id']) ? (int) $_POST['platform_id'] : 0;
        $data = [
            'name'       => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'sort_order' => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0,
        ];

        if (empty($data['name'])) {
            wp_redirect(admin_url('admin.php?page=icsfm-platforms&error=name_required'));
            exit;
        }

        if ($id > 0) {
            self::$repo->update_platform($id, $data);
            $msg = 'updated';
        } else {
            self::$repo->create_platform($data);
            $msg = 'created';
        }

        wp_redirect(admin_url('admin.php?page=icsfm-platforms&message=' . $msg));
        exit;
    }

    public function handle_delete(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('icsfm_delete_platform_' . $id);

        if ($id > 0) {
            if (self::$repo->platform_in_use($id)) {
                wp_redirect(admin_url('admin.php?page=icsfm-platforms&error=in_use'));
                exit;
            }
            self::$repo->delete_platform($id);
        }

        wp_redirect(admin_url('admin.php?page=icsfm-platforms&message=deleted'));
        exit;
    }
}
