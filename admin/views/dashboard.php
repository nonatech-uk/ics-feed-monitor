<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1>ICS Feed Monitor — Dashboard</h1>

    <?php if (empty($grouped)): ?>
        <div class="notice notice-info">
            <p>No apartments configured yet. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments&action=add')); ?>">Add your first apartment</a>.</p>
        </div>
    <?php else: ?>
        <div id="icsfm-dashboard-cards" class="icsfm-cards">
            <?php foreach ($grouped as $apartment): ?>
                <div class="icsfm-card">
                    <h2 class="icsfm-card-title"><?php echo esc_html($apartment->name); ?></h2>

                    <?php if (empty($apartment->pairs)): ?>
                        <p class="icsfm-muted">No feed pairs configured. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-pairs&action=add')); ?>">Add a feed pair</a>.</p>
                    <?php else: ?>
                        <table class="icsfm-status-table">
                            <tbody>
                                <?php foreach ($apartment->pairs as $pair):
                                    $feed_a = null;
                                    $feed_b = null;
                                    foreach ($pair->feeds as $f) {
                                        if ($f->direction === 'a_to_b') $feed_a = $f;
                                        if ($f->direction === 'b_to_a') $feed_b = $f;
                                    }
                                    $status_a = $feed_a ? ICSFM_Admin_Dashboard::get_feed_status($feed_a, $pair->is_active, $alert_window_hours) : null;
                                    $status_b = $feed_b ? ICSFM_Admin_Dashboard::get_feed_status($feed_b, $pair->is_active, $alert_window_hours) : null;
                                ?>
                                <tr data-pair-id="<?php echo esc_attr($pair->id); ?>">
                                    <td class="icsfm-feed-label" style="padding: 8px 4px;">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-pairs&action=edit&id=' . $pair->id)); ?>" style="text-decoration:none; color:#1d2327;">
                                            <strong><?php echo esc_html($pair->platform_a_name); ?> &#8596; <?php echo esc_html($pair->platform_b_name); ?></strong>
                                        </a>
                                        <?php if (!$pair->is_active): ?>
                                            <span class="icsfm-badge icsfm-badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="icsfm-pair-directions" style="padding: 8px 4px;">
                                        <div class="icsfm-direction-row">
                                            <?php if ($status_a): ?>
                                                <span class="icsfm-dot" style="background-color: <?php echo esc_attr($status_a['color']); ?>" title="<?php echo esc_attr($status_a['label']); ?>"></span>
                                                <span class="icsfm-direction-text"><?php echo esc_html($pair->platform_a_name); ?> → <?php echo esc_html($pair->platform_b_name); ?></span>
                                                <?php if ($feed_a && $feed_a->last_polled_at): ?>
                                                    <span class="icsfm-muted" style="font-size:11px; margin-left:4px;"><?php echo esc_html(ICSFM_Admin_Dashboard::time_ago($feed_a->last_polled_at)); ?></span>
                                                <?php endif; ?>
                                                <?php if ($feed_a && $feed_a->last_fetch_status === 'error'): ?>
                                                    <span class="icsfm-badge icsfm-badge-error" style="font-size:10px;">Err</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="icsfm-direction-row">
                                            <?php if ($status_b): ?>
                                                <span class="icsfm-dot" style="background-color: <?php echo esc_attr($status_b['color']); ?>" title="<?php echo esc_attr($status_b['label']); ?>"></span>
                                                <span class="icsfm-direction-text"><?php echo esc_html($pair->platform_b_name); ?> → <?php echo esc_html($pair->platform_a_name); ?></span>
                                                <?php if ($feed_b && $feed_b->last_polled_at): ?>
                                                    <span class="icsfm-muted" style="font-size:11px; margin-left:4px;"><?php echo esc_html(ICSFM_Admin_Dashboard::time_ago($feed_b->last_polled_at)); ?></span>
                                                <?php endif; ?>
                                                <?php if ($feed_b && $feed_b->last_fetch_status === 'error'): ?>
                                                    <span class="icsfm-badge icsfm-badge-error" style="font-size:10px;">Err</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="icsfm-dashboard-footer">
        <p class="icsfm-cron-info">
            <?php if ($next_cron): ?>
                Next staleness check: <strong><?php echo esc_html(wp_date('Y-m-d H:i:s', $next_cron)); ?></strong>
                (<?php echo esc_html(ICSFM_Admin_Dashboard::time_ago(gmdate('Y-m-d H:i:s', $next_cron - (time() - $next_cron)))); ?>)
            <?php else: ?>
                <span class="icsfm-text-error">Cron job not scheduled!</span>
                Try deactivating and reactivating the plugin.
            <?php endif; ?>
        </p>
    </div>

    <?php if (!empty($recent_logs)): ?>
        <div class="icsfm-card icsfm-card-wide">
            <h2 class="icsfm-card-title">
                Recent Activity
                <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-logs')); ?>" class="icsfm-link-small">View all logs</a>
            </h2>
            <table class="icsfm-log-table">
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                        <tr class="icsfm-log-<?php echo esc_attr($log->level); ?>">
                            <td class="icsfm-log-time"><?php echo esc_html(wp_date('M j H:i:s', strtotime($log->created_at))); ?></td>
                            <td class="icsfm-log-level">
                                <span class="icsfm-badge icsfm-badge-<?php echo esc_attr($log->level); ?>"><?php echo esc_html(strtoupper($log->level)); ?></span>
                            </td>
                            <td class="icsfm-log-source"><?php echo esc_html($log->source); ?></td>
                            <td class="icsfm-log-message"><?php echo esc_html($log->message); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-refresh dashboard every 60 seconds
(function() {
    setInterval(function() {
        if (typeof jQuery !== 'undefined' && typeof icsfm !== 'undefined') {
            jQuery.post(icsfm.ajax_url, {
                action: 'icsfm_refresh_dashboard',
                nonce: icsfm.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    }, 60000);
})();
</script>
