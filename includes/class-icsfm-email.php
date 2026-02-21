<?php
defined('ABSPATH') || exit;

class ICSFM_Email {

    public function send_stale_alert(object $feed, int $alert_window_hours): bool {
        $to = $this->get_email();
        if (!$to) {
            return false;
        }

        $apartment    = $feed->apartment_name ?? 'Unknown';
        $feed_label   = ICSFM_Feed_Repository::derive_feed_label($feed);
        $dest_platform = ICSFM_Feed_Repository::get_dest_platform($feed);
        $window       = $alert_window_hours;
        $dashboard    = admin_url('admin.php?page=icsfm-dashboard');

        $avg_hours = $this->get_average_poll_interval((int) $feed->id);

        if ($feed->last_polled_at) {
            $hours_since  = round((time() - strtotime($feed->last_polled_at)) / 3600, 1);
            $last_checked = $this->format_time($feed->last_polled_at);
            $normally     = $avg_hours
                ? "It normally checks about every {$avg_hours} hours."
                : "It normally checks at least every {$window} hours.";
            $description  = "{$dest_platform} hasn't checked the <strong>{$feed_label}</strong> calendar for <strong>{$hours_since} hours</strong>. {$normally}";
        } else {
            $hours_since  = null;
            $last_checked = 'Never';
            $description  = "{$dest_platform} has never checked the <strong>{$feed_label}</strong> calendar.";
        }

        $subject = "⚠️ {$dest_platform} hasn't checked {$feed_label}";

        $usual_window = $avg_hours
            ? "About every {$avg_hours} hours"
            : "Every {$window} hours";

        $rows = [
            ['Calendar',       $feed_label],
            ['Apartment',      $apartment],
            ['Checked by',     $dest_platform],
            ['Last checked',   $last_checked],
            ['Usual frequency', $usual_window],
        ];

        $body_lines = [
            '<p style="font-size:16px;color:#333;line-height:1.5;margin:0 0 16px;">' . $description . '</p>',
            $this->build_table($rows),
        ];

        $html = $this->build_html(
            "{$dest_platform} hasn't checked {$feed_label}",
            '#c0392b',
            $body_lines,
            $dashboard
        );

        ICSFM_Logger::info('email', "Sending stale alert for feed #{$feed->id}", [
            'to'            => $to,
            'apartment'     => $apartment,
            'feed_label'    => $feed_label,
            'dest_platform' => $dest_platform,
        ]);

        return wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    }

    public function send_clear_alert(object $feed): bool {
        $to = $this->get_email();
        if (!$to) {
            return false;
        }

        $apartment     = $feed->apartment_name ?? 'Unknown';
        $feed_label    = ICSFM_Feed_Repository::derive_feed_label($feed);
        $dest_platform = ICSFM_Feed_Repository::get_dest_platform($feed);
        $last_checked  = $this->format_time($feed->last_polled_at);
        $dashboard     = admin_url('admin.php?page=icsfm-dashboard');

        $subject = "✅ {$dest_platform} is checking {$feed_label} again";

        $rows = [
            ['Calendar',    $feed_label],
            ['Apartment',   $apartment],
            ['Checked by',  $dest_platform],
            ['Last checked', $last_checked],
        ];

        $body_lines = [
            '<p style="font-size:16px;color:#333;line-height:1.5;margin:0 0 16px;">'
                . "{$dest_platform} checked the <strong>{$feed_label}</strong> calendar at <strong>{$last_checked}</strong>. Everything is back to normal.</p>",
            $this->build_table($rows),
        ];

        $html = $this->build_html(
            "Good news — {$dest_platform} is checking {$feed_label} again",
            '#27ae60',
            $body_lines,
            $dashboard
        );

        ICSFM_Logger::info('email', "Sending clear alert for feed #{$feed->id}", [
            'to'            => $to,
            'apartment'     => $apartment,
            'feed_label'    => $feed_label,
            'dest_platform' => $dest_platform,
        ]);

        return wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    }

    public function send_test(): bool {
        $to = $this->get_email();
        if (!$to) {
            return false;
        }

        $subject   = '✅ ICS Feed Monitor — Email alerts are working';
        $dashboard = admin_url('admin.php?page=icsfm-dashboard');

        $body_lines = [
            '<p style="font-size:16px;color:#333;line-height:1.5;margin:0 0 16px;">'
                . 'This is a test email from ICS Feed Monitor. If you received this, email alerts are working correctly.</p>',
        ];

        $html = $this->build_html(
            'Email alerts are working',
            '#27ae60',
            $body_lines,
            $dashboard
        );

        ICSFM_Logger::info('email', 'Sending test email', ['to' => $to]);

        return wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    }

    /* ------------------------------------------------------------------
     *  Private helpers
     * ----------------------------------------------------------------*/

    private function get_email(): string {
        $settings = get_option('icsfm_settings', []);
        return $settings['alert_email'] ?? '';
    }

    /**
     * Average hours between polls over the last 48 hours.
     * Returns null if fewer than 2 polls in that window.
     */
    private function get_average_poll_interval(int $feed_id): ?float {
        global $wpdb;
        $table = $wpdb->prefix . 'icsfm_poll_log';

        $times = $wpdb->get_col($wpdb->prepare(
            "SELECT UNIX_TIMESTAMP(polled_at)
             FROM {$table}
             WHERE feed_id = %d
               AND polled_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 48 HOUR)
             ORDER BY polled_at ASC",
            $feed_id
        ));

        if (count($times) < 2) {
            return null;
        }

        $total = 0;
        for ($i = 1; $i < count($times); $i++) {
            $total += $times[$i] - $times[$i - 1];
        }
        $avg_seconds = $total / (count($times) - 1);

        return round($avg_seconds / 3600, 1);
    }

    private function format_time(string $utc_datetime): string {
        $ts = strtotime($utc_datetime);
        return date('j M Y, g:ia', $ts) . ' UTC';
    }

    private function build_table(array $rows): string {
        $html = '<table style="border-collapse:collapse;width:100%;margin:16px 0;" cellpadding="0" cellspacing="0">';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr>'
                . '<td style="padding:8px 12px;border:1px solid #e0e0e0;background:#f8f8f8;font-weight:bold;color:#555;width:40%;font-size:14px;">'
                    . esc_html($label) . '</td>'
                . '<td style="padding:8px 12px;border:1px solid #e0e0e0;color:#333;font-size:14px;">'
                    . esc_html($value) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function build_html(string $heading, string $heading_color, array $body_lines, string $dashboard_url): string {
        $body_content = implode("\n", $body_lines);
        $heading_esc  = esc_html($heading);
        $url_esc      = esc_url($dashboard_url);

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;">

    <!-- Heading banner -->
    <tr>
        <td style="background:{$heading_color};padding:24px 32px;">
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;">{$heading_esc}</h1>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:24px 32px;">
            {$body_content}

            <!-- Dashboard button -->
            <p style="margin:24px 0 0;">
                <a href="{$url_esc}"
                   style="display:inline-block;padding:12px 24px;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:4px;font-size:14px;font-weight:bold;">
                    Open Dashboard
                </a>
            </p>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="padding:16px 32px;border-top:1px solid #e0e0e0;font-size:12px;color:#999;">
            Sent by ICS Feed Monitor
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
