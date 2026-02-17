<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1><?php echo $feed ? 'Edit Feed' : 'Add New Feed'; ?></h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field(wp_unslash($_GET['message']));
                if ($msg === 'created') echo 'Feed created. The proxy URL is shown below.';
                elseif ($msg === 'updated') echo 'Feed updated.';
                elseif ($msg === 'token_regenerated') echo 'Proxy token regenerated. Update the URL in your external platforms.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($feed): ?>
        <div class="icsfm-card icsfm-card-wide">
            <h2 class="icsfm-card-title">Proxy URL</h2>
            <?php $proxy_url = rest_url("icsfm/v1/feed/{$feed->proxy_token}"); ?>
            <div class="icsfm-proxy-url-display">
                <input type="text" readonly class="large-text" value="<?php echo esc_attr($proxy_url); ?>" id="icsfm-proxy-url-input">
                <button type="button" class="button icsfm-copy-btn" data-url="<?php echo esc_attr($proxy_url); ?>">Copy URL</button>
            </div>
            <p class="description">
                Give this URL to Airbnb/VRBO/your booking platform instead of the real ICS feed URL.
            </p>
            <p>
                <a href="<?php echo esc_url(wp_nonce_url(
                    admin_url('admin-post.php?action=icsfm_regenerate_token&id=' . $feed->id),
                    'icsfm_regenerate_token_' . $feed->id
                )); ?>" onclick="return confirm('Regenerate token? The old proxy URL will stop working immediately. You will need to update the URL in all external platforms.');"
                   class="button button-small icsfm-text-warning">Regenerate Token</a>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('icsfm_save_feed'); ?>
        <input type="hidden" name="action" value="icsfm_save_feed">
        <input type="hidden" name="feed_id" value="<?php echo $feed ? (int) $feed->id : 0; ?>">

        <table class="form-table">
            <tr>
                <th scope="row"><label for="apartment_id">Apartment</label></th>
                <td>
                    <select name="apartment_id" id="apartment_id" required>
                        <option value="">— Select —</option>
                        <?php foreach ($apartments as $apt): ?>
                            <option value="<?php echo (int) $apt->id; ?>"
                                <?php selected($feed ? $feed->apartment_id : '', $apt->id); ?>>
                                <?php echo esc_html($apt->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($apartments)): ?>
                        <p class="description icsfm-text-error">
                            No apartments found. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments&action=add')); ?>">Add one first</a>.
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="platform">Platform</label></th>
                <td>
                    <select name="platform" id="platform">
                        <?php foreach (ICSFM_Admin_Feeds::get_platform_options() as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>"
                                <?php selected($feed ? $feed->platform : 'other', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="source_url">Source ICS URL</label></th>
                <td>
                    <input type="url" name="source_url" id="source_url" class="large-text"
                           value="<?php echo $feed ? esc_attr($feed->source_url) : ''; ?>"
                           placeholder="https://..." required>
                    <p class="description">The real ICS feed URL from the platform.</p>
                    <button type="button" class="button button-small icsfm-test-feed-btn" id="icsfm-test-feed">Test Source URL</button>
                    <span id="icsfm-test-result" class="icsfm-test-result"></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="alert_window_hours">Alert Window (hours)</label></th>
                <td>
                    <input type="number" name="alert_window_hours" id="alert_window_hours" class="small-text" min="1" max="168"
                           value="<?php echo $feed ? (int) $feed->alert_window_hours : (int) ($settings['default_alert_window'] ?? 6); ?>">
                    <p class="description">Alert if this feed is not polled within this many hours.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Active</th>
                <td>
                    <label>
                        <input type="checkbox" name="is_active" value="1"
                            <?php checked(!$feed || $feed->is_active); ?>>
                        Feed is active and being monitored
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button($feed ? 'Update Feed' : 'Add Feed'); ?>
    </form>

    <?php if ($feed && !empty($poll_history)): ?>
        <div class="icsfm-card icsfm-card-wide">
            <h2 class="icsfm-card-title">Recent Poll History</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                        <th>Upstream Status</th>
                        <th>Response Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poll_history as $poll): ?>
                        <tr>
                            <td><?php echo esc_html(wp_date('M j H:i:s', strtotime($poll->polled_at))); ?></td>
                            <td><code><?php echo esc_html($poll->remote_ip); ?></code></td>
                            <td class="icsfm-ua-cell" title="<?php echo esc_attr($poll->user_agent); ?>">
                                <?php echo esc_html(substr($poll->user_agent ?? '', 0, 60)); ?>
                            </td>
                            <td>
                                <?php if ($poll->upstream_status === 'ok'): ?>
                                    <span class="icsfm-badge icsfm-badge-ok">OK</span>
                                <?php else: ?>
                                    <span class="icsfm-badge icsfm-badge-error"><?php echo esc_html($poll->upstream_status); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $poll->response_time_ms; ?>ms</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p><a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds')); ?>">&larr; Back to feeds</a></p>
</div>
