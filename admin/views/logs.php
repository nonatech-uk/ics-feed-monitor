<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1>
        ICS Feed Monitor — Logs
        <a href="<?php echo esc_url(wp_nonce_url(
            admin_url('admin-post.php?action=icsfm_clear_logs'),
            'icsfm_clear_logs'
        )); ?>" class="page-title-action icsfm-delete-link" onclick="return confirm('Clear all log entries? This cannot be undone.');">Clear All Logs</a>
    </h1>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'cleared'): ?>
        <div class="notice notice-success is-dismissible">
            <p>All logs cleared.</p>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="icsfm-log-filters">
        <input type="hidden" name="page" value="icsfm-logs">

        <select name="level">
            <option value="">All Levels</option>
            <?php foreach (['debug', 'info', 'warning', 'error'] as $level): ?>
                <option value="<?php echo esc_attr($level); ?>" <?php selected($args['level'], $level); ?>>
                    <?php echo esc_html(ucfirst($level)); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="source">
            <option value="">All Sources</option>
            <?php foreach (['proxy', 'cron', 'webhook', 'admin', 'system'] as $source): ?>
                <option value="<?php echo esc_attr($source); ?>" <?php selected($args['source'], $source); ?>>
                    <?php echo esc_html(ucfirst($source)); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="s" value="<?php echo esc_attr($args['search']); ?>" placeholder="Search logs..." class="regular-text">

        <?php submit_button('Filter', 'secondary', 'submit', false); ?>

        <?php if (!empty($args['level']) || !empty($args['source']) || !empty($args['search'])): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-logs')); ?>" class="button">Clear Filters</a>
        <?php endif; ?>
    </form>

    <p class="icsfm-log-count">
        Showing <?php echo count($result['logs']); ?> of <?php echo (int) $result['total']; ?> entries
        (page <?php echo (int) $args['page']; ?> of <?php echo max(1, (int) $result['pages']); ?>)
    </p>

    <?php if (empty($result['logs'])): ?>
        <p>No log entries found.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped icsfm-log-table-full">
            <thead>
                <tr>
                    <th class="icsfm-col-time">Time (UTC)</th>
                    <th class="icsfm-col-level">Level</th>
                    <th class="icsfm-col-source">Source</th>
                    <th>Message</th>
                    <th class="icsfm-col-context">Context</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['logs'] as $log): ?>
                    <tr class="icsfm-log-<?php echo esc_attr($log->level); ?>">
                        <td class="icsfm-col-time">
                            <?php echo esc_html(wp_date('Y-m-d H:i:s', strtotime($log->created_at))); ?>
                        </td>
                        <td class="icsfm-col-level">
                            <span class="icsfm-badge icsfm-badge-<?php echo esc_attr($log->level); ?>">
                                <?php echo esc_html(strtoupper($log->level)); ?>
                            </span>
                        </td>
                        <td class="icsfm-col-source"><?php echo esc_html($log->source); ?></td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td class="icsfm-col-context">
                            <?php if ($log->context): ?>
                                <details>
                                    <summary>Show</summary>
                                    <pre class="icsfm-context-pre"><?php echo esc_html(wp_json_encode(json_decode($log->context), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($result['pages'] > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $base_url = admin_url('admin.php?page=icsfm-logs');
                    if (!empty($args['level'])) $base_url = add_query_arg('level', $args['level'], $base_url);
                    if (!empty($args['source'])) $base_url = add_query_arg('source', $args['source'], $base_url);
                    if (!empty($args['search'])) $base_url = add_query_arg('s', $args['search'], $base_url);

                    $current = (int) $args['page'];
                    $total_pages = (int) $result['pages'];
                    ?>

                    <?php if ($current > 1): ?>
                        <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $current - 1, $base_url)); ?>">&lsaquo; Previous</a>
                    <?php endif; ?>

                    <span class="paging-input">
                        Page <?php echo $current; ?> of <?php echo $total_pages; ?>
                    </span>

                    <?php if ($current < $total_pages): ?>
                        <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $current + 1, $base_url)); ?>">Next &rsaquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
