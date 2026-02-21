<?php
defined('ABSPATH') || exit;

class ICSFM_Feed_Repository {

    private function apartments_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'icsfm_apartments';
    }

    private function platforms_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'icsfm_platforms';
    }

    private function pairs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'icsfm_feed_pairs';
    }

    private function feeds_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'icsfm_feeds';
    }

    // ─── Apartments ──────────────────────────────────────────

    public function get_all_apartments(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->apartments_table()} ORDER BY sort_order ASC, name ASC"
        ) ?: [];
    }

    public function get_apartment(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->apartments_table()} WHERE id = %d", $id)
        );
    }

    public function create_apartment(array $data): int {
        global $wpdb;

        $slug = sanitize_title($data['name']);
        $wpdb->insert($this->apartments_table(), [
            'name'       => sanitize_text_field($data['name']),
            'slug'       => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_at' => current_time('mysql', true),
        ], ['%s', '%s', '%d', '%s']);

        $id = (int) $wpdb->insert_id;

        ICSFM_Logger::info('admin', 'Apartment created', [
            'apartment_id' => $id,
            'name'         => $data['name'],
            'user'         => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $id;
    }

    public function update_apartment(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        $format = [];

        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
            $update['slug'] = sanitize_title($data['name']);
            $format[] = '%s';
            $format[] = '%s';
        }

        if (isset($data['sort_order'])) {
            $update['sort_order'] = (int) $data['sort_order'];
            $format[] = '%d';
        }

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update($this->apartments_table(), $update, ['id' => $id], $format, ['%d']);

        ICSFM_Logger::info('admin', 'Apartment updated', [
            'apartment_id' => $id,
            'changes'      => $update,
            'user'         => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $result !== false;
    }

    public function delete_apartment(int $id): bool {
        global $wpdb;

        // Delete all pairs (and their feeds) for this apartment
        $pairs = $this->get_pairs_for_apartment($id);
        foreach ($pairs as $pair) {
            $this->delete_pair((int) $pair->id);
        }

        $result = $wpdb->delete($this->apartments_table(), ['id' => $id], ['%d']);

        ICSFM_Logger::info('admin', 'Apartment deleted', [
            'apartment_id'  => $id,
            'pairs_deleted' => count($pairs),
            'user'          => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $result !== false;
    }

    public function count_apartments(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->apartments_table()}");
    }

    // ─── Platforms ───────────────────────────────────────────

    public function get_all_platforms(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->platforms_table()} ORDER BY sort_order ASC, name ASC"
        ) ?: [];
    }

    public function get_platform(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->platforms_table()} WHERE id = %d", $id)
        );
    }

    public function create_platform(array $data): int {
        global $wpdb;

        $slug = sanitize_title($data['name']);
        $wpdb->insert($this->platforms_table(), [
            'name'       => sanitize_text_field($data['name']),
            'slug'       => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_at' => current_time('mysql', true),
        ], ['%s', '%s', '%d', '%s']);

        $id = (int) $wpdb->insert_id;

        ICSFM_Logger::info('admin', 'Platform created', [
            'platform_id' => $id,
            'name'        => $data['name'],
            'user'        => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $id;
    }

    public function update_platform(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        $format = [];

        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
            $update['slug'] = sanitize_title($data['name']);
            $format[] = '%s';
            $format[] = '%s';
        }

        if (isset($data['sort_order'])) {
            $update['sort_order'] = (int) $data['sort_order'];
            $format[] = '%d';
        }

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update($this->platforms_table(), $update, ['id' => $id], $format, ['%d']);

        ICSFM_Logger::info('admin', 'Platform updated', [
            'platform_id' => $id,
            'changes'     => $update,
            'user'        => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $result !== false;
    }

    public function delete_platform(int $id): bool {
        global $wpdb;

        // Check if platform is referenced by any pair
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->pairs_table()} WHERE platform_a_id = %d OR platform_b_id = %d",
            $id, $id
        ));

        if ($count > 0) {
            return false;
        }

        $result = $wpdb->delete($this->platforms_table(), ['id' => $id], ['%d']);

        ICSFM_Logger::info('admin', 'Platform deleted', [
            'platform_id' => $id,
            'user'        => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $result !== false;
    }

    public function count_platforms(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->platforms_table()}");
    }

    public function platform_in_use(int $id): bool {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->pairs_table()} WHERE platform_a_id = %d OR platform_b_id = %d",
            $id, $id
        )) > 0;
    }

    // ─── Feed Pairs ─────────────────────────────────────────

    public function get_all_pairs(): array {
        global $wpdb;
        $pt = $this->pairs_table();
        $at = $this->apartments_table();
        $plt = $this->platforms_table();

        return $wpdb->get_results(
            "SELECT p.*,
                    a.name AS apartment_name, a.sort_order AS apartment_sort_order,
                    pa.name AS platform_a_name, pa.slug AS platform_a_slug,
                    pb.name AS platform_b_name, pb.slug AS platform_b_slug
             FROM {$pt} p
             LEFT JOIN {$at} a ON a.id = p.apartment_id
             LEFT JOIN {$plt} pa ON pa.id = p.platform_a_id
             LEFT JOIN {$plt} pb ON pb.id = p.platform_b_id
             ORDER BY a.sort_order ASC, a.name ASC, pa.sort_order ASC, pb.sort_order ASC"
        ) ?: [];
    }

    public function get_pair(int $id): ?object {
        global $wpdb;
        $pt = $this->pairs_table();
        $at = $this->apartments_table();
        $plt = $this->platforms_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.*,
                    a.name AS apartment_name,
                    pa.name AS platform_a_name, pa.slug AS platform_a_slug,
                    pb.name AS platform_b_name, pb.slug AS platform_b_slug
             FROM {$pt} p
             LEFT JOIN {$at} a ON a.id = p.apartment_id
             LEFT JOIN {$plt} pa ON pa.id = p.platform_a_id
             LEFT JOIN {$plt} pb ON pb.id = p.platform_b_id
             WHERE p.id = %d",
            $id
        ));
    }

    public function create_pair(array $data): int {
        global $wpdb;

        $apartment_id = (int) $data['apartment_id'];
        $platform_a_id = (int) $data['platform_a_id'];
        $platform_b_id = (int) $data['platform_b_id'];

        // Enforce platform_a_id < platform_b_id
        if ($platform_a_id > $platform_b_id) {
            [$platform_a_id, $platform_b_id] = [$platform_b_id, $platform_a_id];
            // Also swap source URLs
            $source_a_to_b = $data['source_url_b_to_a'] ?? '';
            $source_b_to_a = $data['source_url_a_to_b'] ?? '';
        } else {
            $source_a_to_b = $data['source_url_a_to_b'] ?? '';
            $source_b_to_a = $data['source_url_b_to_a'] ?? '';
        }

        $wpdb->insert($this->pairs_table(), [
            'apartment_id'   => $apartment_id,
            'platform_a_id'  => $platform_a_id,
            'platform_b_id'  => $platform_b_id,
            'is_active'      => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'created_at'     => current_time('mysql', true),
            'updated_at'     => current_time('mysql', true),
        ], ['%d', '%d', '%d', '%d', '%s', '%s']);

        $pair_id = (int) $wpdb->insert_id;

        if ($pair_id > 0) {
            // Create both child feeds
            $this->create_feed_for_pair($pair_id, 'a_to_b', $source_a_to_b);
            $this->create_feed_for_pair($pair_id, 'b_to_a', $source_b_to_a);

            ICSFM_Logger::info('admin', 'Feed pair created', [
                'pair_id'       => $pair_id,
                'apartment_id'  => $apartment_id,
                'platform_a_id' => $platform_a_id,
                'platform_b_id' => $platform_b_id,
                'user'          => wp_get_current_user()->user_login ?? 'system',
            ]);
        }

        return $pair_id;
    }

    private function create_feed_for_pair(int $pair_id, string $direction, string $source_url): int {
        global $wpdb;

        $token = bin2hex(random_bytes(32));

        $wpdb->insert($this->feeds_table(), [
            'pair_id'           => $pair_id,
            'direction'         => $direction,
            'source_url'        => esc_url_raw($source_url),
            'proxy_token'       => $token,
            'last_fetch_status' => 'never',
            'created_at'        => current_time('mysql', true),
            'updated_at'        => current_time('mysql', true),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public function update_pair(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        $format = [];

        if (isset($data['is_active'])) {
            $update['is_active'] = (int) (bool) $data['is_active'];
            $format[] = '%d';
        }

        if (!empty($update)) {
            $update['updated_at'] = current_time('mysql', true);
            $format[] = '%s';

            $wpdb->update($this->pairs_table(), $update, ['id' => $id], $format, ['%d']);
        }

        // Update child feed source URLs if provided
        if (isset($data['source_url_a_to_b'])) {
            $this->update_feed_by_pair_direction($id, 'a_to_b', [
                'source_url' => esc_url_raw($data['source_url_a_to_b']),
            ]);
        }
        if (isset($data['source_url_b_to_a'])) {
            $this->update_feed_by_pair_direction($id, 'b_to_a', [
                'source_url' => esc_url_raw($data['source_url_b_to_a']),
            ]);
        }

        ICSFM_Logger::info('admin', 'Feed pair updated', [
            'pair_id' => $id,
            'user'    => wp_get_current_user()->user_login ?? 'system',
        ]);

        return true;
    }

    public function delete_pair(int $id): bool {
        global $wpdb;

        // Delete child feeds and their poll logs
        $feeds = $this->get_feeds_for_pair($id);
        foreach ($feeds as $feed) {
            $wpdb->delete($wpdb->prefix . 'icsfm_poll_log', ['feed_id' => (int) $feed->id], ['%d']);
            $wpdb->delete($this->feeds_table(), ['id' => (int) $feed->id], ['%d']);
        }

        $result = $wpdb->delete($this->pairs_table(), ['id' => $id], ['%d']);

        ICSFM_Logger::info('admin', 'Feed pair deleted', [
            'pair_id'       => $id,
            'feeds_deleted' => count($feeds),
            'user'          => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $result !== false;
    }

    public function get_pairs_for_apartment(int $apartment_id): array {
        global $wpdb;
        $pt = $this->pairs_table();
        $plt = $this->platforms_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*,
                    pa.name AS platform_a_name, pa.slug AS platform_a_slug,
                    pb.name AS platform_b_name, pb.slug AS platform_b_slug
             FROM {$pt} p
             LEFT JOIN {$plt} pa ON pa.id = p.platform_a_id
             LEFT JOIN {$plt} pb ON pb.id = p.platform_b_id
             WHERE p.apartment_id = %d
             ORDER BY pa.sort_order ASC, pb.sort_order ASC",
            $apartment_id
        )) ?: [];
    }

    public function count_pairs(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->pairs_table()}");
    }

    // ─── Feeds ───────────────────────────────────────────────

    public function get_feeds_for_pair(int $pair_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->feeds_table()} WHERE pair_id = %d ORDER BY direction ASC",
            $pair_id
        )) ?: [];
    }

    public function get_feed(int $id): ?object {
        global $wpdb;
        $ft = $this->feeds_table();
        $pt = $this->pairs_table();
        $at = $this->apartments_table();
        $plt = $this->platforms_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT f.*,
                    p.apartment_id, p.platform_a_id, p.platform_b_id, p.is_active AS pair_is_active,
                    a.name AS apartment_name,
                    pa.name AS platform_a_name, pa.slug AS platform_a_slug,
                    pb.name AS platform_b_name, pb.slug AS platform_b_slug
             FROM {$ft} f
             LEFT JOIN {$pt} p ON p.id = f.pair_id
             LEFT JOIN {$at} a ON a.id = p.apartment_id
             LEFT JOIN {$plt} pa ON pa.id = p.platform_a_id
             LEFT JOIN {$plt} pb ON pb.id = p.platform_b_id
             WHERE f.id = %d",
            $id
        ));
    }

    public function find_by_token(string $token): ?object {
        global $wpdb;
        $ft = $this->feeds_table();
        $pt = $this->pairs_table();
        $at = $this->apartments_table();
        $plt = $this->platforms_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT f.*,
                    p.apartment_id, p.platform_a_id, p.platform_b_id, p.is_active AS pair_is_active,
                    a.name AS apartment_name,
                    pa.name AS platform_a_name, pa.slug AS platform_a_slug,
                    pb.name AS platform_b_name, pb.slug AS platform_b_slug
             FROM {$ft} f
             LEFT JOIN {$pt} p ON p.id = f.pair_id
             LEFT JOIN {$at} a ON a.id = p.apartment_id
             LEFT JOIN {$plt} pa ON pa.id = p.platform_a_id
             LEFT JOIN {$plt} pb ON pb.id = p.platform_b_id
             WHERE f.proxy_token = %s",
            $token
        ));
    }

    public function update_feed(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        $format = [];

        $field_map = [
            'source_url'         => '%s',
            'last_polled_at'     => '%s',
            'last_poll_ip'       => '%s',
            'last_fetch_status'  => '%s',
            'last_alert_sent_at' => '%s',
            'proxy_token'        => '%s',
        ];

        foreach ($field_map as $field => $fmt) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if ($field === 'source_url') {
                    $value = esc_url_raw($value);
                }
                $update[$field] = $value;
                $format[] = $fmt;
            }
        }

        if (empty($update)) {
            return false;
        }

        $update['updated_at'] = current_time('mysql', true);
        $format[] = '%s';

        $result = $wpdb->update($this->feeds_table(), $update, ['id' => $id], $format, ['%d']);

        return $result !== false;
    }

    public function update_poll(int $feed_id, array $data): bool {
        return $this->update_feed($feed_id, $data);
    }

    private function update_feed_by_pair_direction(int $pair_id, string $direction, array $data): bool {
        global $wpdb;

        $feed = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->feeds_table()} WHERE pair_id = %d AND direction = %s",
            $pair_id, $direction
        ));

        if (!$feed) {
            return false;
        }

        return $this->update_feed((int) $feed->id, $data);
    }

    public function regenerate_token(int $feed_id): string {
        $new_token = bin2hex(random_bytes(32));
        $this->update_feed($feed_id, ['proxy_token' => $new_token]);

        ICSFM_Logger::warning('admin', 'Feed token regenerated', [
            'feed_id' => $feed_id,
            'user'    => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $new_token;
    }

    public function count_feeds(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->feeds_table()}");
    }

    public function count_active_feeds(): int {
        global $wpdb;
        $ft = $this->feeds_table();
        $pt = $this->pairs_table();

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$ft} f
             INNER JOIN {$pt} p ON p.id = f.pair_id
             WHERE p.is_active = 1"
        );
    }

    /**
     * Get feeds that are stale and haven't been alerted recently.
     * Uses global alert_window_hours instead of per-feed column.
     */
    public function get_stale_feeds(int $alert_window_hours, int $cooldown_hours): array {
        global $wpdb;
        $ft = $this->feeds_table();
        $pt = $this->pairs_table();
        $at = $this->apartments_table();
        $plt = $this->platforms_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT f.*,
                    p.apartment_id, p.platform_a_id, p.platform_b_id, p.is_active AS pair_is_active,
                    a.name AS apartment_name,
                    pa.name AS platform_a_name, pa.slug AS platform_a_slug,
                    pb.name AS platform_b_name, pb.slug AS platform_b_slug
             FROM {$ft} f
             INNER JOIN {$pt} p ON p.id = f.pair_id
             LEFT JOIN {$at} a ON a.id = p.apartment_id
             LEFT JOIN {$plt} pa ON pa.id = p.platform_a_id
             LEFT JOIN {$plt} pb ON pb.id = p.platform_b_id
             WHERE p.is_active = 1
               AND (
                 f.last_polled_at IS NULL
                 OR f.last_polled_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)
               )
               AND (
                 f.last_alert_sent_at IS NULL
                 OR f.last_alert_sent_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)
               )
             ORDER BY f.last_polled_at ASC",
            $alert_window_hours,
            $cooldown_hours
        )) ?: [];
    }

    /**
     * Get feeds that were stale (had alert sent) but have since recovered.
     */
    public function get_cleared_feeds(int $alert_window_hours): array {
        global $wpdb;
        $ft = $this->feeds_table();
        $pt = $this->pairs_table();
        $at = $this->apartments_table();
        $plt = $this->platforms_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT f.*,
                    p.apartment_id, p.platform_a_id, p.platform_b_id, p.is_active AS pair_is_active,
                    a.name AS apartment_name,
                    pa.name AS platform_a_name, pa.slug AS platform_a_slug,
                    pb.name AS platform_b_name, pb.slug AS platform_b_slug
             FROM {$ft} f
             INNER JOIN {$pt} p ON p.id = f.pair_id
             LEFT JOIN {$at} a ON a.id = p.apartment_id
             LEFT JOIN {$plt} pa ON pa.id = p.platform_a_id
             LEFT JOIN {$plt} pb ON pb.id = p.platform_b_id
             WHERE p.is_active = 1
               AND f.last_alert_sent_at IS NOT NULL
               AND f.last_polled_at IS NOT NULL
               AND f.last_polled_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)
             ORDER BY f.last_polled_at DESC",
            $alert_window_hours
        )) ?: [];
    }

    /**
     * Get ALL currently stale feeds (regardless of alert cooldown).
     * Used by cron to determine healthcheck success/fail signal.
     */
    public function get_all_stale_feeds(int $alert_window_hours): array {
        global $wpdb;
        $ft = $this->feeds_table();
        $pt = $this->pairs_table();
        $at = $this->apartments_table();
        $plt = $this->platforms_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT f.*,
                    p.apartment_id, p.platform_a_id, p.platform_b_id, p.is_active AS pair_is_active,
                    a.name AS apartment_name,
                    pa.name AS platform_a_name, pa.slug AS platform_a_slug,
                    pb.name AS platform_b_name, pb.slug AS platform_b_slug
             FROM {$ft} f
             INNER JOIN {$pt} p ON p.id = f.pair_id
             LEFT JOIN {$at} a ON a.id = p.apartment_id
             LEFT JOIN {$plt} pa ON pa.id = p.platform_a_id
             LEFT JOIN {$plt} pb ON pb.id = p.platform_b_id
             WHERE p.is_active = 1
               AND (
                 f.last_polled_at IS NULL
                 OR f.last_polled_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)
               )
             ORDER BY f.last_polled_at ASC",
            $alert_window_hours
        )) ?: [];
    }

    /**
     * Derive a human-readable label from joined feed data.
     * e.g. "Airbnb → VRBO" for a_to_b direction.
     */
    public static function derive_feed_label(object $feed): string {
        if ($feed->direction === 'a_to_b') {
            return ($feed->platform_a_name ?? '?') . ' → ' . ($feed->platform_b_name ?? '?');
        }
        return ($feed->platform_b_name ?? '?') . ' → ' . ($feed->platform_a_name ?? '?');
    }

    /**
     * Get the source platform name (the platform whose ICS is being proxied).
     */
    public static function get_source_platform(object $feed): string {
        return $feed->direction === 'a_to_b'
            ? ($feed->platform_a_name ?? '?')
            : ($feed->platform_b_name ?? '?');
    }

    /**
     * Get the destination platform name (the platform that polls the proxy).
     */
    public static function get_dest_platform(object $feed): string {
        return $feed->direction === 'a_to_b'
            ? ($feed->platform_b_name ?? '?')
            : ($feed->platform_a_name ?? '?');
    }

    /**
     * Get pairs grouped by apartment, each pair with its 2 feeds.
     */
    public function get_pairs_grouped_by_apartment(): array {
        $apartments = $this->get_all_apartments();
        $grouped = [];

        foreach ($apartments as $apartment) {
            $pairs = $this->get_pairs_for_apartment((int) $apartment->id);
            foreach ($pairs as $pair) {
                $pair->feeds = $this->get_feeds_for_pair((int) $pair->id);
            }
            $apartment->pairs = $pairs;
            $grouped[] = $apartment;
        }

        return $grouped;
    }
}
