<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1>
        Feeds
        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds&action=add')); ?>" class="page-title-action">Add New</a>
    </h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field(wp_unslash($_GET['message']));
                if ($msg === 'created') echo 'Feed created.';
                elseif ($msg === 'updated') echo 'Feed updated.';
                elseif ($msg === 'deleted') echo 'Feed deleted.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                $err = sanitize_text_field(wp_unslash($_GET['error']));
                if ($err === 'required_fields') echo 'Please fill in all required fields (apartment, label, source URL).';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (empty($feeds)): ?>
        <p>No feeds configured yet.
            <?php if (empty($apartments)): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments&action=add')); ?>">Add an apartment first</a>.
            <?php else: ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds&action=add')); ?>">Add your first feed</a>.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Apartment</th>
                    <th>Label</th>
                    <th>Platform</th>
                    <th>Proxy URL</th>
                    <th>Alert Window</th>
                    <th>Status</th>
                    <th>Last Polled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feeds as $feed):
                    $status = ICSFM_Admin_Dashboard::get_feed_status($feed);
                    $proxy_url = rest_url("icsfm/v1/feed/{$feed->proxy_token}");
                ?>
                <tr>
                    <td><?php echo esc_html($feed->apartment_name ?? '—'); ?></td>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds&action=edit&id=' . $feed->id)); ?>">
                                <?php echo esc_html($feed->label); ?>
                            </a>
                        </strong>
                        <?php if (!$feed->is_active): ?>
                            <span class="icsfm-badge icsfm-badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="icsfm-platform-badge icsfm-platform-<?php echo esc_attr($feed->platform); ?>">
                            <?php echo esc_html(ucfirst($feed->platform)); ?>
                        </span>
                    </td>
                    <td class="icsfm-proxy-url-cell">
                        <code class="icsfm-proxy-url" title="<?php echo esc_attr($proxy_url); ?>">
                            <?php echo esc_html(substr($proxy_url, 0, 50) . '...'); ?>
                        </code>
                        <button type="button" class="button button-small icsfm-copy-btn" data-url="<?php echo esc_attr($proxy_url); ?>">Copy</button>
                    </td>
                    <td><?php echo (int) $feed->alert_window_hours; ?>h</td>
                    <td>
                        <span class="icsfm-dot" style="background-color: <?php echo esc_attr($status['color']); ?>" title="<?php echo esc_attr($status['label']); ?>"></span>
                        <?php if ($feed->last_fetch_status === 'error'): ?>
                            <span class="icsfm-badge icsfm-badge-error">Upstream Error</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($feed->last_polled_at): ?>
                            <?php echo esc_html(ICSFM_Admin_Dashboard::time_ago($feed->last_polled_at)); ?>
                        <?php else: ?>
                            <span class="icsfm-muted">Never</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds&action=edit&id=' . $feed->id)); ?>">Edit</a>
                        |
                        <a href="<?php echo esc_url(wp_nonce_url(
                            admin_url('admin-post.php?action=icsfm_delete_feed&id=' . $feed->id),
                            'icsfm_delete_feed_' . $feed->id
                        )); ?>" class="icsfm-delete-link" onclick="return confirm('Delete this feed?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
