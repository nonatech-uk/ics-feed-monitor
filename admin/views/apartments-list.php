<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1>
        Apartments
        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments&action=add')); ?>" class="page-title-action">Add New</a>
    </h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field(wp_unslash($_GET['message']));
                if ($msg === 'created') echo 'Apartment created.';
                elseif ($msg === 'updated') echo 'Apartment updated.';
                elseif ($msg === 'deleted') echo 'Apartment deleted.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                $err = sanitize_text_field(wp_unslash($_GET['error']));
                if ($err === 'name_required') echo 'Apartment name is required.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (empty($apartments)): ?>
        <p>No apartments yet. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments&action=add')); ?>">Add your first apartment</a>.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Feeds</th>
                    <th>Sort Order</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $repo = new ICSFM_Feed_Repository();
                foreach ($apartments as $apartment):
                    $feed_count = count($repo->get_feeds_for_apartment((int) $apartment->id));
                ?>
                <tr>
                    <td><strong><?php echo esc_html($apartment->name); ?></strong></td>
                    <td><?php echo (int) $feed_count; ?></td>
                    <td><?php echo (int) $apartment->sort_order; ?></td>
                    <td><?php echo esc_html(wp_date('M j, Y', strtotime($apartment->created_at))); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments&action=edit&id=' . $apartment->id)); ?>">Edit</a>
                        |
                        <a href="<?php echo esc_url(wp_nonce_url(
                            admin_url('admin-post.php?action=icsfm_delete_apartment&id=' . $apartment->id),
                            'icsfm_delete_apartment_' . $apartment->id
                        )); ?>" class="icsfm-delete-link" onclick="return confirm('Delete this apartment and all its feeds?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
