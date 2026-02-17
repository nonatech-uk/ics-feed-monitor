<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1>ICS Feed Monitor — Settings</h1>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
        <div class="notice notice-success is-dismissible">
            <p>Settings saved.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['hc_result'])): ?>
        <?php $hc_msg = sanitize_text_field(wp_unslash($_GET['hc_result'])); ?>
        <div class="notice <?php echo strpos($hc_msg, 'successfully') !== false ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><strong>Healthcheck test:</strong> <?php echo esc_html($hc_msg); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('icsfm_save_settings'); ?>
        <input type="hidden" name="action" value="icsfm_save_settings">

        <h2>Webhook Configuration</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="webhook_url">Webhook URL</label></th>
                <td>
                    <input type="url" name="webhook_url" id="webhook_url" class="large-text"
                           value="<?php echo esc_attr($settings['webhook_url'] ?? ''); ?>"
                           placeholder="https://example.com/webhook">
                    <p class="description">URL to POST/GET when a feed goes stale. Supports any webhook receiver (Zapier, Make, ntfy.sh, custom).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="webhook_method">Webhook Method</label></th>
                <td>
                    <select name="webhook_method" id="webhook_method">
                        <option value="POST" <?php selected($settings['webhook_method'] ?? 'POST', 'POST'); ?>>POST (JSON payload)</option>
                        <option value="GET" <?php selected($settings['webhook_method'] ?? 'POST', 'GET'); ?>>GET (event in query string)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="webhook_secret">Webhook Secret</label></th>
                <td>
                    <input type="text" name="webhook_secret" id="webhook_secret" class="regular-text"
                           value="<?php echo esc_attr($settings['webhook_secret'] ?? ''); ?>">
                    <p class="description">Used to sign webhook payloads (HMAC-SHA256). Sent in the <code>X-ICSFM-Signature</code> header. Leave empty to disable signing.</p>
                    <p class="description">You can also define <code>ICSFM_WEBHOOK_SECRET</code> in <code>wp-config.php</code> to override this value.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Test Webhook</th>
                <td>
                    <button type="button" class="button" id="icsfm-test-webhook">Send Test Webhook</button>
                    <span id="icsfm-webhook-test-result" class="icsfm-test-result"></span>
                </td>
            </tr>
        </table>

        <h2>Healthcheck / Heartbeat</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="healthcheck_url">Healthcheck URL</label></th>
                <td>
                    <input type="url" name="healthcheck_url" id="healthcheck_url" class="large-text"
                           value="<?php echo esc_attr($settings['healthcheck_url'] ?? ''); ?>"
                           placeholder="https://hc-ping.com/your-uuid-here">
                    <p class="description">
                        Pinged on every cron run as a heartbeat. If the cron stops running,
                        your healthcheck service will alert you. Compatible with
                        <a href="https://healthchecks.io" target="_blank">Healthchecks.io</a>,
                        <a href="https://cronitor.io" target="_blank">Cronitor</a>,
                        <a href="https://betteruptime.com" target="_blank">Better Uptime</a>,
                        or any URL that accepts a GET ping.
                    </p>
                    <p class="description">
                        The plugin appends <code>/start</code> at job start, pings the bare URL on success,
                        and appends <code>/fail</code> if stale feeds were found.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">Test Healthcheck</th>
                <td>
                    <button type="button" class="button" id="icsfm-test-healthcheck">Ping Now</button>
                    <span id="icsfm-healthcheck-test-result" class="icsfm-test-result"></span>
                    <br><br>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=icsfm_test_healthcheck_direct'), 'icsfm_test_healthcheck_direct')); ?>" class="button button-small">Ping Now (direct)</a>
                    <p class="description">Use the direct link if the button above doesn't respond.</p>
                </td>
            </tr>
        </table>

        <h2>Default Feed Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="default_alert_window">Default Alert Window</label></th>
                <td>
                    <input type="number" name="default_alert_window" id="default_alert_window" class="small-text" min="1" max="168"
                           value="<?php echo (int) ($settings['default_alert_window'] ?? 6); ?>"> hours
                    <p class="description">Default hours before alerting on un-polled feeds. Can be overridden per feed.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="alert_cooldown_hours">Alert Cooldown</label></th>
                <td>
                    <input type="number" name="alert_cooldown_hours" id="alert_cooldown_hours" class="small-text" min="1" max="168"
                           value="<?php echo (int) ($settings['alert_cooldown_hours'] ?? 6); ?>"> hours
                    <p class="description">Minimum hours between repeat alerts for the same stale feed.</p>
                </td>
            </tr>
        </table>

        <h2>Maintenance</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="log_retention_days">Log Retention</label></th>
                <td>
                    <input type="number" name="log_retention_days" id="log_retention_days" class="small-text" min="1" max="365"
                           value="<?php echo (int) ($settings['log_retention_days'] ?? 30); ?>"> days
                    <p class="description">Poll log and system log entries older than this are automatically deleted.</p>
                </td>
            </tr>
        </table>

        <h2>Feed Proxy URLs</h2>
        <p class="description" style="margin-bottom: 12px;">
            Enter the real ICS feed URL for each platform. The proxy URL is what you give to the <strong>other</strong> platforms
            to import — it fetches the real data and passes it through, while logging every request so we can detect when polling stops.
        </p>

        <?php if (empty($grouped)): ?>
            <p>No apartments configured yet. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments&action=add')); ?>">Add an apartment</a> first, then <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds&action=add')); ?>">add feeds</a>.</p>
        <?php else: ?>
            <?php
            $all_platform_labels = [
                'booking' => 'Booking Platform',
                'airbnb'  => 'Airbnb',
                'vrbo'    => 'VRBO',
                'other'   => 'Other',
            ];
            ?>
            <?php foreach ($grouped as $apartment):
                $apt_platforms = [];
                foreach ($apartment->feeds as $f) {
                    $apt_platforms[$f->platform] = $all_platform_labels[$f->platform] ?? ucfirst($f->platform);
                }
            ?>
                <div class="icsfm-card icsfm-card-wide">
                    <h3 class="icsfm-card-title"><?php echo esc_html($apartment->name); ?></h3>

                    <?php if (empty($apartment->feeds)): ?>
                        <p class="icsfm-muted">No feeds configured. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-feeds&action=add')); ?>">Add a feed</a>.</p>
                    <?php else: ?>
                        <?php foreach ($apartment->feeds as $feed):
                            $proxy_url = rest_url("icsfm/v1/feed/{$feed->proxy_token}");
                            $platform_label = $all_platform_labels[$feed->platform] ?? ucfirst($feed->platform);
                            $consumers = [];
                            foreach ($apt_platforms as $plat_key => $plat_label) {
                                if ($plat_key !== $feed->platform) {
                                    $consumers[] = $plat_label;
                                }
                            }
                            $consumers_str = !empty($consumers) ? implode(', ', $consumers) : 'other platforms';
                        ?>
                            <div class="icsfm-feed-proxy-block">
                                <div class="icsfm-feed-proxy-header">
                                    <span class="icsfm-platform-badge icsfm-platform-<?php echo esc_attr($feed->platform); ?>">
                                        <?php echo esc_html($platform_label); ?>
                                    </span>
                                </div>

                                <table class="icsfm-feed-proxy-detail">
                                    <tr>
                                        <td class="icsfm-proxy-label">Real <?php echo esc_html($platform_label); ?> feed URL:</td>
                                        <td>
                                            <input type="url" name="feed_source_url[<?php echo (int) $feed->id; ?>]" class="large-text"
                                                   value="<?php echo esc_attr($feed->source_url); ?>"
                                                   placeholder="https://...">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="icsfm-proxy-label">Proxy URL<br><span class="icsfm-proxy-hint">paste into <?php echo esc_html($consumers_str); ?></span></td>
                                        <td>
                                            <div class="icsfm-proxy-url-display">
                                                <input type="text" readonly class="large-text icsfm-url-readonly" value="<?php echo esc_attr($proxy_url); ?>">
                                                <button type="button" class="button button-small icsfm-copy-btn" data-url="<?php echo esc_attr($proxy_url); ?>">Copy</button>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php submit_button('Save Settings'); ?>
    </form>

    <h2>System Status</h2>
    <table class="form-table">
        <tr>
            <th scope="row">Plugin Version</th>
            <td><code><?php echo esc_html(ICSFM_VERSION); ?></code></td>
        </tr>
        <tr>
            <th scope="row">Next Cron Run</th>
            <td>
                <?php if ($next_cron): ?>
                    <?php echo esc_html(wp_date('Y-m-d H:i:s', $next_cron)); ?>
                    (in <?php echo esc_html(human_time_diff(time(), $next_cron)); ?>)
                <?php else: ?>
                    <span class="icsfm-text-error">Not scheduled!</span>
                    Try deactivating and reactivating the plugin.
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th scope="row">WP-Cron</th>
            <td>
                <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
                    <span class="icsfm-badge icsfm-badge-ok">System cron configured</span>
                    <p class="description">WordPress pseudo-cron is disabled. Make sure your system cron hits <code>wp-cron.php</code> regularly.</p>
                <?php else: ?>
                    <span class="icsfm-badge icsfm-badge-warning">Pseudo-cron (visitor-triggered)</span>
                    <p class="description">
                        WordPress cron only runs when someone visits the site. For reliable monitoring on low-traffic sites, add a real system cron:
                    </p>
                    <pre style="background: #f0f0f0; padding: 8px; max-width: 600px;">*/15 * * * * wget -q -O /dev/null '<?php echo esc_html(home_url('/wp-cron.php?doing_wp_cron')); ?>' &gt;/dev/null 2&gt;&amp;1</pre>
                    <p class="description">Then add to <code>wp-config.php</code>: <code>define('DISABLE_WP_CRON', true);</code></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>
