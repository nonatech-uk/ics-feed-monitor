<?php defined('ABSPATH') || exit;
$settings = get_option('icsfm_settings', []);
$alert_window_hours = (int) ($settings['alert_window_hours'] ?? 6);
?>
<div class="wrap icsfm-wrap">
    <h1>
        Feed Pairs
        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-pairs&action=add')); ?>" class="page-title-action">Add New</a>
    </h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field(wp_unslash($_GET['message']));
                if ($msg === 'created') echo 'Feed pair created.';
                elseif ($msg === 'updated') echo 'Feed pair updated.';
                elseif ($msg === 'deleted') echo 'Feed pair deleted.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                $err = sanitize_text_field(wp_unslash($_GET['error']));
                if ($err === 'required_fields') echo 'Please select an apartment and both platforms.';
                elseif ($err === 'same_platform') echo 'Platform A and Platform B must be different.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (empty($pairs)): ?>
        <p>No feed pairs configured yet.
            <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-pairs&action=add')); ?>">Create your first feed pair</a>.
        </p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Apartment</th>
                    <th>Platforms</th>
                    <th>Status</th>
                    <th>A → B</th>
                    <th>B → A</th>
                    <th>Last Polled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pairs as $pair):
                    $feed_a = null;
                    $feed_b = null;
                    foreach ($pair->feeds as $f) {
                        if ($f->direction === 'a_to_b') $feed_a = $f;
                        if ($f->direction === 'b_to_a') $feed_b = $f;
                    }

                    $status_a = $feed_a ? ICSFM_Admin_Dashboard::get_feed_status($feed_a, $pair->is_active, $alert_window_hours) : null;
                    $status_b = $feed_b ? ICSFM_Admin_Dashboard::get_feed_status($feed_b, $pair->is_active, $alert_window_hours) : null;

                    // Worst status for overall
                    $worst_class = 'healthy';
                    if (!$pair->is_active) {
                        $worst_class = 'inactive';
                    } else {
                        foreach ([$status_a, $status_b] as $s) {
                            if ($s && $s['class'] === 'stale') { $worst_class = 'stale'; break; }
                            if ($s && $s['class'] === 'warning') { $worst_class = 'warning'; }
                            if ($s && $s['class'] === 'never' && $worst_class !== 'warning') { $worst_class = 'never'; }
                        }
                    }

                    // Most recent poll
                    $last_polled = null;
                    foreach ([$feed_a, $feed_b] as $f) {
                        if ($f && $f->last_polled_at) {
                            if (!$last_polled || strtotime($f->last_polled_at) > strtotime($last_polled)) {
                                $last_polled = $f->last_polled_at;
                            }
                        }
                    }
                ?>
                <tr>
                    <td><?php echo esc_html($pair->apartment_name ?? '—'); ?></td>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-pairs&action=edit&id=' . $pair->id)); ?>">
                                <?php echo esc_html($pair->platform_a_name); ?> &#8596; <?php echo esc_html($pair->platform_b_name); ?>
                            </a>
                        </strong>
                        <?php if (!$pair->is_active): ?>
                            <span class="icsfm-badge icsfm-badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($worst_class === 'inactive'): ?>
                            <span class="icsfm-dot" style="background-color: #999" title="Inactive"></span>
                        <?php elseif ($worst_class === 'stale'): ?>
                            <span class="icsfm-dot" style="background-color: #dc3232" title="Stale"></span>
                        <?php elseif ($worst_class === 'warning'): ?>
                            <span class="icsfm-dot" style="background-color: #ffb900" title="Warning"></span>
                        <?php elseif ($worst_class === 'never'): ?>
                            <span class="icsfm-dot" style="background-color: #999" title="Never polled"></span>
                        <?php else: ?>
                            <span class="icsfm-dot" style="background-color: #46b450" title="Healthy"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($status_a): ?>
                            <span class="icsfm-dot" style="background-color: <?php echo esc_attr($status_a['color']); ?>" title="<?php echo esc_attr($status_a['label']); ?>"></span>
                            <?php if ($feed_a && $feed_a->last_fetch_status === 'error'): ?>
                                <span class="icsfm-badge icsfm-badge-error" style="font-size:10px;">Err</span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($status_b): ?>
                            <span class="icsfm-dot" style="background-color: <?php echo esc_attr($status_b['color']); ?>" title="<?php echo esc_attr($status_b['label']); ?>"></span>
                            <?php if ($feed_b && $feed_b->last_fetch_status === 'error'): ?>
                                <span class="icsfm-badge icsfm-badge-error" style="font-size:10px;">Err</span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($last_polled): ?>
                            <?php echo esc_html(ICSFM_Admin_Dashboard::time_ago($last_polled)); ?>
                        <?php else: ?>
                            <span class="icsfm-muted">Never</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-pairs&action=edit&id=' . $pair->id)); ?>">Edit</a>
                        |
                        <a href="<?php echo esc_url(wp_nonce_url(
                            admin_url('admin-post.php?action=icsfm_delete_pair&id=' . $pair->id),
                            'icsfm_delete_pair_' . $pair->id
                        )); ?>" class="icsfm-delete-link" onclick="return confirm('Delete this feed pair and both proxy feeds?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
