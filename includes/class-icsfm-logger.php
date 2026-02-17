<?php
defined('ABSPATH') || exit;

class ICSFM_Logger {

    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    const SOURCE_PROXY   = 'proxy';
    const SOURCE_CRON    = 'cron';
    const SOURCE_WEBHOOK = 'webhook';
    const SOURCE_ADMIN   = 'admin';
    const SOURCE_SYSTEM  = 'system';

    private static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'icsfm_log';
    }

    public static function debug(string $source, string $message, array $context = []): void {
        self::log(self::LEVEL_DEBUG, $source, $message, $context);
    }

    public static function info(string $source, string $message, array $context = []): void {
        self::log(self::LEVEL_INFO, $source, $message, $context);
    }

    public static function warning(string $source, string $message, array $context = []): void {
        self::log(self::LEVEL_WARNING, $source, $message, $context);
    }

    public static function error(string $source, string $message, array $context = []): void {
        self::log(self::LEVEL_ERROR, $source, $message, $context);
    }

    private static function log(string $level, string $source, string $message, array $context): void {
        global $wpdb;

        // Silently fail if table doesn't exist yet (during activation)
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert($table, [
            'level'      => $level,
            'source'     => $source,
            'message'    => $message,
            'context'    => !empty($context) ? wp_json_encode($context) : null,
            'created_at' => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%s']);
    }

    public static function get_logs(array $args = []): array {
        global $wpdb;
        $table = self::table_name();

        $defaults = [
            'level'   => '',
            'source'  => '',
            'search'  => '',
            'per_page' => 50,
            'page'    => 1,
        ];
        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $params[] = $args['level'];
        }

        if (!empty($args['source'])) {
            $where[] = 'source = %s';
            $params[] = $args['source'];
        }

        if (!empty($args['search'])) {
            $where[] = '(message LIKE %s OR context LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Count
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, ...$params);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Rows
        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
        $all_params = array_merge($params, [$args['per_page'], $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($query, ...$all_params));

        return [
            'logs'  => $rows ?: [],
            'total' => $total,
            'pages' => (int) ceil($total / $args['per_page']),
        ];
    }

    public static function get_recent(int $limit = 10): array {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit)
        ) ?: [];
    }

    public static function clear_all(): void {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . self::table_name());
    }

    public static function prune(int $days = 30): int {
        global $wpdb;
        $table = self::table_name();
        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
