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

                    <?php if (empty($apartment->feeds)): ?>
                        <p class="icsfm-muted">No feeds configured. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds&action=add')); ?>">Add a feed</a>.</p>
                    <?php else: ?>
                        <table class="icsfm-status-table">
                            <tbody>
                                <?php foreach ($apartment->feeds as $feed):
                                    $status = ICSFM_Admin_Dashboard::get_feed_status($feed);
                                ?>
                                <tr data-feed-id="<?php echo esc_attr($feed->id); ?>">
                                    <td class="icsfm-status-dot">
                                        <span class="icsfm-dot" style="background-color: <?php echo esc_attr($status['color']); ?>" title="<?php echo esc_attr($status['label']); ?>"></span>
                                    </td>
                                    <td class="icsfm-feed-label">
                                        <span class="icsfm-platform-badge icsfm-platform-<?php echo esc_attr($feed->platform); ?>"><?php echo esc_html(ucfirst($feed->platform)); ?></span>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds&action=edit&id=' . $feed->id)); ?>">
                                            <?php echo esc_html($feed->label); ?>
                                        </a>
                                    </td>
                                    <td class="icsfm-feed-poll-time">
                                        <?php if ($feed->last_polled_at): ?>
                                            Last poll: <?php echo esc_html(ICSFM_Admin_Dashboard::time_ago($feed->last_polled_at)); ?>
                                            <br><span class="icsfm-muted"><?php echo esc_html(gmdate('j M Y, g:ia', strtotime($feed->last_polled_at))); ?> UTC</span>
                                        <?php else: ?>
                                            <span class="icsfm-muted">Never polled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="icsfm-feed-fetch-status">
                                        <?php if ($feed->last_fetch_status === 'error'): ?>
                                            <span class="icsfm-badge icsfm-badge-error">Upstream Error</span>
                                        <?php elseif ($feed->last_fetch_status === 'ok'): ?>
                                            <span class="icsfm-badge icsfm-badge-ok">OK</span>
                                        <?php endif; ?>
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
                    // Reload the page for simplicity; could do DOM updates for smoother UX
                    location.reload();
                }
            });
        }
    }, 60000);
})();
</script>
