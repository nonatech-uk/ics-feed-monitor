<?php
defined('ABSPATH') || exit;

class ICSFM_Admin_Apartments {

    private static ICSFM_Feed_Repository $repo;

    public function __construct(ICSFM_Feed_Repository $repo) {
        self::$repo = $repo;
    }

    public function init(): void {
        add_action('admin_post_icsfm_save_apartment', [$this, 'handle_save']);
        add_action('admin_post_icsfm_delete_apartment', [$this, 'handle_delete']);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($action === 'edit' || $action === 'add') {
            $apartment = $id ? self::$repo->get_apartment($id) : null;
            include ICSFM_PLUGIN_DIR . 'admin/views/apartment-form.php';
        } else {
            $apartments = self::$repo->get_all_apartments();
            include ICSFM_PLUGIN_DIR . 'admin/views/apartments-list.php';
        }
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('icsfm_save_apartment');

        $id = isset($_POST['apartment_id']) ? (int) $_POST['apartment_id'] : 0;
        $data = [
            'name'       => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'sort_order' => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0,
        ];

        if (empty($data['name'])) {
            wp_redirect(admin_url('admin.php?page=icsfm-apartments&error=name_required'));
            exit;
        }

        if ($id > 0) {
            self::$repo->update_apartment($id, $data);
            $msg = 'updated';
        } else {
            self::$repo->create_apartment($data);
            $msg = 'created';
        }

        wp_redirect(admin_url('admin.php?page=icsfm-apartments&message=' . $msg));
        exit;
    }

    public function handle_delete(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('icsfm_delete_apartment_' . $id);

        if ($id > 0) {
            self::$repo->delete_apartment($id);
        }

        wp_redirect(admin_url('admin.php?page=icsfm-apartments&message=deleted'));
        exit;
    }
}
