<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1><?php echo $pair ? 'Edit Feed Pair' : 'Add New Feed Pair'; ?></h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field(wp_unslash($_GET['message']));
                if ($msg === 'created') echo 'Feed pair created. Proxy URLs are shown below.';
                elseif ($msg === 'updated') echo 'Feed pair updated.';
                elseif ($msg === 'token_regenerated') echo 'Proxy token regenerated. Update the URL in the external platform.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('icsfm_save_pair'); ?>
        <input type="hidden" name="action" value="icsfm_save_pair">
        <input type="hidden" name="pair_id" value="<?php echo $pair ? (int) $pair->id : 0; ?>">

        <table class="form-table">
            <tr>
                <th scope="row"><label for="apartment_id">Apartment</label></th>
                <td>
                    <?php if ($pair): ?>
                        <strong><?php echo esc_html($pair->apartment_name); ?></strong>
                        <input type="hidden" name="apartment_id" value="<?php echo (int) $pair->apartment_id; ?>">
                    <?php else: ?>
                        <select name="apartment_id" id="apartment_id" required>
                            <option value="">— Select Apartment —</option>
                            <?php foreach ($apartments as $apt): ?>
                                <option value="<?php echo (int) $apt->id; ?>"><?php echo esc_html($apt->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($apartments)): ?>
                            <p class="description icsfm-text-error">
                                No apartments found. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments&action=add')); ?>">Add one first</a>.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="platform_a_id">Platform A</label></th>
                <td>
                    <?php if ($pair): ?>
                        <strong><?php echo esc_html($pair->platform_a_name); ?></strong>
                        <input type="hidden" name="platform_a_id" value="<?php echo (int) $pair->platform_a_id; ?>">
                    <?php else: ?>
                        <select name="platform_a_id" id="platform_a_id" required>
                            <option value="">— Select Platform A —</option>
                            <?php foreach ($platforms as $plat): ?>
                                <option value="<?php echo (int) $plat->id; ?>"><?php echo esc_html($plat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($platforms)): ?>
                            <p class="description icsfm-text-error">
                                No platforms found. <a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-platforms&action=add')); ?>">Add platforms first</a>.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="platform_b_id">Platform B</label></th>
                <td>
                    <?php if ($pair): ?>
                        <strong><?php echo esc_html($pair->platform_b_name); ?></strong>
                        <input type="hidden" name="platform_b_id" value="<?php echo (int) $pair->platform_b_id; ?>">
                    <?php else: ?>
                        <select name="platform_b_id" id="platform_b_id" required>
                            <option value="">— Select Platform B —</option>
                            <?php foreach ($platforms as $plat): ?>
                                <option value="<?php echo (int) $plat->id; ?>"><?php echo esc_html($plat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($pair): ?>
            <tr>
                <th scope="row">Active</th>
                <td>
                    <label>
                        <input type="checkbox" name="is_active" value="1"
                            <?php checked($pair->is_active); ?>>
                        Feed pair is active and being monitored
                    </label>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <?php
        // Determine platform names for instructions
        $plat_a_name = $pair ? $pair->platform_a_name : null;
        $plat_b_name = $pair ? $pair->platform_b_name : null;
        ?>

        <!-- Feed A→B block -->
        <div class="icsfm-pair-feed-block" id="icsfm-block-a-to-b">
            <h3 class="icsfm-pair-feed-heading">
                <span class="icsfm-direction-label" id="icsfm-label-a-to-b">
                    <?php echo $plat_a_name ? esc_html($plat_a_name) . ' → ' . esc_html($plat_b_name) : 'Platform A → Platform B'; ?>
                </span>
            </h3>

            <div class="icsfm-pair-instruction">
                <p>Go to <strong><span class="icsfm-platform-a-name"><?php echo $plat_a_name ? esc_html($plat_a_name) : 'Platform A'; ?></span></strong>
                and copy the ICS calendar export URL for this apartment. Paste it here:</p>
            </div>

            <div class="icsfm-pair-field">
                <label for="source_url_a_to_b">Source ICS URL:</label>
                <input type="url" name="source_url_a_to_b" id="source_url_a_to_b" class="large-text"
                       value="<?php echo $feed_a_to_b ? esc_attr($feed_a_to_b->source_url) : ''; ?>"
                       placeholder="https://...">
                <button type="button" class="button button-small icsfm-test-feed-btn" data-url-field="source_url_a_to_b">Test</button>
                <span class="icsfm-test-result" id="icsfm-test-result-a-to-b"></span>
            </div>

            <?php if ($feed_a_to_b): ?>
                <?php $proxy_url_a_to_b = rest_url("icsfm/v1/feed/{$feed_a_to_b->proxy_token}"); ?>
                <div class="icsfm-pair-proxy">
                    <p>Copy this proxy URL and paste it into <strong><?php echo esc_html($plat_b_name); ?></strong>'s calendar import settings:</p>
                    <div class="icsfm-proxy-url-display">
                        <input type="text" readonly class="large-text icsfm-url-readonly" value="<?php echo esc_attr($proxy_url_a_to_b); ?>">
                        <button type="button" class="button button-small icsfm-copy-btn" data-url="<?php echo esc_attr($proxy_url_a_to_b); ?>">Copy</button>
                    </div>
                    <p>
                        <a href="<?php echo esc_url(wp_nonce_url(
                            admin_url('admin-post.php?action=icsfm_regenerate_token&id=' . $feed_a_to_b->id . '&pair_id=' . $pair->id),
                            'icsfm_regenerate_token_' . $feed_a_to_b->id
                        )); ?>" onclick="return confirm('Regenerate token? The old proxy URL will stop working immediately.');"
                           class="icsfm-text-warning" style="font-size:12px;">Regenerate token</a>
                    </p>
                </div>

                <?php if (!empty($poll_history_a_to_b)): ?>
                    <div class="icsfm-pair-poll-history">
                        <h4>Recent Poll History</h4>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>IP</th>
                                    <th>Status</th>
                                    <th>Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($poll_history_a_to_b as $poll): ?>
                                    <tr>
                                        <td><?php echo esc_html(wp_date('M j H:i:s', strtotime($poll->polled_at))); ?></td>
                                        <td><code><?php echo esc_html($poll->remote_ip); ?></code></td>
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
            <?php endif; ?>
        </div>

        <!-- Feed B→A block -->
        <div class="icsfm-pair-feed-block" id="icsfm-block-b-to-a">
            <h3 class="icsfm-pair-feed-heading">
                <span class="icsfm-direction-label" id="icsfm-label-b-to-a">
                    <?php echo $plat_b_name ? esc_html($plat_b_name) . ' → ' . esc_html($plat_a_name) : 'Platform B → Platform A'; ?>
                </span>
            </h3>

            <div class="icsfm-pair-instruction">
                <p>Go to <strong><span class="icsfm-platform-b-name"><?php echo $plat_b_name ? esc_html($plat_b_name) : 'Platform B'; ?></span></strong>
                and copy the ICS calendar export URL for this apartment. Paste it here:</p>
            </div>

            <div class="icsfm-pair-field">
                <label for="source_url_b_to_a">Source ICS URL:</label>
                <input type="url" name="source_url_b_to_a" id="source_url_b_to_a" class="large-text"
                       value="<?php echo $feed_b_to_a ? esc_attr($feed_b_to_a->source_url) : ''; ?>"
                       placeholder="https://...">
                <button type="button" class="button button-small icsfm-test-feed-btn" data-url-field="source_url_b_to_a">Test</button>
                <span class="icsfm-test-result" id="icsfm-test-result-b-to-a"></span>
            </div>

            <?php if ($feed_b_to_a): ?>
                <?php $proxy_url_b_to_a = rest_url("icsfm/v1/feed/{$feed_b_to_a->proxy_token}"); ?>
                <div class="icsfm-pair-proxy">
                    <p>Copy this proxy URL and paste it into <strong><?php echo esc_html($plat_a_name); ?></strong>'s calendar import settings:</p>
                    <div class="icsfm-proxy-url-display">
                        <input type="text" readonly class="large-text icsfm-url-readonly" value="<?php echo esc_attr($proxy_url_b_to_a); ?>">
                        <button type="button" class="button button-small icsfm-copy-btn" data-url="<?php echo esc_attr($proxy_url_b_to_a); ?>">Copy</button>
                    </div>
                    <p>
                        <a href="<?php echo esc_url(wp_nonce_url(
                            admin_url('admin-post.php?action=icsfm_regenerate_token&id=' . $feed_b_to_a->id . '&pair_id=' . $pair->id),
                            'icsfm_regenerate_token_' . $feed_b_to_a->id
                        )); ?>" onclick="return confirm('Regenerate token? The old proxy URL will stop working immediately.');"
                           class="icsfm-text-warning" style="font-size:12px;">Regenerate token</a>
                    </p>
                </div>

                <?php if (!empty($poll_history_b_to_a)): ?>
                    <div class="icsfm-pair-poll-history">
                        <h4>Recent Poll History</h4>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>IP</th>
                                    <th>Status</th>
                                    <th>Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($poll_history_b_to_a as $poll): ?>
                                    <tr>
                                        <td><?php echo esc_html(wp_date('M j H:i:s', strtotime($poll->polled_at))); ?></td>
                                        <td><code><?php echo esc_html($poll->remote_ip); ?></code></td>
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
            <?php endif; ?>
        </div>

        <?php submit_button($pair ? 'Update Feed Pair' : 'Create Feed Pair'); ?>
    </form>

    <p><a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-pairs')); ?>">&larr; Back to feed pairs</a></p>
</div>
