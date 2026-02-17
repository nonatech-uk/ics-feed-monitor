<?php
defined('ABSPATH') || exit;

class ICSFM_Feed_Repository {

    private function apartments_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'icsfm_apartments';
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

        // Delete all feeds for this apartment first
        $feeds = $this->get_feeds_for_apartment($id);
        foreach ($feeds as $feed) {
            $this->delete_feed((int) $feed->id);
        }

        $result = $wpdb->delete($this->apartments_table(), ['id' => $id], ['%d']);

        ICSFM_Logger::info('admin', 'Apartment deleted', [
            'apartment_id' => $id,
            'feeds_deleted' => count($feeds),
            'user'         => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $result !== false;
    }

    public function count_apartments(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->apartments_table()}");
    }

    // ─── Feeds ───────────────────────────────────────────────

    public function get_all_feeds(): array {
        global $wpdb;
        $at = $this->apartments_table();
        $ft = $this->feeds_table();
        return $wpdb->get_results(
            "SELECT f.*, a.name AS apartment_name
             FROM {$ft} f
             LEFT JOIN {$at} a ON a.id = f.apartment_id
             ORDER BY a.sort_order ASC, a.name ASC, f.label ASC"
        ) ?: [];
    }

    public function get_active_feeds(): array {
        global $wpdb;
        $at = $this->apartments_table();
        $ft = $this->feeds_table();
        return $wpdb->get_results(
            "SELECT f.*, a.name AS apartment_name
             FROM {$ft} f
             LEFT JOIN {$at} a ON a.id = f.apartment_id
             WHERE f.is_active = 1
             ORDER BY a.sort_order ASC, a.name ASC, f.label ASC"
        ) ?: [];
    }

    public function get_feeds_for_apartment(int $apartment_id): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->feeds_table()} WHERE apartment_id = %d ORDER BY label ASC",
                $apartment_id
            )
        ) ?: [];
    }

    public function get_feed(int $id): ?object {
        global $wpdb;
        $at = $this->apartments_table();
        $ft = $this->feeds_table();
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT f.*, a.name AS apartment_name
                 FROM {$ft} f
                 LEFT JOIN {$at} a ON a.id = f.apartment_id
                 WHERE f.id = %d",
                $id
            )
        );
    }

    public function find_by_token(string $token): ?object {
        global $wpdb;
        $at = $this->apartments_table();
        $ft = $this->feeds_table();
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT f.*, a.name AS apartment_name
                 FROM {$ft} f
                 LEFT JOIN {$at} a ON a.id = f.apartment_id
                 WHERE f.proxy_token = %s",
                $token
            )
        );
    }

    public function create_feed(array $data): int {
        global $wpdb;

        $settings = get_option('icsfm_settings', []);
        $token = bin2hex(random_bytes(32));

        $wpdb->insert($this->feeds_table(), [
            'apartment_id'      => (int) $data['apartment_id'],
            'label'             => sanitize_text_field($data['label']),
            'platform'          => sanitize_text_field($data['platform'] ?? 'other'),
            'source_url'        => esc_url_raw($data['source_url']),
            'proxy_token'       => $token,
            'alert_window_hours'=> (int) ($data['alert_window_hours'] ?? $settings['default_alert_window'] ?? 6),
            'is_active'         => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'last_fetch_status' => 'never',
            'created_at'        => current_time('mysql', true),
            'updated_at'        => current_time('mysql', true),
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']);

        $id = (int) $wpdb->insert_id;

        ICSFM_Logger::info('admin', 'Feed created', [
            'feed_id'      => $id,
            'label'        => $data['label'],
            'platform'     => $data['platform'] ?? 'other',
            'apartment_id' => $data['apartment_id'],
            'user'         => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $id;
    }

    public function update_feed(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        $format = [];

        $field_map = [
            'apartment_id'       => '%d',
            'label'              => '%s',
            'platform'           => '%s',
            'source_url'         => '%s',
            'alert_window_hours' => '%d',
            'is_active'          => '%d',
            'last_polled_at'     => '%s',
            'last_poll_ip'       => '%s',
            'last_fetch_status'  => '%s',
            'last_alert_sent_at' => '%s',
            'proxy_token'        => '%s',
        ];

        foreach ($field_map as $field => $fmt) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if ($field === 'label' || $field === 'platform') {
                    $value = sanitize_text_field($value);
                } elseif ($field === 'source_url') {
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

    public function regenerate_token(int $feed_id): string {
        $new_token = bin2hex(random_bytes(32));
        $this->update_feed($feed_id, ['proxy_token' => $new_token]);

        ICSFM_Logger::warning('admin', 'Feed token regenerated', [
            'feed_id' => $feed_id,
            'user'    => wp_get_current_user()->user_login ?? 'system',
        ]);

        return $new_token;
    }

    public function delete_feed(int $id): bool {
        global $wpdb;

        $feed = $this->get_feed($id);
        $result = $wpdb->delete($this->feeds_table(), ['id' => $id], ['%d']);

        // Clean up poll log
        $wpdb->delete($wpdb->prefix . 'icsfm_poll_log', ['feed_id' => $id], ['%d']);

        if ($feed) {
            ICSFM_Logger::info('admin', 'Feed deleted', [
                'feed_id'  => $id,
                'label'    => $feed->label,
                'platform' => $feed->platform,
                'user'     => wp_get_current_user()->user_login ?? 'system',
            ]);
        }

        return $result !== false;
    }

    public function count_feeds(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->feeds_table()}");
    }

    public function count_active_feeds(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->feeds_table()} WHERE is_active = 1");
    }

    public function get_stale_feeds(int $cooldown_hours): array {
        global $wpdb;
        $at = $this->apartments_table();
        $ft = $this->feeds_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*, a.name AS apartment_name
                 FROM {$ft} f
                 LEFT JOIN {$at} a ON a.id = f.apartment_id
                 WHERE f.is_active = 1
                   AND (
                     f.last_polled_at IS NULL
                     OR f.last_polled_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL f.alert_window_hours HOUR)
                   )
                   AND (
                     f.last_alert_sent_at IS NULL
                     OR f.last_alert_sent_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)
                   )
                 ORDER BY f.last_polled_at ASC",
                $cooldown_hours
            )
        ) ?: [];
    }

    public function get_feeds_grouped_by_apartment(): array {
        $apartments = $this->get_all_apartments();
        $grouped = [];

        foreach ($apartments as $apartment) {
            $apartment->feeds = $this->get_feeds_for_apartment((int) $apartment->id);
            $grouped[] = $apartment;
        }

        return $grouped;
    }
}
