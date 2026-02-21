<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1>
        Platforms
        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-platforms&action=add')); ?>" class="page-title-action">Add New</a>
    </h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field(wp_unslash($_GET['message']));
                if ($msg === 'created') echo 'Platform created.';
                elseif ($msg === 'updated') echo 'Platform updated.';
                elseif ($msg === 'deleted') echo 'Platform deleted.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                $err = sanitize_text_field(wp_unslash($_GET['error']));
                if ($err === 'name_required') echo 'Platform name is required.';
                elseif ($err === 'in_use') echo 'Cannot delete this platform — it is used by one or more feed pairs.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (empty($platforms)): ?>
        <p>No platforms yet. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-platforms&action=add')); ?>">Add your first platform</a> (e.g. Airbnb, VRBO).</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Feed Pairs</th>
                    <th>Sort Order</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $repo = new ICSFM_Feed_Repository();
                foreach ($platforms as $platform):
                    $in_use = $repo->platform_in_use((int) $platform->id);
                ?>
                <tr>
                    <td><strong><?php echo esc_html($platform->name); ?></strong></td>
                    <td><?php echo $in_use ? 'Yes' : '—'; ?></td>
                    <td><?php echo (int) $platform->sort_order; ?></td>
                    <td><?php echo esc_html(wp_date('M j, Y', strtotime($platform->created_at))); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-platforms&action=edit&id=' . $platform->id)); ?>">Edit</a>
                        <?php if (!$in_use): ?>
                            |
                            <a href="<?php echo esc_url(wp_nonce_url(
                                admin_url('admin-post.php?action=icsfm_delete_platform&id=' . $platform->id),
                                'icsfm_delete_platform_' . $platform->id
                            )); ?>" class="icsfm-delete-link" onclick="return confirm('Delete this platform?');">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
